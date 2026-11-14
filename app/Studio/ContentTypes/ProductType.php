<?php

namespace App\Studio\ContentTypes;

use App\Enums\StockStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use App\Models\ProductSpec;
use App\Models\Tag;
use App\Support\RichText;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Payload shape:
 *   name, slug, sku, category_id, short_description, description (HTML),
 *   key_features: string[], specs: [{label, value}],
 *   price_usd, price_lkr, stock_status, store_url (external store page), is_featured,
 *   images: [{media_id|null, path|null, alt, is_thumbnail}],
 *   documents: [{media_id, title, type}],
 *   tag_ids: int[], new_tags: string[] (created when the product is approved),
 *   seo_title, seo_description
 */
class ProductType extends ContentType
{
    public function key(): string
    {
        return 'product';
    }

    public function label(): string
    {
        return 'Product';
    }

    public function normalise(array $payload, ?int $entityId): array
    {
        $name = $this->text($payload['name'] ?? '');
        $short = $this->text($payload['short_description'] ?? '', 500);

        $images = [];
        foreach (array_values((array) ($payload['images'] ?? [])) as $image) {
            if (! is_array($image)) {
                continue;
            }
            $images[] = [
                'media_id' => isset($image['media_id']) && is_numeric($image['media_id']) ? (int) $image['media_id'] : null,
                'path' => isset($image['media_id']) && is_numeric($image['media_id']) ? null : $this->nullableText($image['path'] ?? null),
                'alt' => $this->text($image['alt'] ?? ''),
                'is_thumbnail' => (bool) ($image['is_thumbnail'] ?? false),
            ];
        }
        // Exactly one thumbnail: the chosen one, otherwise the first image.
        $chosen = collect($images)->search(fn ($image) => $image['is_thumbnail']);
        foreach ($images as $i => &$image) {
            $image['is_thumbnail'] = $i === ($chosen === false ? 0 : $chosen);
            if ($image['alt'] === '') {
                $image['alt'] = $name;
            }
        }
        unset($image);

        return [
            'name' => $name,
            // The slug and SEO fields fill themselves in when left empty.
            'slug' => Str::slug($this->text($payload['slug'] ?? '') ?: $name),
            'sku' => strtoupper($this->text($payload['sku'] ?? '', 64)),
            'category_id' => is_numeric($payload['category_id'] ?? null) ? (int) $payload['category_id'] : null,
            'short_description' => $short,
            'description' => (string) RichText::clean(is_string($payload['description'] ?? null) ? $payload['description'] : ''),
            'key_features' => collect((array) ($payload['key_features'] ?? []))
                ->map(fn ($feature) => $this->text($feature, 300))->filter()->values()->all(),
            'specs' => collect((array) ($payload['specs'] ?? []))
                ->filter(fn ($spec) => is_array($spec))
                ->map(fn ($spec) => ['label' => $this->text($spec['label'] ?? ''), 'value' => $this->text($spec['value'] ?? '', 500)])
                ->filter(fn ($spec) => $spec['label'] !== '' || $spec['value'] !== '')->values()->all(),
            'price_usd' => $this->money($payload['price_usd'] ?? null),
            'price_lkr' => $this->money($payload['price_lkr'] ?? null),
            'stock_status' => $this->text($payload['stock_status'] ?? StockStatus::InStock->value, 20),
            'store_url' => $this->nullableText($payload['store_url'] ?? null, 1000),
            'is_featured' => (bool) ($payload['is_featured'] ?? false),
            'images' => $images,
            'documents' => collect((array) ($payload['documents'] ?? []))
                ->filter(fn ($document) => is_array($document) && is_numeric($document['media_id'] ?? null))
                ->map(fn ($document) => [
                    'media_id' => (int) $document['media_id'],
                    'title' => $this->text($document['title'] ?? ''),
                    'type' => $this->text($document['type'] ?? 'other', 20),
                ])->values()->all(),
            'tag_ids' => collect((array) ($payload['tag_ids'] ?? []))->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'new_tags' => collect((array) ($payload['new_tags'] ?? []))->map(fn ($tag) => $this->text($tag, 60))->filter()->unique(fn ($tag) => Str::slug($tag))->values()->all(),
            'seo_title' => $this->text($payload['seo_title'] ?? '') ?: $name,
            'seo_description' => $this->text($payload['seo_description'] ?? '', 500) ?: $short,
        ];
    }

