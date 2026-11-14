<?php

namespace App\Studio\ContentTypes;

use App\Models\BannerSlide;
use App\Models\Media;
use App\Models\Page;
use App\Models\Product;
use App\Support\HomePage;
use App\Support\PageContent;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Home, Services and Contact pages. Pages are fixed (they cannot be
 * created or deleted); their content is a set of structured fields defined
 * in PageContent::schema(), which also drives validation here.
 *
 * Payload: slug, seo_title, seo_description, content: {field => value}
 */
class PageType extends ContentType
{
    public function key(): string
    {
        return 'page';
    }

    public function label(): string
    {
        return 'Page';
    }

    /** The id of the row for a page, creating it on first use. */
    public static function idFor(string $slug): int
    {
        abort_unless(in_array($slug, Page::SLUGS, true), 404);

        return Page::firstOrCreate(['slug' => $slug])->id;
    }

    // ── Normalising and validating against the schema ────────────────────────

    public function normalise(array $payload, ?int $entityId): array
    {
        $slug = $entityId ? Page::whereKey($entityId)->value('slug') : ($payload['slug'] ?? null);
        $content = [];

        foreach (PageContent::schema((string) $slug) as $field) {
            $content[$field['key']] = $this->clean($field, $payload['content'][$field['key']] ?? null);
        }

        return [
            'slug' => $slug,
            'seo_title' => $this->nullableText($payload['seo_title'] ?? null),
            'seo_description' => $this->nullableText($payload['seo_description'] ?? null, 500),
            'content' => $content,
        ];
    }

