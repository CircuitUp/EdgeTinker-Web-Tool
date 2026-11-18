<?php

namespace App\Livewire\Studio\Banner;

use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Media;
use App\Models\Revision;
use App\Studio\ContentTypes\BannerType;
use App\Studio\ContentTypes\ContentTypeRegistry;
use App\Studio\MediaLibrary;
use App\Studio\RevisionService;
use App\Support\Banner\BannerConfig;
use App\Support\Banner\BannerFonts;
use App\Support\Banner\FontInstaller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The visual home banner editor.
 *
 * The editing itself happens in the browser (resources/js/banner/editor.js);
 * this component loads the config, stores uploads, downloads fonts and saves
 * the result through the approval workflow (RevisionService): Save draft
 * keeps a private draft, Publish (Content Admin) or Submit for approval
 * (Content Creator) sends it on. The live banner only changes on approval.
 */
class Editor extends Component
{
    use StudioScreen, WithFileUploads;

    #[Locked]
    public int $entityId;

    #[Locked]
    public ?int $revisionId = null;

    /** A file picked in the editor, uploaded by $wire.upload('upload', …). */
    public $upload;

    public function mount(ContentTypeRegistry $types, ?int $revision = null): void
    {
        $user = $this->user();

        if ($revision !== null) {
            $record = Revision::where('entity_type', 'banner')->findOrFail($revision);
            abort_unless($user->isAdmin() || $record->author_id === $user->id, 403);
            abort_unless($record->isEditable(), 404);

            $this->revisionId = $record->id;
            $this->entityId = $record->entity_id;

            return;
        }

        $this->entityId = BannerType::homeId();

        // Continue your own unfinished draft or submission rather than starting a second one.
        $mine = Revision::query()->forEntity('banner', $this->entityId)->pending()
            ->where('author_id', $user->id)->get()->first(fn (Revision $r) => $r->isEditable());
        if ($mine) {
            $this->redirectRoute('studio.banner.revision', $mine->id);
        }
    }

    private function config(): array
    {
        $payload = $this->revisionId
            ? Revision::find($this->revisionId)?->payload
            : app(ContentTypeRegistry::class)->get('banner')->current($this->entityId);

        return BannerConfig::normalise($payload ?? BannerConfig::empty());
    }

