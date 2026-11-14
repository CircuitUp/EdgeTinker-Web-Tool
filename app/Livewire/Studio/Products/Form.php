<?php

namespace App\Livewire\Studio\Products;

use App\Enums\StockStatus;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\Revision;
use App\Models\Tag;
use App\Studio\ContentTypes\ContentTypeRegistry;
use App\Studio\MediaLibrary;
use App\Studio\RevisionService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The one product form: add a product, edit a live product, or continue a
 * pending change. Saving never touches the live site directly: it goes
 * through RevisionService (a draft or submission for creators; published
 * immediately for the Content Admin).
 */
class Form extends Component
{
    use StudioScreen, WithFileUploads;

    /** The product payload being edited (shape: see ProductType). */
    public array $form = [];

    #[Locked]
    public ?int $entityId = null;

    #[Locked]
    public ?int $revisionId = null;

    /** True once the creator has typed their own web address, so we stop deriving it from the name. */
    public bool $slugEdited = false;

    public $imageUploads = [];

    public $documentUpload = null;

    public string $newTag = '';

    public bool $showLibrary = false;

    public function mount(ContentTypeRegistry $types, ?int $product = null, ?int $revision = null): void
    {
        $type = $types->get('product');
        $user = $this->user();

        if ($revision !== null) {
            $record = Revision::where('entity_type', 'product')->findOrFail($revision);
            abort_unless($user->isAdmin() || $record->author_id === $user->id, 403);
            abort_unless($record->isEditable(), 404);

            $this->revisionId = $record->id;
            $this->entityId = $record->entity_id;
            $this->form = $record->payload ?? [];
        } elseif ($product !== null) {
            // Continue your own open change rather than starting a second one.
            $mine = Revision::query()->forEntity('product', $product)->pending()
                ->where('author_id', $user->id)->get()->first(fn (Revision $r) => $r->isEditable());
            if ($mine) {
                $this->redirectRoute('studio.products.revision', $mine->id);

                return;
            }

            $this->entityId = $product;
            $this->form = $type->current($product) ?? abort(404);
        }

        // Defaults for anything missing (a new product, or an older draft).
        $this->form += [
            'name' => '', 'slug' => '', 'sku' => '', 'category_id' => null, 'short_description' => '', 'description' => '',
            'key_features' => [''], 'specs' => [['label' => '', 'value' => '']], 'price_usd' => null, 'price_lkr' => null,
            'stock_status' => StockStatus::InStock->value, 'is_featured' => false, 'images' => [], 'documents' => [],
            'tag_ids' => [], 'new_tags' => [], 'seo_title' => '', 'seo_description' => '',
        ];

        $this->slugEdited = $this->entityId !== null || $this->form['slug'] !== '';

        // For a new product the web address and SEO fields fill themselves in; show them empty until then.
        if ($this->entityId === null && $this->revisionId === null) {
            $this->form['seo_title'] = '';
            $this->form['seo_description'] = '';
        }
    }

    // ── Web address ──────────────────────────────────────────────────────────

    public function updatedFormName(string $value): void
    {
        if (! $this->slugEdited) {
            $this->form['slug'] = Str::slug($value);
        }
    }

    public function updatedFormSlug(string $value): void
    {
        $this->slugEdited = trim($value) !== '';
        $this->form['slug'] = Str::slug($value);
    }

    // ── Key features and specifications ──────────────────────────────────────

    public function addFeature(): void
    {
        $this->form['key_features'][] = '';
    }

    public function removeFeature(int $index): void
    {
        unset($this->form['key_features'][$index]);
        $this->form['key_features'] = array_values($this->form['key_features']);
    }

    public function addSpec(): void
    {
        $this->form['specs'][] = ['label' => '', 'value' => ''];
    }

    public function removeSpec(int $index): void
    {
        unset($this->form['specs'][$index]);
        $this->form['specs'] = array_values($this->form['specs']);
    }

