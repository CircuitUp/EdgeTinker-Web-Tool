<?php

namespace App\Livewire\Studio\Pages;

use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Media;
use App\Models\Product;
use App\Models\Revision;
use App\Studio\ContentTypes\ContentTypeRegistry;
use App\Studio\ContentTypes\PageType;
use App\Studio\MediaLibrary;
use App\Studio\RevisionService;
use App\Support\PageContent;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Edits one page (Home, Services or Contact) through structured fields.
 * The form is generated from PageContent::schema(), so adding a field to a
 * page means adding it to the schema and to the page template: no new form code.
 */
class Form extends Component
{
    use StudioScreen, WithFileUploads;

    #[Locked]
    public string $slug = '';

    #[Locked]
    public int $entityId;

    #[Locked]
    public ?int $revisionId = null;

    /** seo_title, seo_description and content (one entry per schema field). */
    public array $form = [];

    /** Pending image uploads, keyed like the field they belong to: uploads.partners.0.logo */
    public array $uploads = [];

    public string $productToAdd = '';

    public function mount(ContentTypeRegistry $types, ?string $page = null, ?int $revision = null): void
    {
        $user = $this->user();
        $type = $types->get('page');

        if ($revision !== null) {
            $record = Revision::where('entity_type', 'page')->findOrFail($revision);
            abort_unless($user->isAdmin() || $record->author_id === $user->id, 403);
            abort_unless($record->isEditable(), 404);

            $this->revisionId = $record->id;
            $this->entityId = $record->entity_id;
            $payload = $record->payload;
        } else {
            $this->entityId = PageType::idFor((string) $page); // 404 for an unknown page

            $mine = Revision::query()->forEntity('page', $this->entityId)->pending()
                ->where('author_id', $user->id)->get()->first(fn (Revision $r) => $r->isEditable());
            if ($mine) {
                $this->redirectRoute('studio.pages.revision', $mine->id);

                return;
            }

            $payload = $type->current($this->entityId);
        }

        $this->slug = $payload['slug'];
        $this->form = [
            'seo_title' => $payload['seo_title'] ?? '',
            'seo_description' => $payload['seo_description'] ?? '',
            'content' => array_replace(PageContent::defaults($this->slug), $payload['content'] ?? []),
        ];

        // "One per line" fields are edited as text and turned back into lists when saved.
        foreach (PageContent::schema($this->slug) as $field) {
            foreach ($field['fields'] ?? [] as $sub) {
                if ($sub['type'] === 'lines') {
                    foreach ($this->form['content'][$field['key']] as $i => $row) {
                        $this->form['content'][$field['key']][$i][$sub['key']] = implode("\n", (array) ($row[$sub['key']] ?? []));
                    }
                }
            }
        }
    }

    private function field(string $key): array
    {
        return collect(PageContent::schema($this->slug))->firstWhere('key', $key) ?? abort(404);
    }

    // ── Repeating rows ───────────────────────────────────────────────────────

    public function addRow(string $key): void
    {
        $field = $this->field($key);
        abort_unless($field['type'] === 'repeater', 404);

        if (count($this->form['content'][$key]) >= ($field['max_rows'] ?? 20)) {
            $this->toast('warning', 'This section already has the maximum number of rows.');

            return;
        }

        $this->form['content'][$key][] = collect($field['fields'])->mapWithKeys(fn ($sub) => [$sub['key'] => match ($sub['type']) {
            'image' => ['media_id' => null, 'path' => null],
            'icon' => PageContent::ICONS[0],
            default => '',
        }])->all();
    }

    public function removeRow(string $key, int $index): void
    {
        $this->field($key);
        unset($this->form['content'][$key][$index]);
        $this->form['content'][$key] = array_values($this->form['content'][$key]);
    }

    public function moveRow(string $key, int $from, int $to): void
    {
        $this->field($key);
        $rows = array_values($this->form['content'][$key]);

        if (isset($rows[$from]) && $to >= 0 && $to < count($rows)) {
            $row = array_splice($rows, $from, 1);
            array_splice($rows, $to, 0, $row);
            $this->form['content'][$key] = $rows;
        }
    }

