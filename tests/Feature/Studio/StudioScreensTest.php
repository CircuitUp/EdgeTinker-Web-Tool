<?php

namespace Tests\Feature\Studio;

use App\Enums\RevisionStatus;
use App\Enums\UserStatus;
use App\Jobs\GenerateImageVariants;
use App\Livewire\Studio\Account;
use App\Livewire\Studio\Media\Library;
use App\Livewire\Studio\Products\Form as ProductForm;
use App\Livewire\Studio\Products\Index as ProductIndex;
use App\Livewire\Studio\Revisions\Show as RevisionShow;
use App\Livewire\Studio\Tags\Index as TagIndex;
use App\Livewire\Studio\Users\Index as UserIndex;
use App\Models\Media;
use App\Models\Product;
use App\Models\Revision;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\Studio\AccountStatusChanged;
use App\Notifications\Studio\ResetPasswordNotification;
use App\Studio\MediaLibrary;
use App\Studio\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\BuildsCatalogue;
use Tests\TestCase;

/** The Content Studio screens, driven the way a browser drives them. */
class StudioScreensTest extends TestCase
{
    use BuildsCatalogue;
    use RefreshDatabase;

    private User $admin;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('public');
        Queue::fake();

        $this->admin = User::factory()->admin()->create();
        $this->creator = User::factory()->create();
    }

    // ── Product form ─────────────────────────────────────────────────────────

    public function test_a_creator_can_add_a_product_with_just_the_basics_and_submit_it(): void
    {
        $category = $this->makeCategory('Sensor Boards');

        Livewire::actingAs($this->creator)->test(ProductForm::class)
            ->set('form.name', 'Quick Board')
            ->assertSet('form.slug', 'quick-board')          // the web address fills itself in
            ->set('form.category_id', $category->id)
            ->set('form.sku', 'et-quick-1')
            ->set('form.short_description', 'Added in under two minutes.')
            ->set('form.price_usd', '12.5')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('studio.products.index'));

        $revision = Revision::sole();
        $this->assertSame(RevisionStatus::Submitted, $revision->status);
        $this->assertSame($this->creator->id, $revision->author_id);
        $this->assertSame('Quick Board', $revision->payload['seo_title']);
        $this->assertSame(0, Product::count());
    }

    public function test_the_admin_publishes_straight_from_the_form(): void
    {
        $category = $this->makeCategory('Sensor Boards');

        Livewire::actingAs($this->admin)->test(ProductForm::class)
            ->set('form.name', 'Admin Board')->set('form.category_id', $category->id)
            ->set('form.sku', 'ET-ADM-1')->set('form.short_description', 'Live at once.')
            ->call('submit')->assertHasNoErrors();

        $this->assertSame('Admin Board', Product::sole()->name);
    }

    public function test_the_form_shows_validation_errors_next_to_the_fields(): void
    {
        Livewire::actingAs($this->creator)->test(ProductForm::class)
            ->set('form.name', '')
            ->call('submit')
            ->assertHasErrors(['form.name', 'form.sku', 'form.category_id', 'form.short_description'])
            ->assertNoRedirect();

        $this->assertSame(0, Revision::count());
    }

    public function test_images_can_be_uploaded_reordered_and_given_a_thumbnail(): void
    {
        $component = Livewire::actingAs($this->creator)->test(ProductForm::class)
            ->set('form.name', 'Pictured Board')
            ->set('imageUploads', [UploadedFile::fake()->image('front.png', 600, 600), UploadedFile::fake()->image('back.jpg', 600, 600)])
            ->assertHasNoErrors();

        $this->assertSame(2, Media::where('kind', 'image')->count());
        Queue::assertPushed(GenerateImageVariants::class, 2);

        $images = $component->get('form.images');
        $this->assertTrue($images[0]['is_thumbnail']);
        $this->assertFalse($images[1]['is_thumbnail']);
        $this->assertSame('Pictured Board', $images[0]['alt']);

        $component->call('setThumbnail', 1)->call('moveImage', 1, 0);
        $images = $component->get('form.images');
        $this->assertSame('back.jpg', Media::find($images[0]['media_id'])->original_name);
        $this->assertTrue($images[0]['is_thumbnail']);

        $component->call('removeImage', 0);
        $this->assertCount(1, $component->get('form.images'));
        $this->assertTrue($component->get('form.images.0.is_thumbnail'), 'Removing the thumbnail promotes the next image.');
    }

    public function test_dangerous_uploads_are_refused(): void
    {
        foreach ([
            UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>'),
            UploadedFile::fake()->createWithContent('fake.png', '<?php echo "not an image";'),
        ] as $file) {
            Livewire::actingAs($this->creator)->test(ProductForm::class)
                ->set('imageUploads', [$file])
                ->assertHasErrors('imageUploads.0');
        }

        Livewire::actingAs($this->creator)->test(ProductForm::class)
            ->set('documentUpload', UploadedFile::fake()->createWithContent('evil.php', '<?php'))
            ->assertHasErrors('documentUpload');

        $this->assertSame(0, Media::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_damaged_image_is_refused_at_upload(): void
    {
        // Starts with a valid PNG signature and header, so it passes the type checks, but the image data is broken.
        $damaged = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAAAT0lEQVR4nO3PQQ0AIBDAsAP/nuGNAvZoFSzZOjOz5m87DvjFQM1AzUDNQM1AzUDNQM1AzUDNQM1AzUDNQM1AzUDNQM1AzUDNQM1A7QJQIAF/aY3b3QAAAABJRU5ErkJggg==');

        Livewire::actingAs($this->creator)->test(ProductForm::class)
            ->set('imageUploads', [UploadedFile::fake()->createWithContent('damaged.png', $damaged)])
            ->assertHasErrors('imageUploads')
            ->assertSet('form.images', []);

        $this->assertSame(0, Media::count());
        Queue::assertNothingPushed();
    }

    public function test_documents_and_tags_are_attached_through_the_form(): void
    {
        $tag = Tag::create(['name' => 'I2C', 'slug' => 'i2c']);

        $component = Livewire::actingAs($this->creator)->test(ProductForm::class)
            ->set('documentUpload', UploadedFile::fake()->create('board-datasheet.pdf', 200, 'application/pdf'))
            ->assertHasNoErrors()
            ->call('toggleTag', $tag->id)
            ->set('newTag', 'i2c')->call('addNewTag')          // already exists: selected, not duplicated
            ->set('newTag', 'Brand New')->call('addNewTag');

        $this->assertSame('datasheet', $component->get('form.documents.0.type'));
        $this->assertSame('Board Datasheet', $component->get('form.documents.0.title'));
        $this->assertSame([$tag->id], $component->get('form.tag_ids'));
        $this->assertSame(['Brand New'], $component->get('form.new_tags'));
        $this->assertSame(1, Tag::count(), 'New tags are not created until approval.');
    }

    public function test_editing_a_live_product_starts_from_the_live_content(): void
    {
        $product = $this->makeProduct($this->makeCategory('Boards'), 'Live Board', ['sku' => 'ET-LIVE-1']);

        Livewire::actingAs($this->creator)->test(ProductForm::class, ['product' => $product->id])
            ->assertSet('form.name', 'Live Board')
            ->assertSet('form.sku', 'ET-LIVE-1')
            ->set('form.short_description', 'Edited.')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $revision = Revision::sole();
        $this->assertSame(RevisionStatus::Draft, $revision->status);
        $this->assertSame($product->id, $revision->entity_id);
        $this->assertSame('Short description.', $product->fresh()->short_description);
    }

    public function test_a_creator_cannot_open_another_creators_draft(): void
    {
        $revision = app(RevisionService::class)->save($this->creator, 'tag', null, ['name' => 'Mine'], submit: false);
        $other = User::factory()->create();

        Livewire::actingAs($other)->test(RevisionShow::class, ['revision' => $revision->id])->assertForbidden();
        $this->actingAs($other)->get(route('studio.revisions.show', $revision))->assertForbidden();

        Livewire::actingAs($this->creator)->test(RevisionShow::class, ['revision' => $revision->id])->assertOk()->assertSee('Mine');
        Livewire::actingAs($this->admin)->test(RevisionShow::class, ['revision' => $revision->id])->assertOk();
    }

    // ── Review ───────────────────────────────────────────────────────────────

    public function test_the_admin_reviews_from_the_review_screen(): void
    {
        $category = $this->makeCategory('Boards');
        $payload = ['name' => 'Reviewed Board', 'sku' => 'ET-REV-1', 'category_id' => $category->id, 'short_description' => 'For review.'];
        $revision = app(RevisionService::class)->save($this->creator, 'product', null, $payload, submit: true);

        // Rejecting without a comment is refused on the screen.
        Livewire::actingAs($this->admin)->test(RevisionShow::class, ['revision' => $revision->id])
            ->assertSee('Proposed content')->assertSee('Reviewed Board')
            ->call('reject')->assertHasErrors('comment');
        $this->assertSame(RevisionStatus::Submitted, $revision->fresh()->status);

        Livewire::actingAs($this->admin)->test(RevisionShow::class, ['revision' => $revision->id])
            ->call('approve')->assertRedirect(route('studio.review.index'));

        $this->assertSame('Reviewed Board', Product::sole()->name);
    }

    public function test_a_creator_cannot_approve_through_the_screen(): void
    {
        $revision = app(RevisionService::class)->save($this->creator, 'tag', null, ['name' => 'Self Approved'], submit: true);

        Livewire::actingAs($this->creator)->test(RevisionShow::class, ['revision' => $revision->id])
            ->call('approve')
            ->assertForbidden();

        $this->assertSame(0, Tag::count());
        $this->assertSame(RevisionStatus::Submitted, $revision->fresh()->status);
    }

    public function test_the_before_and_after_comparison_marks_what_changed(): void
    {
        $product = $this->makeProduct($this->makeCategory('Boards'), 'Compared Board');
        $current = app('App\Studio\ContentTypes\ContentTypeRegistry')->get('product')->current($product->id);
        $revision = app(RevisionService::class)->save($this->creator, 'product', $product->id, ['short_description' => 'A new description.'] + $current, submit: true);

        Livewire::actingAs($this->admin)->test(RevisionShow::class, ['revision' => $revision->id])
            ->assertSee('1 field changed')
            ->assertSee('Short description.')      // live now
            ->assertSee('A new description.')      // proposed
            ->assertDontSee('SEO title')           // unchanged fields are hidden by default
            ->set('onlyChanges', false)
            ->assertSee('SEO title');
    }

    public function test_the_preview_shows_the_proposed_page_without_publishing_it(): void
    {
        $category = $this->makeCategory('Boards');
        $revision = app(RevisionService::class)->save($this->creator, 'product', null, [
            'name' => 'Previewed Board', 'sku' => 'ET-PRE-1', 'category_id' => $category->id, 'short_description' => 'Only a preview.',
        ], submit: true);

        $this->actingAs($this->admin)->get(route('studio.revisions.preview', $revision))->assertOk()
            ->assertSee('Previewed Board')->assertSee('Only a preview.')->assertSee('Preview only')->assertSee('noindex', false);
        $this->actingAs($this->creator)->get(route('studio.revisions.preview', $revision))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('studio.revisions.preview', $revision))->assertForbidden();

        $this->assertSame(0, Product::count());
        $this->get('/product/previewed-board')->assertNotFound();
    }

    public function test_deletion_is_requested_from_the_product_list(): void
    {
        $product = $this->makeProduct($this->makeCategory('Boards'), 'Doomed Board');

        Livewire::actingAs($this->creator)->test(ProductIndex::class)->call('requestDeletion', $product->id);
        $this->assertNotNull($product->fresh(), 'A creator’s request does not delete anything.');
        $this->assertSame('delete', Revision::sole()->action->value);

        Livewire::actingAs($this->creator)->test(ProductIndex::class)->assertSee('Deletion pending');
    }

    // ── Tags ─────────────────────────────────────────────────────────────────

    public function test_tags_are_managed_on_one_screen(): void
    {
        Livewire::actingAs($this->admin)->test(TagIndex::class)->set('newName', 'LoRaWAN')->call('add')->assertHasNoErrors();
        $tag = Tag::sole();

        Livewire::actingAs($this->admin)->test(TagIndex::class)->set('newName', 'lorawan')->call('add')->assertHasErrors('newName');

        Livewire::actingAs($this->creator)->test(TagIndex::class)
            ->call('startEditing', $tag->id)->set('editingName', 'LoRa WAN')->call('rename')->assertHasNoErrors();
        $this->assertSame('LoRaWAN', $tag->fresh()->name, 'A creator’s rename waits for approval.');
    }

    // ── Users (Content Admin) ────────────────────────────────────────────────

    public function test_the_admin_activates_suspends_resets_and_deletes_creators(): void
    {
        $pending = User::factory()->pending()->create();

        $screen = Livewire::actingAs($this->admin)->test(UserIndex::class)->assertSee($pending->email);

        $screen->call('activate', $pending->id);
        $this->assertSame(UserStatus::Active, $pending->fresh()->status);
        Notification::assertSentTo($pending, AccountStatusChanged::class);

        $screen->call('suspend', $pending->id);
        $this->assertSame(UserStatus::Suspended, $pending->fresh()->status);

        $screen->call('sendPasswordReset', $pending->id);
        Notification::assertSentTo($pending, ResetPasswordNotification::class);

        $revision = app(RevisionService::class)->save($this->creator, 'tag', null, ['name' => 'Kept'], submit: true);
        $screen->call('delete', $this->creator->id);
        $this->assertNull(User::find($this->creator->id));
        $this->assertNull($revision->fresh()->author_id, 'Submissions outlive their author.');
    }

    public function test_an_unverified_creator_cannot_be_activated(): void
    {
        $unverified = User::factory()->pending()->unverified()->create();

        Livewire::actingAs($this->admin)->test(UserIndex::class)->call('activate', $unverified->id);

        $this->assertSame(UserStatus::Pending, $unverified->fresh()->status);
    }

    public function test_creators_cannot_use_the_users_screen_and_the_admin_cannot_be_managed_from_it(): void
    {
        $victim = User::factory()->pending()->create();

        Livewire::actingAs($this->creator)->test(UserIndex::class)->assertForbidden();
        $this->assertSame(UserStatus::Pending, $victim->fresh()->status);

        // The admin account is not a "creator", so the screen's actions cannot target it.
        Livewire::actingAs($this->admin)->test(UserIndex::class)->call('delete', $this->admin->id)->assertNotFound();
        $this->assertNotNull($this->admin->fresh());
    }

    // ── Media library ────────────────────────────────────────────────────────

    public function test_files_in_use_cannot_be_deleted_and_creators_only_delete_their_own(): void
    {
        Livewire::actingAs($this->creator)->test(Library::class)
            ->set('uploads', [UploadedFile::fake()->image('used.png', 300, 300), UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf')])
            ->assertHasNoErrors();

        $image = Media::where('kind', 'image')->sole();
        $document = Media::where('kind', 'document')->sole();

        // Used by a pending change: protected.
        app(RevisionService::class)->save($this->creator, 'category', null, ['name' => 'Uses Image', 'image' => ['media_id' => $image->id]], submit: false);
        Livewire::actingAs($this->creator)->test(Library::class)->call('delete', $image->id);
        $this->assertNotNull($image->fresh());

        // Someone else's upload: forbidden.
        Livewire::actingAs(User::factory()->create())->test(Library::class)->call('delete', $document->id)->assertForbidden();
        $this->assertNotNull($document->fresh());

        Livewire::actingAs($this->creator)->test(Library::class)->call('delete', $document->id);
        $this->assertNull($document->fresh());
        Storage::disk('public')->assertMissing($document->path);
    }

    public function test_uploaded_images_get_resized_webp_versions(): void
    {
        Queue::fake([]); // let this job run
        Storage::fake('public');

        // A real PNG (the image library must be able to decode it).
        $source = imagecreatetruecolor(1000, 600);
        imagefill($source, 0, 0, imagecolorallocate($source, 109, 40, 217));
        ob_start();
        imagepng($source);
        $file = UploadedFile::fake()->createWithContent('board.png', ob_get_clean());

        $media = app(MediaLibrary::class)->store($file, Media::KIND_IMAGE, $this->creator);
        (new GenerateImageVariants($media->id))->handle();

        $media->refresh();
        $this->assertSame([1000, 600], [$media->width, $media->height]);
        $this->assertSame(['400', '800'], array_map('strval', array_keys($media->variants)), 'No version is made larger than the original.');

        foreach ($media->variants as $width => $path) {
            Storage::disk('public')->assertExists($path);
            [$actualWidth] = getimagesize(Storage::disk('public')->path($path));
            $this->assertSame((int) $width, $actualWidth);
            $this->assertStringEndsWith('.webp', $path);
        }
        $this->assertStringContainsString('400w', $media->srcset());
    }

    // ── Account ──────────────────────────────────────────────────────────────

    public function test_changing_the_password_needs_the_current_one(): void
    {
        Livewire::actingAs($this->creator)->test(Account::class)
            ->set('currentPassword', 'wrong')->set('newPassword', 'Another-Pass-77')->set('newPasswordConfirmation', 'Another-Pass-77')
            ->call('changePassword')->assertHasErrors('currentPassword');

        Livewire::actingAs($this->creator)->test(Account::class)
            ->set('currentPassword', 'Password-123')->set('newPassword', 'Another-Pass-77')->set('newPasswordConfirmation', 'Another-Pass-77')
            ->call('changePassword')->assertHasNoErrors();

        $this->assertTrue(Hash::check('Another-Pass-77', $this->creator->fresh()->password));
    }

    public function test_two_factor_is_only_on_after_a_code_is_confirmed_and_only_for_the_admin(): void
    {
        Livewire::actingAs($this->creator)->test(Account::class)->call('startTwoFactor')->assertForbidden();

        $screen = Livewire::actingAs($this->admin)->test(Account::class)->call('startTwoFactor');
        $this->assertFalse($this->admin->fresh()->hasTwoFactorEnabled(), 'Scanning the QR code alone must not turn it on.');

        $screen->set('code', '000000')->call('confirmTwoFactor')->assertHasErrors('code');
        $this->assertFalse($this->admin->fresh()->hasTwoFactorEnabled());

        $code = app(Google2FA::class)->getCurrentOtp($this->admin->fresh()->two_factor_secret);
        $screen->set('code', $code)->call('confirmTwoFactor')->assertHasNoErrors();

        $this->assertTrue($this->admin->fresh()->hasTwoFactorEnabled());
        $this->assertCount(8, $this->admin->fresh()->two_factor_recovery_codes);

        // Turning it off needs the password.
        $screen->set('confirmPassword', 'wrong')->call('disableTwoFactor')->assertHasErrors('confirmPassword');
        $this->assertTrue($this->admin->fresh()->hasTwoFactorEnabled());
        $screen->set('confirmPassword', 'Password-123')->call('disableTwoFactor');
        $this->assertFalse($this->admin->fresh()->hasTwoFactorEnabled());
    }
}
