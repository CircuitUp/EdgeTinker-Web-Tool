<?php

namespace App\Studio\ContentTypes;

use App\Models\Category;
use App\Models\Media;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Payload: name, slug, tagline, image: {media_id|null, path|null}, sort_order */
class CategoryType extends ContentType
{
    public function key(): string
    {
        return 'category';
    }

    public function label(): string
    {
        return 'Category';
    }

    public function normalise(array $payload, ?int $entityId): array
    {
        $name = $this->text($payload['name'] ?? '');
        $mediaId = is_numeric($payload['image']['media_id'] ?? null) ? (int) $payload['image']['media_id'] : null;

        return [
            'name' => $name,
            'slug' => Str::slug($this->text($payload['slug'] ?? '') ?: str_replace('/', '-', $name)),
            'tagline' => $this->nullableText($payload['tagline'] ?? null),
            'image' => [
                'media_id' => $mediaId,
                'path' => $mediaId ? null : $this->nullableText($payload['image']['path'] ?? null),
            ],
            'sort_order' => is_numeric($payload['sort_order'] ?? null) ? max(0, (int) $payload['sort_order']) : 0,
        ];
    }

    public function rules(array $payload, ?int $entityId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($entityId)],
            'tagline' => ['nullable', 'string', 'max:255'],
            'image.media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', Media::KIND_IMAGE)],
            'image.path' => ['nullable', 'string', 'starts_with:placeholders/', 'not_regex:/\.\./'],
            'sort_order' => ['integer', 'min:0', 'max:100000'],
        ];
    }

    public function validationMessages(): array
    {
        return ['slug.unique' => 'Another category already uses this web address (slug).'];
    }

    public function validationAttributes(): array
    {
        return ['slug' => 'web address', 'sort_order' => 'display order', 'image.media_id' => 'image'];
    }

    public function title(array $payload): string
    {
        return $payload['name'] ?? 'Untitled category';
    }

    public function current(int $entityId): ?array
    {
        $category = Category::find($entityId);

        if (! $category) {
            return null;
        }

        $media = $category->image_path ? Media::where('path', $category->image_path)->first() : null;

        return [
            'name' => $category->name,
            'slug' => $category->slug,
            'tagline' => $category->tagline,
            'image' => ['media_id' => $media?->id, 'path' => $media ? null : $category->image_path],
            'sort_order' => $category->sort_order,
        ];
    }

    public function apply(array $payload, ?int $entityId): int
    {
        $category = $entityId ? Category::findOrFail($entityId) : new Category;

        $category->fill([
            'name' => $payload['name'],
            'slug' => $payload['slug'],
            'tagline' => $payload['tagline'],
            'image_path' => $payload['image']['media_id'] ? Media::find($payload['image']['media_id'])?->path : $payload['image']['path'],
            'sort_order' => $payload['sort_order'],
        ])->save();

        return $category->id;
    }

    public function remove(int $entityId): void
    {
        $category = Category::withCount('products')->findOrFail($entityId);

        if ($category->products_count > 0) {
            throw ValidationException::withMessages(['revision' => "“{$category->name}” still contains {$category->products_count} "
                .'product(s). Move or delete them before deleting the category.']);
        }

        $category->delete();
    }

    public function summary(array $payload): array
    {
        $image = $payload['image'] ?? [];

        return [
            'Name' => $payload['name'] ?? '',
            'Web address' => '/products/'.($payload['slug'] ?? ''),
            'Tagline' => $payload['tagline'] ?? '',
            'Menu image' => ($image['media_id'] ?? null)
                ? (Media::find($image['media_id'])?->original_name ?? 'Missing file')
                : (($image['path'] ?? null) ? basename($image['path']) : 'None'),
            'Display order' => (string) ($payload['sort_order'] ?? 0),
        ];
    }

    public function editUrl(?int $entityId, ?int $revisionId = null): string
    {
        return $revisionId
            ? route('studio.categories.revision', $revisionId)
            : ($entityId ? route('studio.categories.edit', $entityId) : route('studio.categories.create'));
    }

    public function publicUrl(int $entityId): ?string
    {
        $slug = Category::whereKey($entityId)->value('slug');

        return $slug ? route('products.category', $slug) : null;
    }
}