    // ── Images ───────────────────────────────────────────────────────────────

    /** Fires when a file is chosen. $name is the field path, e.g. "partners.0.logo". */
    public function updatedUploads($value, string $name): void
    {
        $library = app(MediaLibrary::class);
        $upload = data_get($this->uploads, $name);

        // Only paths that are image fields of this page's schema are accepted.
        [$key, $index, $sub] = array_pad(explode('.', $name), 3, null);
        $field = $this->field((string) $key);
        abort_unless(collect($field['fields'] ?? [])->contains(fn ($f) => $f['key'] === $sub && $f['type'] === 'image') && isset($this->form['content'][$key][(int) $index]), 404);

        $validator = validator(['file' => $upload], ['file' => MediaLibrary::rules(Media::KIND_IMAGE)]);
        if ($validator->fails()) {
            $this->addError("uploads.$name", 'The image must be a JPG, PNG, WebP or GIF file of 8 MB or less.');
        } else {
            try {
                $media = $library->store($upload, Media::KIND_IMAGE, $this->user());
                $this->form['content'][$key][(int) $index][$sub] = ['media_id' => $media->id, 'path' => null];
            } catch (ValidationException $e) {
                $this->addError("uploads.$name", collect($e->errors())->flatten()->first());
            }
        }

        $this->uploads = [];
    }

    public function removeImage(string $key, int $index, string $sub): void
    {
        $this->field($key);
        if (isset($this->form['content'][$key][$index][$sub])) {
            $this->form['content'][$key][$index][$sub] = ['media_id' => null, 'path' => null];
        }
    }

    // ── Featured products ────────────────────────────────────────────────────

    public function updatedProductToAdd(string $value): void
    {
        $key = 'featured_product_ids';
        if ($value !== '' && isset($this->form['content'][$key]) && Product::whereKey((int) $value)->exists()
            && ! in_array((int) $value, $this->form['content'][$key], true)) {
            $this->form['content'][$key][] = (int) $value;
        }
        $this->productToAdd = '';
    }

    public function removeProduct(string $key, int $index): void
    {
        $this->field($key);
        unset($this->form['content'][$key][$index]);
        $this->form['content'][$key] = array_values($this->form['content'][$key]);
    }

    // ── Saving ───────────────────────────────────────────────────────────────

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
            $revision = $revisions->save($user, 'page', $this->entityId, ['slug' => $this->slug] + $this->form, $submit,
                $this->revisionId ? Revision::find($this->revisionId) : null);
        } catch (ValidationException $e) {
            $this->showFormErrors($e);

            return null;
        }

        if (! $submit) {
            $this->flashToast('success', 'Draft saved. It is not visible on the live site.');

            return $this->redirectRoute('studio.pages.revision', $revision->id);
        }

        $this->flashToast('success', $user->isAdmin() ? "The {$revision->title} is now live." : "Your changes to the {$revision->title} were submitted for review.");

        return $this->redirectRoute('studio.pages.index');
    }

    public function render()
    {
        $content = $this->form['content'] ?? [];
        $mediaIds = collect($content)->flatten(1)->filter(fn ($row) => is_array($row))
            ->flatMap(fn ($row) => collect($row)->filter(fn ($v) => is_array($v) && isset($v['media_id']))->pluck('media_id'))->filter();

        return view('livewire.studio.pages.form', [
            'title' => PageContent::TITLES[$this->slug] ?? 'Page',
            'groups' => collect(PageContent::schema($this->slug))->groupBy(fn ($field) => $field['group'] ?? 'Page'),
            'media' => Media::whereIn('id', $mediaIds)->get()->keyBy('id'),
            'products' => isset($content['featured_product_ids']) ? Product::orderBy('name')->get(['id', 'name', 'sku']) : collect(),
            'revision' => $this->revisionId ? Revision::find($this->revisionId) : null,
            'isAdmin' => $this->user()->isAdmin(),
        ])->title('Edit '.(PageContent::TITLES[$this->slug] ?? 'page'));
    }
}
