<?php

namespace App\Livewire\Studio\Wiki;

use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Media;
use App\Models\Product;
use App\Models\Revision;
use App\Models\WikiPage;
use App\Studio\ContentTypes\ContentTypeRegistry;
use App\Studio\MediaLibrary;
use App\Studio\RevisionService;
use App\Support\WikiMarkdown;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Markdown editor for one wiki page, with a live preview beside it. */
class Form extends Component
{
    use StudioScreen, WithFileUploads;

    public array $form = [];

    #[Locked]
    public ?int $entityId = null;

    #[Locked]
    public ?int $revisionId = null;

    public bool $slugEdited = false;

    public $attachment = null;

    public function mount(ContentTypeRegistry $types, ?int $page = null, ?int $revision = null): void
    {
        $user = $this->user();

        if ($revision !== null) {
            $record = Revision::where('entity_type', 'wiki_page')->findOrFail($revision);
            abort_unless($user->isAdmin() || $record->author_id === $user->id, 403);
            abort_unless($record->isEditable(), 404);

            $this->revisionId = $record->id;
            $this->entityId = $record->entity_id;
            $this->form = $record->payload ?? [];
        } elseif ($page !== null) {
            $mine = Revision::query()->forEntity('wiki_page', $page)->pending()
                ->where('author_id', $user->id)->get()->first(fn (Revision $r) => $r->isEditable());
            if ($mine) {
                $this->redirectRoute('studio.wiki.revision', $mine->id);

                return;
            }

            $this->entityId = $page;
            $this->form = $types->get('wiki_page')->current($page) ?? abort(404);
        } else {
            // New page: /wiki/new?product=12 preselects the product.
            $productId = Product::whereKey((int) request()->query('product'))->value('id');
            $this->form = [
                'product_id' => $productId,
                'sort_order' => $productId ? (int) WikiPage::where('product_id', $productId)->max('sort_order') + 1 : 1,
            ];
        }

        $this->form += ['product_id' => null, 'title' => '', 'slug' => '', 'body' => '', 'sort_order' => 1];
        $this->slugEdited = $this->entityId !== null || $this->form['slug'] !== '';
    }

    public function updatedFormTitle(string $value): void
    {
        if (! $this->slugEdited) {
            $this->form['slug'] = Str::slug(str_replace('&', 'and', $value));
        }
    }

    public function updatedFormSlug(string $value): void
    {
        $this->slugEdited = trim($value) !== '';
        $this->form['slug'] = Str::slug($value);
    }

    /** Uploads an image or document and inserts the Markdown for it at the cursor. */
    public function updatedAttachment(MediaLibrary $library): void
    {
        $extension = strtolower($this->attachment->getClientOriginalExtension());
        $kind = in_array($extension, MediaLibrary::IMAGE_EXTENSIONS, true) ? Media::KIND_IMAGE : Media::KIND_DOCUMENT;

        $validator = validator(['file' => $this->attachment], ['file' => MediaLibrary::rules($kind)]);
        if ($validator->fails()) {
            $this->addError('attachment', 'This file type or size is not allowed. Images up to 8 MB; documents (PDF, ZIP, STEP, STL, BIN, HEX, UF2, TXT, CSV) up to 30 MB.');
            $this->attachment = null;

            return;
        }

        try {
            $media = $library->store($this->attachment, $kind, $this->user());
        } catch (ValidationException $e) {
            $this->addError('attachment', collect($e->errors())->flatten()->first());
            $this->attachment = null;

            return;
        }

        $label = str_replace(['[', ']'], '', pathinfo($media->original_name, PATHINFO_FILENAME));
        // Site-relative address, so the link keeps working if the domain changes.
        $path = parse_url($media->url, PHP_URL_PATH);
        $markdown = $media->isImage() ? "![$label]($path)" : "[$label]($path)";

        $this->dispatch('insert-markdown', text: $markdown);
        $this->attachment = null;
    }

    public function saveDraft(RevisionService $revisions)
    {
        return $this->persist($revisions, false);
    }

    public function submit(RevisionService $revisions)
    {
        return $this->persist($revisions, true);
    }

    private function persist(RevisionService $revisions, bool $submit)
    {
        $this->resetErrorBag();
        $user = $this->user();

        try {
            $revision = $revisions->save($user, 'wiki_page', $this->entityId, $this->form, $submit,
                $this->revisionId ? Revision::find($this->revisionId) : null);
        } catch (ValidationException $e) {
            $this->showFormErrors($e);

            return null;
        }

        if (! $submit) {
            $this->flashToast('success', 'Draft saved. It is not visible on the live site.');

            return $this->redirectRoute('studio.wiki.revision', $revision->id);
        }

        $this->flashToast('success', $user->isAdmin() ? "“{$revision->title}” is now live." : "“{$revision->title}” was submitted for review.");

        return $this->redirect(route('studio.wiki.index', ['product' => $revision->payload['product_id']]));
    }

    public function render()
    {
        $revision = $this->revisionId ? Revision::find($this->revisionId) : null;

        return view('livewire.studio.wiki.form', [
            'products' => Product::with('category:id,name')->orderBy('name')->get(['id', 'name', 'category_id']),
            'rendered' => WikiMarkdown::render($this->form['body'] ?? ''),
            'revision' => $revision,
            'isAdmin' => $this->user()->isAdmin(),
        ])->title($this->entityId ? 'Edit wiki page' : 'Add a wiki page');
    }
}
