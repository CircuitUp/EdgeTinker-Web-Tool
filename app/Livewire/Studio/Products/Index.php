<?php

namespace App\Livewire\Studio\Products;

use App\Enums\RevisionAction;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Product;
use App\Models\Revision;
use App\Studio\RevisionService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Products')]
class Index extends Component
{
    use StudioScreen, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** all | pending (has open changes) | featured */
    #[Url]
    public string $show = 'all';

    public function updating($name): void
    {
        if (in_array($name, ['search', 'show'], true)) {
            $this->resetPage();
        }
    }

    public function requestDeletion(int $productId, RevisionService $revisions): void
    {
        $done = $this->attempt(fn () => $revisions->requestDeletion($this->user(), 'product', $productId));

        if ($done) {
            $this->toast('success', $this->user()->isAdmin()
                ? 'The product has been deleted.'
                : 'Deletion requested. The product stays live until the Content Admin approves.');
        }
    }

    public function render()
    {
        $pendingIds = Revision::query()->where('entity_type', 'product')->pending()->whereNotNull('entity_id')->pluck('entity_id')->unique();

        $products = Product::query()
            ->with(['category:id,name', 'thumbnail.media'])
            ->when($this->search !== '', function ($query) {
                $like = '%'.addcslashes($this->search, '\\%_').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like));
            })
            ->when($this->show === 'pending', fn ($query) => $query->whereIn('id', $pendingIds))
            ->when($this->show === 'featured', fn ($query) => $query->where('is_featured', true))
            ->orderBy('name')
            ->paginate(15);

        // Open changes for the products on this page, grouped by product.
        $pending = Revision::query()->where('entity_type', 'product')->pending()
            ->whereIn('entity_id', $products->pluck('id'))->with('author:id,name')->get()->groupBy('entity_id');

        return view('livewire.studio.products.index', [
            'products' => $products,
            'pending' => $pending,
            // New products that are not live yet exist only as revisions.
            'newProducts' => Revision::query()->where('entity_type', 'product')
                ->where('action', RevisionAction::Create->value)->pending()->with('author:id,name')->latest('updated_at')->get(),
            'userId' => $this->user()->id,
            'isAdmin' => $this->user()->isAdmin(),
        ]);
    }
}