    /** Media library entries as the editor needs them. */
    private function describe(Media $media): array
    {
        return [
            'id' => $media->id,
            'kind' => $media->kind,
            'name' => $media->original_name,
            'url' => $media->url,
            'thumb' => $media->isImage() ? (empty($media->variants) ? $media->url : $media->variantUrl(400)) : null,
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    // ── Called from the editor ───────────────────────────────────────────────

    /** Store the uploaded file in the media library; returns its entry, or an error message. */
    public function storeUpload(string $kind): array
    {
        $kind = $kind === Media::KIND_VIDEO ? Media::KIND_VIDEO : Media::KIND_IMAGE;
        $validator = validator(['file' => $this->upload], ['file' => MediaLibrary::rules($kind)]);

        if ($validator->fails()) {
            $this->reset('upload');

            return ['error' => $kind === Media::KIND_VIDEO
                ? 'The video must be an MP4 or WebM file of 30 MB or less.'
                : 'The image must be a JPG, PNG, WebP or GIF file of 8 MB or less.'];
        }

        try {
            $media = app(MediaLibrary::class)->store($this->upload, $kind, $this->user());
        } catch (ValidationException $e) {
            return ['error' => collect($e->errors())->flatten()->first()];
        } finally {
            $this->reset('upload');
        }

        return ['media' => $this->describe($media)];
    }

    /** The latest images or videos in the media library, for the picker. */
    public function library(string $kind): array
    {
        return Media::where('kind', $kind === Media::KIND_VIDEO ? Media::KIND_VIDEO : Media::KIND_IMAGE)
            ->latest()->limit(60)->get()->map(fn (Media $m) => $this->describe($m))->all();
    }

    /** Make sure a Google font face is on this server; returns its stylesheet URL. */
    public function loadFont(string $family, int $weight = 400, bool $italic = false): array
    {
        if (! BannerFonts::exists($family)) {
            return ['error' => 'Unknown font.'];
        }
        if (! BannerFonts::isGoogle($family)) {
            return ['css' => null];
        }

        $installer = app(FontInstaller::class);
        if (! $installer->ensure($family, $weight, $italic)) {
            return ['error' => BannerFonts::FAMILIES[$family][0].' could not be downloaded. Check the server can reach fonts.googleapis.com, or choose another font.'];
        }

        return ['css' => $installer->stylesheetUrl($family)];
    }

    /** The banner with its original design restored (words and backgrounds kept). */
    public function resetDesign(array $config): array
    {
        return BannerConfig::resetDesign($config);
    }

    /** A fresh slide, text element or button in the default design. */
    public function template(string $kind): array
    {
        $item = match ($kind) {
            'slide' => BannerConfig::slide('Your headline here', 'A short line that explains it.', 'Learn more', '/products', name: 'New slide'),
            'button' => BannerConfig::buttonElement(),
            default => BannerConfig::textElement('New text'),
        };

        // Normalise through a one-slide banner so the shape is exactly what the editor expects.
        $config = BannerConfig::normalise(['slides' => [$kind === 'slide' ? $item : ['elements' => [$item]]]]);

        return $kind === 'slide' ? $config['slides'][0] : $config['slides'][0]['elements'][0];
    }

    /**
     * Save a draft, or submit (a Content Admin's submission is published at once).
     * Returns validation errors for the editor to show; on success it redirects.
     */
    public function save(array $config, bool $submit)
    {
        $user = $this->user();
        $revisions = app(RevisionService::class);

        try {
            $revision = $revisions->save($user, 'banner', $this->entityId, $config, $submit,
                $this->revisionId ? Revision::find($this->revisionId) : null);
        } catch (ValidationException $e) {
            return ['errors' => $e->errors()];
        }

        if (! $submit) {
            $this->flashToast('success', 'Draft saved. It is not visible on the live site.');

            return $this->redirectRoute('studio.banner.revision', $revision->id);
        }

        if ($user->isAdmin()) {
            $failed = app(FontInstaller::class)->ensureFor($revision->payload);
            $this->flashToast($failed ? 'warning' : 'success', $failed
                ? 'The banner is live, but these fonts could not be downloaded and fall back to the site font: '.implode(', ', $failed).'.'
                : 'The new banner is live on the home page.');

            return $this->redirectRoute('studio.banner.edit');
        }

        $this->flashToast('success', 'Your banner changes were submitted for review.');

        return $this->redirectRoute('studio.revisions.index');
    }

    public function render()
    {
        $config = $this->config();
        $ids = collect($config['slides'])->flatMap(fn ($s) => [$s['background']['image']['media_id'], $s['background']['video']['media_id']])->filter();
        $installer = app(FontInstaller::class);

        $boot = [
            'config' => $config,
            'media' => Media::whereIn('id', $ids)->get()->mapWithKeys(fn (Media $m) => [$m->id => $this->describe($m)]),
            'placeholderBase' => asset('placeholders'),
            'storageBase' => Storage::disk('public')->url(''),
            'fonts' => BannerFonts::catalogue(),
            'fontCss' => collect(BannerFonts::FAMILIES)->keys()->mapWithKeys(fn ($k) => [$k => $installer->stylesheetUrl($k)])->filter(),
            'isAdmin' => $this->user()->isAdmin(),
            'previewUrl' => route('studio.banner.preview'),
            'csrf' => csrf_token(),
            'limits' => ['slides' => BannerConfig::MAX_SLIDES, 'elements' => BannerConfig::MAX_ELEMENTS],
            'breakpointMin' => BannerConfig::BREAKPOINT_MIN,
        ];

        $revision = $this->revisionId ? Revision::find($this->revisionId) : null;

        return view('livewire.studio.banner.editor', ['boot' => $boot, 'revision' => $revision, 'isAdmin' => $boot['isAdmin']])
            ->title('Home banner')
            ->layoutData(['wide' => true]);
    }
}