    private function clean(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'textarea' => is_scalar($value) ? mb_substr(trim(preg_replace("/\r\n?/", "\n", (string) $value)), 0, 5000) : '',
            'lines' => collect(is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value))
                ->map(fn ($line) => $this->text($line, 500))->filter()->values()->all(),
            'image' => [
                'media_id' => is_numeric($value['media_id'] ?? null) ? (int) $value['media_id'] : null,
                'path' => is_numeric($value['media_id'] ?? null) ? null : $this->nullableText($value['path'] ?? null),
            ],
            'products' => collect((array) $value)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'repeater' => collect((array) $value)->filter(fn ($row) => is_array($row))
                ->map(fn (array $row) => collect($field['fields'])->mapWithKeys(fn ($sub) => [$sub['key'] => $this->clean($sub, $row[$sub['key']] ?? null)])->all())
                // Drop rows left completely empty.
                ->filter(fn (array $row) => collect($row)->contains(fn ($v) => is_array($v) ? ! empty(array_filter($v)) : $v !== ''))
                ->values()->all(),
            default => $this->text($value, 1000), // text, link, icon
        };
    }

    public function rules(array $payload, ?int $entityId): array
    {
        $rules = [
            'slug' => ['required', Rule::in(Page::SLUGS)],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ];

        foreach (PageContent::schema((string) ($payload['slug'] ?? '')) as $field) {
            $rules += $this->fieldRules($field, 'content.'.$field['key']);
        }

        return $rules;
    }

    private function fieldRules(array $field, string $path): array
    {
        $presence = ($field['required'] ?? false) ? 'required' : 'nullable';
        $max = 'max:'.($field['max'] ?? 1000);

        return match ($field['type']) {
            'text', 'textarea' => [$path => [$presence, 'string', $max]],
            // Internal path ("/products") or a full https address. Blocks javascript: and protocol-relative links.
            'link' => [$path => [$presence, 'string', $max, 'regex:#^(/(?!/)[^\s]*|https://[^\s]+)$#']],
            'icon' => [$path => [$presence, Rule::in(PageContent::ICONS)]],
            'lines' => [$path => ['array', 'max:'.($field['max_rows'] ?? 20)], "$path.*" => ['string', $max]],
            'image' => [
                "$path.media_id" => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', Media::KIND_IMAGE)],
                "$path.path" => ['nullable', 'string', 'starts_with:placeholders/', 'not_regex:/\.\./'],
            ],
            'products' => [$path => ['array', 'max:'.($field['max_rows'] ?? 12)], "$path.*" => ['integer', Rule::exists('products', 'id')]],
            'repeater' => array_merge(
                [$path => ['array', 'min:'.($field['min_rows'] ?? 0), 'max:'.($field['max_rows'] ?? 20)]],
                ...array_map(fn ($sub) => $this->fieldRules($sub, "$path.*.{$sub['key']}"), $field['fields']),
            ),
        };
    }

    public function validationMessages(): array
    {
        return [
            'content.*.*.button_url.regex' => 'The button link must be a page on this site (starting with /) or a full https:// address.',
            'content.slides.min' => 'The slideshow needs at least one slide.',
            'required' => 'This field is required.',
        ];
    }

    public function validationAttributes(): array
    {
        $names = ['seo_title' => 'SEO title', 'seo_description' => 'SEO description'];
        foreach (Page::SLUGS as $slug) {
            foreach (PageContent::schema($slug) as $field) {
                $names['content.'.$field['key']] = strtolower($field['label']);
                foreach ($field['fields'] ?? [] as $sub) {
                    $names["content.{$field['key']}.*.{$sub['key']}"] = strtolower($sub['label']);
                }
            }
        }

        return $names;
    }

    // ── Reading and writing ──────────────────────────────────────────────────

    public function title(array $payload): string
    {
        return (PageContent::TITLES[$payload['slug'] ?? ''] ?? 'Unknown').' page';
    }

    public function current(int $entityId): ?array
    {
        $page = Page::find($entityId);

        if (! $page) {
            return null;
        }

        $content = array_replace(PageContent::defaults($page->slug), array_intersect_key($page->content ?? [], PageContent::defaults($page->slug)));

        if ($page->slug === 'home') {
            // Slides and the featured flag are stored in their own tables; present them as page fields.
            $content['slides'] = BannerSlide::active()->get()->map(function (BannerSlide $slide) {
                $media = $slide->image_path ? Media::where('path', $slide->image_path)->first() : null;

                return [
                    'heading' => $slide->heading, 'sub_text' => (string) $slide->sub_text,
                    'button_label' => (string) $slide->button_label, 'button_url' => (string) $slide->button_url,
                    'image' => ['media_id' => $media?->id, 'path' => $media ? null : $slide->image_path],
                    'image_alt' => $slide->image_alt,
                ];
            })->all();

            $featured = Product::featured()->latest()->pluck('id')->all();
            $order = array_flip($content['featured_product_ids'] ?? []);
            usort($featured, fn ($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
            $content['featured_product_ids'] = $featured;
        }

        return ['slug' => $page->slug, 'seo_title' => $page->seo_title, 'seo_description' => $page->seo_description, 'content' => $content];
    }

    public function apply(array $payload, ?int $entityId): int
    {
        $page = Page::firstOrNew(['slug' => $payload['slug']]);
        $content = $payload['content'];

        if ($payload['slug'] === 'home') {
            $media = Media::whereIn('id', collect($content['slides'])->pluck('image.media_id')->filter())->get()->keyBy('id');

            BannerSlide::query()->delete();
            foreach ($content['slides'] as $order => $slide) {
                BannerSlide::create([
                    'heading' => $slide['heading'], 'sub_text' => $slide['sub_text'] ?: null,
                    'button_label' => $slide['button_label'] ?: null, 'button_url' => $slide['button_url'] ?: null,
                    'image_path' => $slide['image']['media_id'] ? ($media[$slide['image']['media_id']]->path ?? null) : $slide['image']['path'],
                    'image_alt' => $slide['image_alt'], 'sort_order' => $order + 1, 'is_active' => true,
                ]);
            }

            // The list on this form is the complete set of featured products.
            Product::query()->whereNotIn('id', $content['featured_product_ids'])->where('is_featured', true)->update(['is_featured' => false]);
            Product::query()->whereIn('id', $content['featured_product_ids'])->update(['is_featured' => true]);

            unset($content['slides']); // kept in banner_slides, not duplicated here
        }

        $page->fill(['content' => $content, 'seo_title' => $payload['seo_title'], 'seo_description' => $payload['seo_description']])->save();

        return $page->id;
    }

    public function remove(int $entityId): void
    {
        throw ValidationException::withMessages(['revision' => 'The Home, Services and Contact pages cannot be deleted.']);
    }

    // ── Presentation ─────────────────────────────────────────────────────────

    public function summary(array $payload): array
    {
        $summary = [];
        $content = $payload['content'] ?? [];

        foreach (PageContent::schema($payload['slug'] ?? '') as $field) {
            $label = ($field['group'] ?? 'Page').': '.$field['label'];
            $value = $content[$field['key']] ?? null;

            $summary[$label] = match ($field['type']) {
                'repeater' => collect((array) $value)->map(fn ($row, $i) => ($field['row_label'] ?? 'Row').' '.($i + 1).': '
                    .collect($field['fields'])->map(fn ($sub) => $this->describe($sub, $row[$sub['key']] ?? null))->filter()->implode(' | '))->all(),
                'products' => Product::whereIn('id', (array) $value)->get(['id', 'name'])
                    ->sortBy(fn ($product) => array_search($product->id, (array) $value))->pluck('name')->values()->all(),
                default => $this->describe($field, $value),
            };
        }

        return $summary + ['SEO title' => $payload['seo_title'] ?? '', 'SEO description' => $payload['seo_description'] ?? ''];
    }

    private function describe(array $field, mixed $value): string
    {
        return match ($field['type']) {
            'image' => ($value['media_id'] ?? null)
                ? 'image: '.(Media::find($value['media_id'])?->original_name ?? 'missing file')
                : (($value['path'] ?? null) ? 'image: '.basename($value['path']) : ''),
            'lines' => implode('; ', (array) $value),
            default => (string) $value,
        };
    }

    public function editUrl(?int $entityId, ?int $revisionId = null): string
    {
        return $revisionId
            ? route('studio.pages.revision', $revisionId)
            : route('studio.pages.edit', Page::whereKey($entityId)->value('slug') ?? 'home');
    }

    public function publicUrl(int $entityId): ?string
    {
        return match (Page::whereKey($entityId)->value('slug')) {
            'home' => route('home'),
            'services' => route('services'),
            'contact' => route('contact'),
            default => null,
        };
    }

    public function hasPreview(): bool
    {
        return true;
    }

    public function preview(array $payload, ?int $entityId): ?Renderable
    {
        $payload = $this->normalise($payload, $entityId);
        $content = array_replace(PageContent::defaults($payload['slug']), $payload['content']);
        $seo = ['title' => $payload['seo_title'], 'description' => $payload['seo_description']];

        return match ($payload['slug']) {
            'home' => view('home', ['isPreview' => true] + HomePage::data($payload['content'])),
            'services' => view('services', ['content' => $content, 'seo' => $seo, 'isPreview' => true]),
            'contact' => view('contact', ['content' => $content, 'seo' => $seo, 'isPreview' => true]),
            default => null,
        };
    }
}
