<?php

namespace Tests\Feature\Studio;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Studio\Auth\LoginController;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Studio\CreatorAwaitingActivation;
use App\Notifications\Studio\ResetPasswordNotification;
use App\Notifications\Studio\VerifyEmailNotification;
use App\Studio\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Who can get into Content Studio, and how. */
class StudioAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Password-123';

    private function login(User $user, string $password = self::PASSWORD)
    {
        return $this->post('/contentcreator/login', ['email' => $user->email, 'password' => $password]);
    }

    // ── The hidden address ───────────────────────────────────────────────────

    public function test_guests_are_sent_to_the_sign_in_page(): void
    {
        foreach (['/contentcreator', '/contentcreator/products', '/contentcreator/review', '/contentcreator/users', '/contentcreator/media'] as $url) {
            $this->get($url)->assertRedirect('/contentcreator/login');
        }
    }

    public function test_every_studio_response_tells_search_engines_to_stay_away(): void
    {
        $this->get('/contentcreator/login')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false);

        $this->actingAs(User::factory()->create())->get('/contentcreator')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_the_public_site_never_links_to_content_studio(): void
    {
        foreach (['/', '/products', '/search?q=x', '/services', '/contact'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('contentcreator');
        }
    }

    // ── Signing in ───────────────────────────────────────────────────────────

    public function test_an_active_creator_can_sign_in_and_out(): void
    {
        $user = User::factory()->create();

        $this->login($user)->assertRedirect('/contentcreator');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(AuditLog::where('event', 'auth.login')->where('user_id', $user->id)->exists());

        $this->post('/contentcreator/logout')->assertRedirect('/contentcreator/login');
        $this->assertGuest();
    }

    public function test_wrong_details_give_the_same_message_whether_or_not_the_account_exists(): void
    {
        $user = User::factory()->create();

        $wrongPassword = $this->login($user, 'wrong-password')->assertSessionHasErrors('email');
        $unknownEmail = $this->post('/contentcreator/login', ['email' => 'nobody@example.com', 'password' => 'x'])->assertSessionHasErrors('email');

        $this->assertSame(session('errors')->first('email'), 'These details do not match our records.');
        $this->assertGuest();
    }

    public function test_accounts_that_are_not_active_cannot_sign_in(): void
    {
        $this->login(User::factory()->pending()->create())->assertSessionHasErrors('email');
        $this->assertStringContainsString('waiting for the Content Admin', session('errors')->first('email'));
        $this->assertGuest();

        $this->login(User::factory()->suspended()->create())->assertSessionHasErrors('email');
        $this->assertStringContainsString('suspended', session('errors')->first('email'));
        $this->assertGuest();

        $this->login(User::factory()->unverified()->create())->assertRedirect('/contentcreator/verify-email');
        $this->assertGuest();
    }

    public function test_sign_in_locks_after_repeated_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < LoginController::MAX_ATTEMPTS; $i++) {
            $this->login($user, 'wrong-password');
        }

        // Even the correct password is refused while locked out.
        $this->login($user)->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many failed sign-in attempts', session('errors')->first('email'));
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('event', 'auth.lockout')->exists());
    }

    public function test_suspending_someone_locks_them_out_even_while_signed_in(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/contentcreator')->assertOk();

        $user->status = UserStatus::Suspended;
        $user->save();

        $this->get('/contentcreator')->assertRedirect('/contentcreator/login');
        $this->assertGuest();
    }

    // ── Roles ────────────────────────────────────────────────────────────────

    public function test_admin_only_screens_are_forbidden_to_creators(): void
    {
        $creator = User::factory()->create();

        foreach (['/contentcreator/review', '/contentcreator/users', '/contentcreator/audit-log'] as $url) {
            $this->actingAs($creator)->get($url)->assertForbidden();
        }

        $admin = User::factory()->admin()->create();
        foreach (['/contentcreator/review', '/contentcreator/users', '/contentcreator/audit-log'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_creator_screens_open_for_both_roles(): void
    {
        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            foreach (['', '/products', '/products/new', '/categories', '/categories/new', '/tags', '/media', '/submissions', '/account'] as $path) {
                $this->actingAs($user)->get('/contentcreator'.$path)->assertOk();
            }
        }
    }

    // ── Registration ─────────────────────────────────────────────────────────

    public function test_registration_creates_a_pending_creator_who_cannot_sign_in_yet(): void
    {
        Notification::fake();

        $this->post('/contentcreator/register', [
            'name' => 'New Person', 'email' => 'new@example.com',
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
            // Attempts to self-promote must be ignored.
            'role' => 'admin', 'status' => 'active', 'email_verified_at' => now()->toDateTimeString(),
        ])->assertRedirect('/contentcreator/verify-email');

        $user = User::where('email', 'new@example.com')->sole();
        $this->assertSame(UserRole::Creator, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertGuest();
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->login($user)->assertRedirect('/contentcreator/verify-email');
        $this->assertGuest();
    }

    public function test_registration_enforces_the_password_policy_and_unique_email(): void
    {
        $existing = User::factory()->create();

        $this->post('/contentcreator/register', [
            'name' => 'X', 'email' => $existing->email, 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertSame(1, User::count());
    }

    public function test_the_registration_honeypot_silently_drops_bots(): void
    {
        $this->post('/contentcreator/register', [
            'name' => 'Bot', 'email' => 'bot@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
            'website' => 'http://spam.example',
        ])->assertRedirect('/contentcreator/verify-email');

        $this->assertSame(0, User::count());
    }

    public function test_confirming_the_email_address_notifies_the_admin_but_does_not_activate(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->pending()->unverified()->create();

        $url = URL::temporarySignedRoute('studio.verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect('/contentcreator/login');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame(UserStatus::Pending, $user->fresh()->status);
        Notification::assertSentTo($admin, CreatorAwaitingActivation::class);

        // A tampered link is refused.
        $other = User::factory()->pending()->unverified()->create();
        $this->get(str_replace("/{$user->id}/", "/{$other->id}/", $url))->assertForbidden();
        $this->assertNull($other->fresh()->email_verified_at);
    }

    // ── Password reset ───────────────────────────────────────────────────────

    public function test_a_password_can_be_reset_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/contentcreator/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $this->post('/contentcreator/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status'); // same answer

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/contentcreator/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'Brand-New-Pass-9', 'password_confirmation' => 'Brand-New-Pass-9',
        ])->assertRedirect('/contentcreator/login');

        $this->assertTrue(Hash::check('Brand-New-Pass-9', $user->fresh()->password));
        $this->login($user, 'Brand-New-Pass-9')->assertRedirect('/contentcreator');
    }

    // ── Two-factor authentication ────────────────────────────────────────────

    private function adminWithTwoFactor(): array
    {
        $secret = app(TwoFactor::class)->generateSecret();
        $admin = User::factory()->admin()->create();
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['aaaaa-bbbbb', 'ccccc-ddddd'],
        ])->save();

        return [$admin, $secret];
    }

    public function test_two_factor_asks_for_a_code_after_the_password(): void
    {
        [$admin, $secret] = $this->adminWithTwoFactor();

        $this->login($admin)->assertRedirect('/contentcreator/two-factor');
        $this->assertGuest(); // the password alone is not enough

        $this->post('/contentcreator/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->post('/contentcreator/two-factor', ['code' => $code])->assertRedirect('/contentcreator');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_two_factor_code_cannot_be_used_twice(): void
    {
        [$admin, $secret] = $this->adminWithTwoFactor();
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->assertTrue(app(TwoFactor::class)->verifyCode($admin, $secret, $code));
        $this->assertFalse(app(TwoFactor::class)->verifyCode($admin, $secret, $code));
    }

    public function test_a_recovery_code_works_once(): void
    {
        [$admin] = $this->adminWithTwoFactor();

        $this->login($admin);
        $this->post('/contentcreator/two-factor', ['recovery_code' => 'AAAAA-BBBBB'])->assertRedirect('/contentcreator');
        $this->assertAuthenticatedAs($admin);
        $this->assertSame(['ccccc-ddddd'], $admin->fresh()->two_factor_recovery_codes);

        $this->post('/contentcreator/logout');
        $this->login($admin);
        $this->post('/contentcreator/two-factor', ['recovery_code' => 'aaaaa-bbbbb'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_the_two_factor_page_is_unreachable_without_the_password_step(): void
    {
        $this->get('/contentcreator/two-factor')->assertRedirect('/contentcreator/login');
        $this->post('/contentcreator/two-factor', ['code' => '123456'])->assertRedirect('/contentcreator/login');
    }

    // ── The single admin ─────────────────────────────────────────────────────

    public function test_the_admin_is_created_by_command_and_only_once(): void
    {
        $this->artisan('studio:create-admin', ['--name' => 'Ada', '--email' => 'ada@example.com', '--password' => self::PASSWORD])
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertTrue($admin->isAdmin() && $admin->isActive() && $admin->hasVerifiedEmail());

        $this->artisan('studio:create-admin', ['--name' => 'Second', '--email' => 'second@example.com', '--password' => self::PASSWORD])
            ->assertFailed();
        $this->assertSame(1, User::count());
    }

    public function test_the_admin_command_rejects_a_weak_password(): void
    {
        $this->artisan('studio:create-admin', ['--name' => 'Ada', '--email' => 'ada@example.com', '--password' => 'weak'])->assertFailed();
        $this->assertSame(0, User::count());
    }
}
