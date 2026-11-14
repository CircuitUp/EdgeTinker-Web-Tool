<?php

namespace App\Studio\ContentTypes;

use InvalidArgumentException;

/** Looks up a content type by its key. Types are registered in AppServiceProvider. */
class ContentTypeRegistry
{
    /** @var array<string, ContentType> */
    private array $types = [];

    public function register(ContentType $type): void
    {
        $this->types[$type->key()] = $type;
    }

    public function get(string $key): ContentType
    {
        return $this->types[$key] ?? throw new InvalidArgumentException("Unknown content type [$key].");
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /** @return array<string, string> key => label */
    public function labels(): array
    {
        return array_map(fn (ContentType $type) => $type->label(), $this->types);
    }
}
