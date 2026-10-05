<?php

namespace App\Livewire\Catalog;

use App\Modules\Catalog\Actions\ListCategories;
use App\Modules\Catalog\Actions\SearchServices;
use App\Modules\Catalog\Data\ServiceSearchCriteria;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Îlot interactif du catalogue : l'état (recherche, catégorie, tri, page) vit dans l'URL.
 * Sans JavaScript, le formulaire envoie la même requête GET et la page se rend à l'identique.
 * Le composant ne lit rien lui-même : il appelle l'Action publique `SearchServices`.
 */
class ServiceSearch extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'categorie', except: '')]
    public string $categorie = '';

    #[Url(as: 'tri', except: 'pertinence')]
    public string $tri = 'pertinence';

    public function updating(string $name): void
    {
        if (in_array($name, ['q', 'categorie', 'tri'], true)) {
            $this->resetPage();
        }
    }

    public function clear(): void
    {
        $this->reset(['q', 'categorie', 'tri']);
        $this->resetPage();
    }

    public function removeQuery(): void
    {
        $this->reset('q');
        $this->resetPage();
    }

    public function removeCategory(): void
    {
        $this->reset('categorie');
        $this->resetPage();
    }

    public function render(SearchServices $search, ListCategories $categories)
    {
        $criteria = ServiceSearchCriteria::make($this->q, $this->categorie, $this->tri);
        $cats = $categories();
        $current = collect($cats)->firstWhere('slug', $criteria->categorySlug);

        return view('livewire.catalog.service-search', [
            'results' => $search($criteria, $this->getPage()),
            'categories' => $cats,
            'criteria' => $criteria,
            'currentCategory' => $current,
        ]);
    }
}
