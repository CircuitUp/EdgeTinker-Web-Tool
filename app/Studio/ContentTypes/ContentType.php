<?php

namespace App\Studio\ContentTypes;

use Illuminate\Contracts\Support\Renderable;

/**
 * A kind of content that goes through the approval workflow (product,
 * category, tag, wiki page, page). Each type knows how to validate a proposed
 * payload, read the live version, and copy an approved payload into the live
 * tables. The workflow itself (RevisionService) is the same for all of them.
 */
abstract class ContentType
{
    /** Stable key stored in revisions.entity_type, e.g. "product". */
    abstract public function key(): string;

    /** Singular name shown to people, e.g. "Product". */
    abstract public function label(): string;

    /** Clean a submitted payload: trim, cast, fill derived fields, sanitise HTML. */
    abstract public function normalise(array $payload, ?int $entityId): array;

    /** Validation rules for a normalised payload. Re-checked at approval time. */
    abstract public function rules(array $payload, ?int $entityId): array;

    /** Display name of the item described by the payload. */
    abstract public function title(array $payload): string;

    /** The live item in payload shape, or null if it does not exist. */
    abstract public function current(int $entityId): ?array;

    /** Write an approved payload to the live tables. Returns the item's id. */
    abstract public function apply(array $payload, ?int $entityId): int;

    /** Delete the live item. May throw a ValidationException to refuse. */
    abstract public function remove(int $entityId): void;

    /**
     * Human-readable view of a payload for the before/after comparison:
     * ['Label' => 'text' | ['list', 'of', 'lines']], in display order.
     */
    abstract public function summary(array $payload): array;

    /** Content Studio URL of the edit form for a revision or live item. */
    abstract public function editUrl(?int $entityId, ?int $revisionId = null): string;

    /** Public URL of the live item, if it has its own page. */
    public function publicUrl(int $entityId): ?string
    {
        return null;
    }

    /** Whether preview() can render a page for this type. */
    public function hasPreview(): bool
    {
        return false;
    }

    /** The public page as it would look with this payload live, or null if there is nothing to preview. */
    public function preview(array $payload, ?int $entityId): ?Renderable
    {
        return null;
    }

    public function validationMessages(): array
    {
        return [];
    }

    public function validationAttributes(): array
    {
        return [];
    }

    // ── Helpers for subclasses ───────────────────────────────────────────────

    protected function text(mixed $value, int $max = 255): string
    {
        return is_scalar($value) ? mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $value)), 0, $max) : '';
    }

    protected function nullableText(mixed $value, int $max = 255): ?string
    {
        $text = $this->text($value, $max);

        return $text === '' ? null : $text;
    }

    protected function money(mixed $value): ?string
    {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }
}
