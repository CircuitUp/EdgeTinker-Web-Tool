<?php

namespace App\Studio\ContentTypes;

use App\Models\Product;
use App\Models\WikiPage;
use App\Support\WikiMarkdown;
use App\Support\WikiTree;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Payload: product_id, title, slug, body (Markdown), sort_order */
class WikiPageType extends ContentType
{
    public function key(): string
    {
        return 'wiki_page';
    }

    public function label(): string
    {
        return 'Wiki page';
    }

    public function normalise(array $payload, ?int $entityId): array
    {
        $title = $this->text($payload['title'] ?? '');

        return [
            'product_id' => is_numeric($payload['product_id'] ?? null) ? (int) $payload['product_id'] : null,
            'title' => $title,
            'slug' => Str::slug($this->text($payload['slug'] ?? '') ?: str_replace('&', 'and', $title)),
            // Markdown is stored as written; it is made safe when rendered (see WikiMarkdown).
            'body' => is_string($payload['body'] ?? null) ? preg_replace("/\r\n?/", "\n", $payload['body']) : '',
            'sort_order' => is_numeric($payload['sort_order'] ?? null) ? max(0, (int) $payload['sort_order']) : 0,
        ];
    }

    public function rules(array $payload, ?int $entityId): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255',
                Rule::unique('wiki_pages', 'slug')->where('product_id', $payload['product_id'] ?? 0)->ignore($entityId)],
            'body' => ['required', 'string', 'max:200000'],
            'sort_order' => ['integer', 'min:0', 'max:100000'],
        ];
    }

    public function validationMessages(): array
    {
        return [
            'slug.unique' => 'This product already has a wiki page with this web address.',
            'body.required' => 'The page needs some content.',
            'product_id.exists' => 'The product this page belongs to no longer exists.',
        ];
    }

    public function validationAttributes(): array
    {
        return ['product_id' => 'product', 'slug' => 'web address', 'body' => 'content', 'sort_order' => 'position'];
    }

    public function title(array $payload): string
    {
        $product = Product::whereKey($payload['product_id'] ?? null)->value('name');

        return ($product ? "$product: " : '').($payload['title'] ?? 'Untitled page');
    }

    public function current(int $entityId): ?array
    {
        $page = WikiPage::find($entityId);

        return $page ? $page->only(['product_id', 'title', 'slug', 'body', 'sort_order']) : null;
    }

    public function apply(array $payload, ?int $entityId): int
    {
        $page = $entityId ? WikiPage::findOrFail($entityId) : new WikiPage;
        $page->fill($payload)->save();

        return $page->id;
    }

    public function remove(int $entityId): void
    {
        WikiPage::findOrFail($entityId)->delete();
    }

    public function summary(array $payload): array
    {
        $product = Product::with('category:id,slug')->find($payload['product_id'] ?? null);

        return [
            'Product' => $product?->name ?? '(missing product)',
            'Title' => $payload['title'] ?? '',
            'Web address' => $product ? "/wiki/{$product->category->slug}/{$product->slug}/".($payload['slug'] ?? '') : '',
            'Position' => (string) ($payload['sort_order'] ?? 0),
            // One entry per line, so the comparison shows which lines changed.
            'Content (Markdown)' => $payload['body'] ?? '',
        ];
    }

    public function editUrl(?int $entityId, ?int $revisionId = null): string
    {
        return $revisionId ? route('studio.wiki.revision', $revisionId) : route('studio.wiki.edit', $entityId);
    }

    public function publicUrl(int $entityId): ?string
    {
        $page = WikiPage::with('product.category')->find($entityId);

        return $page?->product?->category ? $page->url() : null;
    }

    public function hasPreview(): bool
    {
        return true;
    }

    public function preview(array $payload, ?int $entityId): ?Renderable
    {
        $payload = $this->normalise($payload, $entityId);
        $product = Product::with('category')->find($payload['product_id']);

        if (! $product) {
            return null;
        }

        $page = new WikiPage($payload);
        $page->updated_at = now();
        $page->setRelation('product', $product);

        return view('wiki.show', [
            'product' => $product,
            'page' => $page,
            'rendered' => WikiMarkdown::render($payload['body']),
            'pages' => $product->wikiPages,
            'previous' => null,
            'next' => null,
            'tree' => app(WikiTree::class)->get(),
            'isPreview' => true,
        ]);
    }
}
