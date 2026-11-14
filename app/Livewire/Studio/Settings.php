<?php

namespace App\Livewire\Studio;

use App\Http\Controllers\ContactController;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Media;
use App\Studio\Audit;
use App\Studio\MediaLibrary;
use App\Support\Settings as SiteSettings;
use App\Support\SiteCache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Site settings (Content Admin only): logo, contact details, social links and
 * an email test. Settings apply immediately; they do not go through the
 * approval workflow because only the admin can change them.
 */
#[Title('Site settings')]
class Settings extends Component
{
    use StudioScreen, WithFileUploads;

    /** Setting key => form field. Dots are not allowed in Livewire property paths, so fields use underscores. */
    private const FIELDS = [
        'site.name' => 'site_name', 'site.tagline' => 'site_tagline',
        'contact.email' => 'contact_email', 'contact.phone' => 'contact_phone', 'contact.address' => 'contact_address',
        'contact.map_embed_url' => 'contact_map_embed_url', 'contact.recipient' => 'contact_recipient',
        'social.linkedin' => 'social_linkedin', 'social.github' => 'social_github',
        'social.youtube' => 'social_youtube', 'social.facebook' => 'social_facebook',
    ];

    public array $form = [];

    public $logoUpload = null;

    public ?string $mailResult = null;

    public bool $mailOk = false;

    public function mount(SiteSettings $settings): void
    {
        abort_unless($this->user()->isAdmin(), 403);

        foreach (self::FIELDS as $key => $field) {
            $this->form[$field] = $this->current($settings, $key);
        }
    }

    /**
     * The value in effect for a setting, as shown in the form. Shipped
     * "[PLACEHOLDER] …" defaults appear as an empty box; real defaults
     * (such as the site name) appear as they are.
     */
    private function current(SiteSettings $settings, string $key): string
    {
        $value = (string) $settings->get($key);

        return str_starts_with($value, '[PLACEHOLDER]') ? '' : $value;
    }

    public function save(SiteSettings $settings): void
    {
        abort_unless($this->user()->isAdmin(), 403);

        $social = ['nullable', 'string', 'max:255', 'url:https'];

        $this->validate([
            'form.site_name' => ['required', 'string', 'max:60'],
            'form.site_tagline' => ['nullable', 'string', 'max:160'],
            'form.contact_email' => ['nullable', 'email', 'max:255'],
            'form.contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s.]+$/'],
            'form.contact_address' => ['nullable', 'string', 'max:300'],
            // Only embed addresses from these two map providers may be framed on the Contact page.
            'form.contact_map_embed_url' => ['nullable', 'string', 'max:2000', 'regex:#^https://(www\.google\.com/maps/embed\?|www\.openstreetmap\.org/export/embed\.html\?)#'],
            'form.contact_recipient' => ['nullable', 'email', 'max:255'],
            'form.social_linkedin' => $social, 'form.social_github' => $social,
            'form.social_youtube' => $social, 'form.social_facebook' => $social,
        ], [
            'form.contact_map_embed_url.regex' => 'Paste the address from Google Maps (Share → Embed a map → the src="…" value) or an OpenStreetMap embed link.',
            'form.contact_phone.regex' => 'The phone number may contain digits, spaces and + ( ) - . only.',
            'url' => 'Enter a full address starting with https://',
        ], collect(self::FIELDS)->mapWithKeys(fn ($field, $key) => ["form.$field" => str_replace(['.', '_'], ' ', $key)])->all());

        $changed = [];
        foreach (self::FIELDS as $key => $field) {
            $value = trim((string) $this->form[$field]);
            if ($value !== $this->current($settings, $key)) {
                $settings->set($key, $value === '' ? null : $value);
                $changed[] = $key;
            }
        }

        if ($changed) {
            SiteCache::flush();
            Audit::log('settings.updated', 'Changed site settings: '.implode(', ', $changed));
        }

        $this->toast('success', $changed ? 'Settings saved. The site has been updated.' : 'Nothing was changed.');
    }

    public function updatedLogoUpload(MediaLibrary $library, SiteSettings $settings): void
    {
        abort_unless($this->user()->isAdmin(), 403);

        $this->validate(['logoUpload' => MediaLibrary::rules(Media::KIND_IMAGE)], [
            'logoUpload.mimes' => 'The logo must be a PNG, JPG, WebP or GIF file.', 'logoUpload.max' => 'The logo must be 8 MB or smaller.',
        ]);

        try {
            $media = $library->store($this->logoUpload, Media::KIND_IMAGE, $this->user());
        } catch (ValidationException $e) {
            $this->addError('logoUpload', collect($e->errors())->flatten()->first());
            $this->logoUpload = null;

            return;
        }

        $settings->set('site.logo_path', $media->path);
        SiteCache::flush();
        Audit::log('settings.updated', 'Replaced the site logo');

        $this->logoUpload = null;
        $this->toast('success', 'Logo updated.');
    }

    public function removeLogo(SiteSettings $settings): void
    {
        abort_unless($this->user()->isAdmin(), 403);

        $settings->set('site.logo_path', null);
        SiteCache::flush();
        Audit::log('settings.updated', 'Removed the site logo (back to the text wordmark)');
        $this->toast('success', 'Logo removed. The site shows the text wordmark again.');
    }

    /** Sends a real email straight away (not queued) so a mail configuration problem is reported here. */
    public function sendTestEmail(): void
    {
        abort_unless($this->user()->isAdmin(), 403);

        $to = $this->user()->email;
        $mailer = config('mail.default');

        try {
            Mail::raw("This is a test email from the EdgeTinker website.\n\nIf you are reading this, outgoing email is working.\nSent ".now()->toDayDateTimeString().'.',
                fn ($message) => $message->to($to)->subject('EdgeTinker email test'));

            $this->mailOk = true;
            $this->mailResult = in_array($mailer, ['log', 'array'], true)
                ? "The site is set to the “{$mailer}” mailer, which records emails instead of sending them (normal on a development computer). "
                    .'On the live server, set MAIL_MAILER=smtp in .env.'
                : "A test email was sent to $to. Check the inbox (and the spam folder).";
            Audit::log('settings.mail_test', 'Sent a test email');
        } catch (Throwable $e) {
            $this->mailOk = false;
            // The first line of the error is enough to diagnose and never includes the password.
            $this->mailResult = 'The email could not be sent: '.mb_substr(strtok($e->getMessage(), "\n"), 0, 300);
        }
    }

    public function render(SiteSettings $settings)
    {
        abort_unless($this->user()->isAdmin(), 403);

        return view('livewire.studio.settings', [
            'logoUrl' => media_url($settings->get('site.logo_path')),
            'recipient' => ContactController::recipient($settings),
            'mailer' => config('mail.default'),
            'mailFrom' => config('mail.from.address'),
        ]);
    }
}
