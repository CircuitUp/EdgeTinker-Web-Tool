<?php

namespace App\Livewire\Studio\Media;

use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Media;
use App\Studio\MediaLibrary;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Media library')]
class Library extends Component
{
    use StudioScreen, WithFileUploads, WithPagination;

    /** all | image | document */
    #[Url]
    public string $kind = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public $uploads = [];

    public function updating($name): void
    {
        if (in_array($name, ['kind', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function updatedUploads(MediaLibrary $library): void
    {
        $this->resetErrorBag();
        $stored = 0;

        foreach ($this->uploads as $index => $upload) {
            // Decide the kind from the extension, then apply that kind's rules.
            $extension = strtolower($upload->getClientOriginalExtension());
            $kind = MediaLibrary::kindFor($extension);

            $validator = validator(['file' => $upload], ['file' => MediaLibrary::rules($kind)], [
                'file.max' => ':name is too large (images up to 8 MB, documents and videos up to 30 MB).',
            ]);

            if ($validator->fails()) {
                $this->addError('uploads', $upload->getClientOriginalName().': this file type or size is not allowed.');

                continue;
            }

            try {
                $library->store($upload, $kind, $this->user());
                $stored++;
            } catch (ValidationException $e) {
                $this->addError('uploads', collect($e->errors())->flatten()->first());
            }
        }

        $this->uploads = [];

        if ($stored > 0) {
            $this->toast('success', "$stored ".($stored === 1 ? 'file' : 'files').' uploaded.');
        }
    }

    public function delete(int $mediaId, MediaLibrary $library): void
    {
        $media = Media::findOrFail($mediaId);

        // Creators may remove their own uploads; the admin may remove any.
        abort_unless($this->user()->isAdmin() || $media->uploaded_by === $this->user()->id, 403);

        if ($this->attempt(fn () => $library->delete($media, $this->user()))) {
            $this->toast('success', 'File deleted.');
        }
    }

    public function render()
    {
        $files = Media::query()
            ->with('uploader:id,name')
            ->when($this->kind !== 'all', fn ($query) => $query->where('kind', $this->kind))
            ->when($this->search !== '', fn ($query) => $query->where('original_name', 'like', '%'.addcslashes($this->search, '\\%_').'%'))
            ->latest()
            ->paginate(24);

        return view('livewire.studio.media.library', [
            'files' => $files,
            'userId' => $this->user()->id,
            'isAdmin' => $this->user()->isAdmin(),
        ]);
    }
}
