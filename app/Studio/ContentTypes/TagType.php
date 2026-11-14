<?php

namespace App\Studio\ContentTypes;

use App\Models\Tag;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Payload: name, slug */
class TagType extends ContentType
{
    public function key(): string
    {
        return 'tag';
    }

    public function label(): string
    {
        return 'Tag';
    }

    public function normalise(array $payload, ?int $entityId): array
    {
        $name = $this->text($payload['name'] ?? '', 60);

        return [
            'name' => $name,
            'slug' => Str::slug($this->text($payload['slug'] ?? '', 60) ?: $name),
        ];
    }

    public function rules(array $payload, ?int $entityId): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:60', Rule::unique('tags', 'slug')->ignore($entityId)],
        ];
    }

    public function validationMessages(): array
    {
        return ['slug.unique' => 'A tag with this name already exists.', 'slug.required' => 'The tag name must contain letters or numbers.'];
    }

    public function title(array $payload): string
    {
        return $payload['name'] ?? 'Untitled tag';
    }

    public function current(int $entityId): ?array
    {
        $tag = Tag::find($entityId);

        return $tag ? ['name' => $tag->name, 'slug' => $tag->slug] : null;
    }

    public function apply(array $payload, ?int $entityId): int
    {
        $tag = $entityId ? Tag::findOrFail($entityId) : new Tag;
        $tag->fill($payload)->save();

        return $tag->id;
    }

    public function remove(int $entityId): void
    {
        Tag::findOrFail($entityId)->delete(); // product links cascade
    }

    public function summary(array $payload): array
    {
        return ['Name' => $payload['name'] ?? '', 'Filter address' => '/search?tags='.($payload['slug'] ?? '')];
    }

    public function editUrl(?int $entityId, ?int $revisionId = null): string
    {
        return route('studio.tags.index');
    }

    public function publicUrl(int $entityId): ?string
    {
        $slug = Tag::whereKey($entityId)->value('slug');

        return $slug ? route('search', ['tags' => $slug]) : null;
    }
}
