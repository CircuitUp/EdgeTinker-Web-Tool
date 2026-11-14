<?php

namespace App\Livewire\Studio\Categories;

use App\Enums\RevisionAction;
use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Category;
use App\Models\Revision;
use App\Studio\RevisionService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Categories')]
class Index extends Component
{
    use StudioScreen;

    public function requestDeletion(int $categoryId, RevisionService $revisions): void
    {
        if ($this->attempt(fn () => $revisions->requestDeletion($this->user(), 'category', $categoryId))) {
            $this->toast('success', $this->user()->isAdmin()
                ? 'The category has been deleted.'
                : 'Deletion requested. The category stays live until the Content Admin approves.');
        }
    }

    public function render()
    {
        $pending = Revision::query()->where('entity_type', 'category')->pending()->with('author:id,name')->latest('updated_at')->get();

        return view('livewire.studio.categories.index', [
            'categories' => Category::withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
            'pending' => $pending->whereNotNull('entity_id')->groupBy('entity_id'),
            'newCategories' => $pending->where('action', RevisionAction::Create),
            'userId' => $this->user()->id,
            'isAdmin' => $this->user()->isAdmin(),
        ]);
    }
}
