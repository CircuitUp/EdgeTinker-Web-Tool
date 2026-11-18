<?php

namespace App\Livewire\Studio\Pages;

use App\Livewire\Studio\Concerns\StudioScreen;
use App\Models\Page;
use App\Models\Revision;
use App\Studio\ContentTypes\PageType;
use App\Support\PageContent;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pages')]
class Index extends Component
{
    use StudioScreen;

    public function render()
    {
        $pages = collect(Page::SLUGS)->map(fn (string $slug) => [
            'slug' => $slug,
            'id' => PageType::idFor($slug),
            'title' => PageContent::TITLES[$slug],
            'url' => route($slug === 'home' ? 'home' : $slug),
            'about' => match ($slug) {
                'home' => 'Featured products, partner logos and the text of each section. The banner has its own editor.',
                'services' => 'Introduction, the service cards, the “how we work” steps and the enquiry panel.',
                'contact' => 'Headings and introduction. The address, email, phone and map are in Site settings.',
            },
        ]);

        return view('livewire.studio.pages.index', [
            'pages' => $pages,
            'pending' => Revision::query()->where('entity_type', 'page')->pending()->with('author:id,name')->latest('updated_at')->get()->groupBy('entity_id'),
            'userId' => $this->user()->id,
        ]);
    }
}