    public function rules(array $payload, ?int $entityId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($entityId)],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9._\-]*$/', Rule::unique('products', 'sku')->ignore($entityId)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'short_description' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:100000'],
            'key_features' => ['array', 'max:20'],
            'key_features.*' => ['string', 'max:300'],
            'specs' => ['array', 'max:60'],
            'specs.*.label' => ['required', 'string', 'max:255'],
            'specs.*.value' => ['required', 'string', 'max:500'],
            'price_usd' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'price_lkr' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'stock_status' => ['required', Rule::enum(StockStatus::class)],
            // The "Buy" button opens this address. https only, so it can never be a javascript: or data: link.
            'store_url' => ['nullable', 'string', 'max:1000', 'url:https'],
            'is_featured' => ['boolean'],
            'images' => ['array', 'max:12'],
            'images.*.media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', Media::KIND_IMAGE)],
            // Only demo artwork may be referenced by path; real images come from the media library.
            'images.*.path' => ['nullable', 'required_without:images.*.media_id', 'string', 'starts_with:placeholders/', 'not_regex:/\.\./'],
            'images.*.alt' => ['required', 'string', 'max:255'],
            'documents' => ['array', 'max:20'],
            'documents.*.media_id' => ['required', 'integer', Rule::exists('media', 'id')->where('kind', Media::KIND_DOCUMENT)],
            'documents.*.title' => ['required', 'string', 'max:255'],
            'documents.*.type' => ['required', Rule::in(array_keys(ProductDocument::TYPES))],
            'tag_ids' => ['array', 'max:30'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')],
            'new_tags' => ['array', 'max:10'],
            'new_tags.*' => ['string', 'max:60'],
            'seo_title' => ['required', 'string', 'max:255'],
            'seo_description' => ['required', 'string', 'max:500'],
        ];
    }

    public function validationMessages(): array
    {
        return [
            'slug.unique' => 'Another product already uses this web address (slug).',
            'sku.unique' => 'Another product already uses this SKU.',
            'sku.regex' => 'The SKU may only contain letters, numbers, dots, dashes and underscores.',
            'specs.*.label.required' => 'Each specification needs a name.',
            'specs.*.value.required' => 'Each specification needs a value.',
            'documents.*.title.required' => 'Each document needs a title.',
            'images.*.path.starts_with' => 'Images must be chosen from the media library.',
            'store_url.url' => 'The store link must be a full address starting with https://',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'category_id' => 'category', 'price_usd' => 'price in USD', 'price_lkr' => 'price in LKR',
            'sku' => 'SKU', 'slug' => 'web address', 'seo_title' => 'SEO title', 'seo_description' => 'SEO description',
            'store_url' => 'store link',
        ];
    }

    public function title(array $payload): string
    {
        return $payload['name'] ?? 'Untitled product';
    }

    public function current(int $entityId): ?array
    {
        $product = Product::with(['images', 'specs', 'documents', 'tags'])->find($entityId);

        if (! $product) {
            return null;
        }

        return [
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'short_description' => $product->short_description,
            'description' => (string) $product->description,
            'key_features' => array_values($product->key_features ?? []),
            'specs' => $product->specs->map(fn (ProductSpec $spec) => ['label' => $spec->label, 'value' => $spec->value])->all(),
            'price_usd' => $product->price_usd,
            'price_lkr' => $product->price_lkr,
            'stock_status' => $product->stock_status->value,
            'store_url' => $product->store_url,
            'is_featured' => $product->is_featured,
            'images' => $product->images->map(fn (ProductImage $image) => [
                'media_id' => $image->media_id,
                'path' => $image->media_id ? null : $image->path,
                'alt' => $image->alt,
                'is_thumbnail' => $image->is_thumbnail,
            ])->all(),
            'documents' => $product->documents->map(fn (ProductDocument $document) => [
                'media_id' => $document->media_id, 'title' => $document->title, 'type' => $document->type,
            ])->all(),
            'tag_ids' => $product->tags->pluck('id')->sort()->values()->all(),
            'new_tags' => [],
            'seo_title' => $product->seo_title ?: $product->name,
            'seo_description' => $product->seo_description ?: $product->short_description,
        ];
    }

    public function apply(array $payload, ?int $entityId): int
    {
        $product = $entityId ? Product::findOrFail($entityId) : new Product;

        $product->fill([
            'category_id' => $payload['category_id'],
            'name' => $payload['name'],
            'slug' => $payload['slug'],
            'sku' => $payload['sku'],
            'short_description' => $payload['short_description'],
            'description' => $payload['description'],
            'key_features' => $payload['key_features'],
            'price_usd' => $payload['price_usd'],
            'price_lkr' => $payload['price_lkr'],
            'stock_status' => $payload['stock_status'],
            'store_url' => $payload['store_url'] ?? null,
            'is_featured' => $payload['is_featured'],
            'seo_title' => $payload['seo_title'],
            'seo_description' => $payload['seo_description'],
        ])->save();

        $media = Media::whereIn('id', collect($payload['images'])->pluck('media_id')->filter())->get()->keyBy('id');

        $product->images()->delete();
        foreach ($payload['images'] as $order => $image) {
            $product->images()->create([
                'media_id' => $image['media_id'],
                'path' => $image['media_id'] ? $media[$image['media_id']]->path : $image['path'],
                'alt' => $image['alt'],
                'is_thumbnail' => $image['is_thumbnail'],
                'sort_order' => $order + 1,
            ]);
        }

        $product->specs()->delete();
        foreach ($payload['specs'] as $order => $spec) {
            $product->specs()->create($spec + ['sort_order' => $order + 1]);
        }

        $product->documents()->delete();
        foreach ($payload['documents'] as $order => $document) {
            $product->documents()->create($document + ['sort_order' => $order + 1]);
        }

        // Tags typed into the product form are created now, at approval.
        $tagIds = collect($payload['tag_ids']);
        foreach ($payload['new_tags'] as $name) {
            $tagIds->push(Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id);
        }
        $product->tags()->sync($tagIds->unique()->all());

        return $product->id;
    }

    public function remove(int $entityId): void
    {
        Product::findOrFail($entityId)->delete(); // images, specs, documents and tag links cascade
    }

    public function summary(array $payload): array
    {
        $payload = $payload + ['images' => [], 'documents' => [], 'specs' => [], 'key_features' => [], 'tag_ids' => [], 'new_tags' => []];
        $media = Media::whereIn('id', collect($payload['images'])->pluck('media_id')
            ->merge(collect($payload['documents'])->pluck('media_id'))->filter())->get()->keyBy('id');

        $tags = Tag::whereIn('id', $payload['tag_ids'])->orderBy('name')->pluck('name')
            ->merge(collect($payload['new_tags'])->map(fn ($tag) => "$tag (new tag)"))->all();

        return [
            'Name' => $payload['name'] ?? '',
            'Web address' => '/product/'.($payload['slug'] ?? ''),
            'SKU' => $payload['sku'] ?? '',
            'Category' => Category::find($payload['category_id'] ?? null)?->name ?? '(none)',
            'Short description' => $payload['short_description'] ?? '',
            'Full description' => trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>'], "\n", $payload['description'] ?? '')))),
            'Key features' => $payload['key_features'],
            'Specifications' => collect($payload['specs'])->map(fn ($spec) => "{$spec['label']}: {$spec['value']}")->all(),
            'Price (USD)' => isset($payload['price_usd']) ? '$'.number_format((float) $payload['price_usd'], 2) : 'Not set',
            'Price (LKR)' => isset($payload['price_lkr']) ? 'Rs '.number_format((float) $payload['price_lkr'], 2) : 'Not set',
            'Stock status' => StockStatus::tryFrom($payload['stock_status'] ?? '')?->label() ?? '',
            'Store link (Buy button)' => ($payload['store_url'] ?? null) ?: 'Not set',
            'Featured on home page' => ($payload['is_featured'] ?? false) ? 'Yes' : 'No',
            'Images' => collect($payload['images'])->map(fn ($image) => ($image['media_id'] ? ($media[$image['media_id']]->original_name ?? 'Missing file') : basename((string) $image['path']))
                .($image['is_thumbnail'] ? ' (thumbnail)' : '').' – alt: '.$image['alt'])->all(),
            'Documents' => collect($payload['documents'])->map(fn ($document) => (ProductDocument::TYPES[$document['type']] ?? 'Other').': '.$document['title']
                .' ('.($media[$document['media_id']]->original_name ?? 'missing file').')')->all(),
            'Tags' => $tags,
            'SEO title' => $payload['seo_title'] ?? '',
            'SEO description' => $payload['seo_description'] ?? '',
        ];
    }

    public function editUrl(?int $entityId, ?int $revisionId = null): string
    {
        return $revisionId
            ? route('studio.products.revision', $revisionId)
            : ($entityId ? route('studio.products.edit', $entityId) : route('studio.products.create'));
    }

    public function publicUrl(int $entityId): ?string
    {
        $slug = Product::whereKey($entityId)->value('slug');

        return $slug ? route('products.show', $slug) : null;
    }

    public function hasPreview(): bool
    {
        return true;
    }

    /** Builds an unsaved Product from the payload and renders the real product page with it. */
    public function preview(array $payload, ?int $entityId): ?Renderable
    {
        $payload = $this->normalise($payload, $entityId);
        $media = Media::whereIn('id', collect($payload['images'])->pluck('media_id')
            ->merge(collect($payload['documents'])->pluck('media_id'))->filter())->get()->keyBy('id');

        $product = new Product(collect($payload)->only([
            'name', 'slug', 'sku', 'short_description', 'description', 'key_features', 'price_usd', 'price_lkr',
            'stock_status', 'store_url', 'is_featured', 'seo_title', 'seo_description',
        ])->all());
        $product->category_id = $payload['category_id'];

        $product->setRelation('category', Category::find($payload['category_id']) ?? new Category(['name' => 'No category', 'slug' => 'none']));
        $product->setRelation('images', collect($payload['images'])->map(function ($image) use ($media) {
            $model = new ProductImage([
                'path' => $image['media_id'] ? ($media[$image['media_id']]->path ?? '') : $image['path'],
                'alt' => $image['alt'], 'is_thumbnail' => $image['is_thumbnail'],
            ]);
            $model->setRelation('media', $image['media_id'] ? ($media[$image['media_id']] ?? null) : null);

            return $model;
        }));
        $product->setRelation('specs', collect($payload['specs'])->map(fn ($spec) => new ProductSpec($spec)));
        $product->setRelation('documents', collect($payload['documents'])->map(function ($document) use ($media) {
            $model = new ProductDocument($document);
            $model->setRelation('media', $media[$document['media_id']] ?? null);

            return $model;
        })->filter(fn ($document) => $document->media));
        $product->setRelation('tags', Tag::whereIn('id', $payload['tag_ids'])->orderBy('name')->get()
            ->concat(collect($payload['new_tags'])->map(fn ($name) => new Tag(['name' => $name, 'slug' => Str::slug($name)]))));

        return view('products.show', [
            'product' => $product,
            'related' => collect(),
            'description' => RichText::clean($product->description),
            'structuredData' => [],
            'isPreview' => true,
        ]);
    }
}
