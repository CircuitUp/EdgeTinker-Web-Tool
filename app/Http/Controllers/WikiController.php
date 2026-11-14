<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\WikiPage;
use App\Support\WikiMarkdown;
use App\Support\WikiTree;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The public wiki: /wiki, /wiki/{category}/{product}/{page} and /wiki/search. */
class WikiController extends Controller
{
    public function __construct(private readonly WikiTree $tree) {}

    public function index(): View
    {
        return view('wiki.index', ['tree' => $this->tree->get()]);
    }

    /** /wiki/{category}/{product} goes to that product's first page. */
    public function product(string $category, string $product): RedirectResponse
    {
        $record = $this->findProduct($category, $product);
        $first = $record->wikiPages->first() ?? abort(404);

        return redirect()->route('wiki.show', [$category, $product, $first->slug]);
    }

    public function show(string $category, string $product, string $page): View
    {
        $record = $this->findProduct($category, $product);
        $pages = $record->wikiPages;
        $index = $pages->search(fn (WikiPage $candidate) => $candidate->slug === $page);

        abort_if($index === false, 404);

        $current = $pages[$index];
        $current->setRelation('product', $record);

        return view('wiki.show', [
            'product' => $record,
            'page' => $current,
            'rendered' => WikiMarkdown::render($current->body),
            'pages' => $pages,
            'previous' => $pages[$index - 1] ?? null,
            'next' => $pages[$index + 1] ?? null,
            'tree' => $this->tree->get(),
        ]);
    }

    public function search(Request $request): View
    {
        $query = $request->query('q');
        $query = is_string($query) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $query)), 0, 100) : '';

        return view('wiki.search', [
            'query' => $query,
            'results' => $query === '' ? collect() : $this->results($query),
            'tree' => $this->tree->get(),
        ]);
    }

    private function findProduct(string $category, string $product): Product
    {
        return Product::query()
            ->with(['category', 'wikiPages'])
            ->where('slug', $product)
            ->whereHas('category', fn ($query) => $query->where('slug', $category))
            ->firstOrFail();
    }

    /**
     * Wiki-wide search over page titles and content. Every word must appear;
     * title matches rank first. Uses the FULLTEXT index on MySQL and LIKE elsewhere.
     */
    private function results(string $query)
    {
        $words = collect(preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($word) => trim(preg_replace('/[^\p{L}\p{N}\-\.\+\/]+/u', '', $word), '-./+'))
            ->filter()->unique()->take(8)->values();

        if ($words->isEmpty()) {
            return collect();
        }

        $search = WikiPage::query()->with('product.category');
        $fullText = in_array($search->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '\\%_').'%';
            $parts = array_filter(preg_split('/[^\p{L}\p{N}]+/u', $word, -1, PREG_SPLIT_NO_EMPTY), fn ($part) => mb_strlen($part) >= 3);

            $search->where(function ($group) use ($like, $parts, $fullText) {
                $group->where('wiki_pages.title', 'like', $like)
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', $like)->orWhere('sku', 'like', $like));

                if ($fullText && $parts) {
                    $group->orWhereRaw('MATCH(wiki_pages.title, wiki_pages.body) AGAINST (? IN BOOLEAN MODE)',
                        [implode(' ', array_map(fn ($part) => "+$part*", $parts))]);
                } else {
                    $group->orWhere('wiki_pages.body', 'like', $like);
                }
            });
        }

        $phrase = '%'.addcslashes(mb_strtolower($query), '\\%_').'%';

        return $search
            ->orderByRaw('CASE WHEN LOWER(wiki_pages.title) LIKE ? THEN 0 ELSE 1 END', [$phrase])
            ->orderBy('wiki_pages.product_id')->orderBy('wiki_pages.sort_order')
            ->limit(50)->get()
            ->filter(fn (WikiPage $page) => $page->product?->category !== null)
            ->values();
    }
}
