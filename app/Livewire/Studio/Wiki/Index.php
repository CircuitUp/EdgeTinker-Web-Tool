<?php

namespace App\Livewire\Studio\Wiki;

use App\Enums\RevisionAction;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Category;
use App\Models\Product;
use App\Models\Revision;
use App\Models\WikiPage;
use App\Studio\RevisionService;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** The wiki tree manager: category → product → pages, with what is live and what is pending. */
#[Title('Wiki')]
class Index extends Component
{
    use StudioScreen;

    /** Product whose pages are shown expanded. */
    #[Url(as: 'product')]
    public ?int $open = null;

    public function toggle(int $productId): void
    {
        $this->open = $this->open === $productId ? null : $productId;
    }

    /**
     * Creates drafts for whichever of the standard pages (Overview, Quick Start, …)
     * the product does not have yet, ready to be filled in.
     */
    public function addStandardPages(int $productId, RevisionService $revisions): void
    {
        $product = Product::with('wikiPages')->findOrFail($productId);
        $user = $this->user();

        $existing = $product->wikiPages->pluck('slug')
            ->merge(Revision::query()->where('entity_type', 'wiki_page')->pending()->get()
                ->filter(fn (Revision $r) => ($r->payload['product_id'] ?? null) === $product->id)->map(fn (Revision $r) => $r->payload['slug'] ?? null));

        $created = 0;
        foreach (WikiPage::STANDARD_PAGES as $order => $title) {
            $slug = Str::slug(str_replace('&', 'and', $title));
            if ($existing->contains($slug)) {
                continue;
            }

            $revisions->save($user, 'wiki_page', null, [
                'product_id' => $product->id,
                'title' => $title,
                'slug' => $slug,
                'sort_order' => $order + 1,
                'body' => "## $title\n\nWrite the ".strtolower($title)." for the {$product->name} here.\n",
            ], submit: false);
            $created++;
        }

        $this->open = $product->id;
        $this->toast($created ? 'success' : 'info', $created
            ? "$created draft ".Str::plural('page', $created).' created. Fill them in, then submit each one.'
            : 'This product already has all the standard pages.');
    }

    public function requestDeletion(int $pageId, RevisionService $revisions): void
    {
        if ($this->attempt(fn () => $revisions->requestDeletion($this->user(), 'wiki_page', $pageId))) {
            $this->toast('success', $this->user()->isAdmin() ? 'The page has been deleted.' : 'Deletion requested. The page stays live until the Content Admin approves.');
        }
    }

    public function render()
    {
        $pending = Revision::query()->where('entity_type', 'wiki_page')->pending()->with('author:id,name')->latest('updated_at')->get();

        return view('livewire.studio.wiki.index', [
            'categories' => Category::with(['products' => fn ($query) => $query->orderBy('name')->withCount('wikiPages')])
                ->orderBy('sort_order')->orderBy('name')->get(),
            'pages' => $this->open ? WikiPage::where('product_id', $this->open)->orderBy('sort_order')->orderBy('id')->get() : collect(),
            'pendingByPage' => $pending->whereNotNull('entity_id')->groupBy('entity_id'),
            // New pages are not live yet; group them under the product they are for.
            'newByProduct' => $pending->where('action', RevisionAction::Create)->groupBy(fn (Revision $r) => $r->payload['product_id'] ?? 0),
            'userId' => $this->user()->id,
            'isAdmin' => $this->user()->isAdmin(),
        ]);
    }
}
