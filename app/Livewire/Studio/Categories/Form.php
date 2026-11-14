<?php

namespace App\Livewire\Studio\Categories;

use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Category;
use App\Models\Media;
use App\Models\Revision;
use App\Studio\ContentTypes\ContentTypeRegistry;
use App\Studio\MediaLibrary;
use App\Studio\RevisionService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use StudioScreen, WithFileUploads;

    public array $form = [];

    #[Locked]
    public ?int $entityId = null;

    #[Locked]
    public ?int $revisionId = null;

    public $imageUpload = null;

    public function mount(ContentTypeRegistry $types, ?int $category = null, ?int $revision = null): void
    {
        $user = $this->user();

        if ($revision !== null) {
            $record = Revision::where('entity_type', 'category')->findOrFail($revision);
            abort_unless($user->isAdmin() || $record->author_id === $user->id, 403);
            abort_unless($record->isEditable(), 404);

            $this->revisionId = $record->id;
            $this->entityId = $record->entity_id;
            $this->form = $record->payload ?? [];
        } elseif ($category !== null) {
            $mine = Revision::query()->forEntity('category', $category)->pending()
                ->where('author_id', $user->id)->get()->first(fn (Revision $r) => $r->isEditable());
            if ($mine) {
                $this->redirectRoute('studio.categories.revision', $mine->id);

                return;
            }

            $this->entityId = $category;
            $this->form = $types->get('category')->current($category) ?? abort(404);
        }

        $this->form += [
            'name' => '', 'slug' => '', 'tagline' => '', 'image' => ['media_id' => null, 'path' => null],
            'sort_order' => (int) Category::max('sort_order') + 1,
        ];
    }

    public function updatedImageUpload(MediaLibrary $library): void
    {
        $this->validate(['imageUpload' => MediaLibrary::rules(Media::KIND_IMAGE)], [
            'imageUpload.mimes' => 'The image must be a JPG, PNG, WebP or GIF file.',
            'imageUpload.max' => 'The image must be 8 MB or smaller.',
        ]);

        try {
            $media = $library->store($this->imageUpload, Media::KIND_IMAGE, $this->user());
            $this->form['image'] = ['media_id' => $media->id, 'path' => null];
        } catch (ValidationException $e) {
            $this->addError('imageUpload', collect($e->errors())->flatten()->first());
        }
        $this->imageUpload = null;
    }

    public function removeImage(): void
    {
        $this->form['image'] = ['media_id' => null, 'path' => null];
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
            $revision = $revisions->save($user, 'category', $this->entityId, $this->form, $submit,
                $this->revisionId ? Revision::find($this->revisionId) : null);
        } catch (ValidationException $e) {
            $this->showFormErrors($e);

            return null;
        }

        if (! $submit) {
            $this->flashToast('success', 'Draft saved. It is not visible on the live site.');

            return $this->redirectRoute('studio.categories.revision', $revision->id);
        }

        $this->flashToast('success', $user->isAdmin() ? "“{$revision->title}” is now live." : "“{$revision->title}” was submitted for review.");

        return $this->redirectRoute('studio.categories.index');
    }

    public function render()
    {
        $image = $this->form['image'] ?? [];

        return view('livewire.studio.categories.form', [
            'imageUrl' => ($image['media_id'] ?? null) ? Media::find($image['media_id'])?->variantUrl(800) : media_url($image['path'] ?? null),
            'revision' => $this->revisionId ? Revision::find($this->revisionId) : null,
            'isAdmin' => $this->user()->isAdmin(),
        ])->title($this->entityId ? 'Edit category' : 'Add a category');
    }
}