    public function moveSpec(int $from, int $to): void
    {
        $this->form['specs'] = $this->moved($this->form['specs'], $from, $to);
    }

    // ── Images ───────────────────────────────────────────────────────────────

    public function updatedImageUploads(MediaLibrary $library): void
    {
        $this->validate(
            ['imageUploads' => ['array', 'max:12'], 'imageUploads.*' => MediaLibrary::rules(Media::KIND_IMAGE)],
            ['imageUploads.*.mimes' => 'Images must be JPG, PNG, WebP or GIF files.', 'imageUploads.*.max' => 'Each image must be 8 MB or smaller.'],
        );

        foreach ($this->imageUploads as $upload) {
            try {
                $this->appendImage($library->store($upload, Media::KIND_IMAGE, $this->user()));
            } catch (ValidationException $e) {
                $this->addError('imageUploads', collect($e->errors())->flatten()->first());
            }
        }

        $this->imageUploads = [];
    }

    public function addFromLibrary(int $mediaId): void
    {
        $media = Media::where('kind', Media::KIND_IMAGE)->findOrFail($mediaId);

        if (! collect($this->form['images'])->contains('media_id', $media->id)) {
            $this->appendImage($media);
        }
        $this->showLibrary = false;
    }

    public function removeImage(int $index): void
    {
        $wasThumbnail = $this->form['images'][$index]['is_thumbnail'] ?? false;
        unset($this->form['images'][$index]);
        $this->form['images'] = array_values($this->form['images']);

        if ($wasThumbnail && $this->form['images'] !== []) {
            $this->form['images'][0]['is_thumbnail'] = true;
        }
    }

    public function setThumbnail(int $index): void
    {
        foreach ($this->form['images'] as $i => $image) {
            $this->form['images'][$i]['is_thumbnail'] = $i === $index;
        }
    }

    public function moveImage(int $from, int $to): void
    {
        $this->form['images'] = $this->moved($this->form['images'], $from, $to);
    }

    private function appendImage(Media $media): void
    {
        if (count($this->form['images']) >= 12) {
            $this->toast('warning', 'A product can have up to 12 images.');

            return;
        }

        $this->form['images'][] = [
            'media_id' => $media->id,
            'path' => null,
            'alt' => $this->form['name'] ?: pathinfo($media->original_name, PATHINFO_FILENAME),
            'is_thumbnail' => $this->form['images'] === [],
        ];
    }

    // ── Documents ────────────────────────────────────────────────────────────

    public function updatedDocumentUpload(MediaLibrary $library): void
    {
        $this->validate(
            ['documentUpload' => MediaLibrary::rules(Media::KIND_DOCUMENT)],
            ['documentUpload.extensions' => 'Allowed document types: '.implode(', ', MediaLibrary::DOCUMENT_EXTENSIONS).'.',
                'documentUpload.max' => 'Documents must be 30 MB or smaller.', 'documentUpload.mimetypes' => 'This file does not look like one of the allowed document types.'],
        );

        $media = $library->store($this->documentUpload, Media::KIND_DOCUMENT, $this->user());
        $name = pathinfo($media->original_name, PATHINFO_FILENAME);

        $this->form['documents'][] = [
            'media_id' => $media->id,
            'title' => Str::headline($name),
            // Guess the type from the file name; the creator can change it.
            'type' => match (true) {
                Str::contains(Str::lower($name), 'datasheet') => 'datasheet',
                Str::contains(Str::lower($name), ['schematic', 'sch']) => 'schematic',
                Str::contains(Str::lower($name), 'pinout') => 'pinout',
                in_array(Str::lower(pathinfo($media->original_name, PATHINFO_EXTENSION)), ['step', 'stp', 'stl'], true) => 'model_3d',
                in_array(Str::lower(pathinfo($media->original_name, PATHINFO_EXTENSION)), ['bin', 'hex', 'uf2'], true) => 'firmware',
                default => 'other',
            },
        ];

        $this->documentUpload = null;
    }

    public function removeDocument(int $index): void
    {
        unset($this->form['documents'][$index]);
        $this->form['documents'] = array_values($this->form['documents']);
    }

    // ── Tags ─────────────────────────────────────────────────────────────────

    public function toggleTag(int $tagId): void
    {
        $ids = collect($this->form['tag_ids']);
        $this->form['tag_ids'] = ($ids->contains($tagId) ? $ids->reject(fn ($id) => $id === $tagId) : $ids->push($tagId))->values()->all();
    }

    /** "Create tag" shortcut: selects an existing tag of that name, or queues a new one to be created on approval. */
    public function addNewTag(): void
    {
        $name = trim(preg_replace('/\s+/', ' ', $this->newTag));
        $slug = Str::slug($name);
        $this->newTag = '';

        if ($slug === '') {
            return;
        }

        if ($existing = Tag::where('slug', $slug)->first()) {
            if (! in_array($existing->id, $this->form['tag_ids'], true)) {
                $this->form['tag_ids'][] = $existing->id;
            }

            return;
        }

        if (! collect($this->form['new_tags'])->contains(fn ($tag) => Str::slug($tag) === $slug)) {
            $this->form['new_tags'][] = mb_substr($name, 0, 60);
        }
    }

    public function removeNewTag(int $index): void
    {
        unset($this->form['new_tags'][$index]);
        $this->form['new_tags'] = array_values($this->form['new_tags']);
    }

    // ── Saving ───────────────────────────────────────────────────────────────

    public function saveDraft(RevisionService $revisions)
    {
        return $this->persist($revisions, submit: false);
    }

    public function submit(RevisionService $revisions)
    {
        return $this->persist($revisions, submit: true);
    }

    private function persist(RevisionService $revisions, bool $submit)
    {
        $this->resetErrorBag();
        $user = $this->user();
        $existing = $this->revisionId ? Revision::find($this->revisionId) : null;

        try {
            $revision = $revisions->save($user, 'product', $this->entityId, $this->form, $submit, $existing);
        } catch (ValidationException $e) {
            $this->showFormErrors($e);

            return null;
        }

        if (! $submit) {
            $this->flashToast('success', 'Draft saved. It is not visible on the live site.');

            return $this->redirectRoute('studio.products.revision', $revision->id);
        }

        $this->flashToast('success', $user->isAdmin()
            ? "“{$revision->title}” is now live."
            : "“{$revision->title}” was submitted. The Content Admin will review it.");

        return $this->redirectRoute('studio.products.index');
    }

    private function moved(array $items, int $from, int $to): array
    {
        $items = array_values($items);
        if (! isset($items[$from]) || $to < 0 || $to >= count($items)) {
            return $items;
        }
        $item = array_splice($items, $from, 1);
        array_splice($items, $to, 0, $item);

        return $items;
    }

    public function render(RevisionService $revisions)
    {
        $mediaIds = collect($this->form['images'] ?? [])->pluck('media_id')
            ->merge(collect($this->form['documents'] ?? [])->pluck('media_id'))->filter();
        $revision = $this->revisionId ? Revision::find($this->revisionId) : null;

        return view('livewire.studio.products.form', [
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
            'media' => Media::whereIn('id', $mediaIds)->get()->keyBy('id'),
            'library' => $this->showLibrary ? Media::where('kind', Media::KIND_IMAGE)->latest()->limit(48)->get() : collect(),
            'documentTypes' => ProductDocument::TYPES,
            'stockStatuses' => StockStatus::cases(),
            'revision' => $revision,
            'liveName' => $this->entityId ? Product::whereKey($this->entityId)->value('name') : null,
            // Someone else's open changes to the same product (so two people do not overwrite each other unknowingly).
            'otherPending' => $this->entityId
                ? $revisions->pendingFor('product', $this->entityId)->reject(fn (Revision $r) => $r->id === $this->revisionId)
                : collect(),
            'isAdmin' => $this->user()->isAdmin(),
        ])->title($this->entityId ? 'Edit product' : 'Add a product');
    }
}
