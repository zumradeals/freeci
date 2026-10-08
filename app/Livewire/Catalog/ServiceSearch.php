<?php

namespace App\Livewire\Catalog;

use App\Modules\Catalog\Actions\ListCategories;
use App\Modules\Catalog\Actions\ListSkills;
use App\Modules\Catalog\Actions\SearchServices;
use App\Modules\Catalog\Data\ServiceSearchCriteria;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Îlot interactif du catalogue : l'état (recherche, catégorie, prix, délai, compétence, tri, page) vit dans l'URL.
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

    #[Url(as: 'prix_min', except: '')]
    public string $prixMin = '';

    #[Url(as: 'prix_max', except: '')]
    public string $prixMax = '';

    #[Url(as: 'delai_max', except: '')]
    public string $delaiMax = '';

    #[Url(as: 'competence', except: '')]
    public string $competence = '';

    #[Url(as: 'tri', except: 'pertinence')]
    public string $tri = 'pertinence';

    public function updating(string $name): void
    {
        if (in_array($name, ['q', 'categorie', 'tri', 'prixMin', 'prixMax', 'delaiMax', 'competence'], true)) {
            $this->resetPage();
        }
    }

    /** Tranche de budget prédéfinie (FCFA) ; une valeur vide laisse la borne ouverte. */
    public function setBudget(string $min, string $max): void
    {
        $this->prixMin = ctype_digit($min) ? $min : '';
        $this->prixMax = ctype_digit($max) ? $max : '';
        $this->resetPage();
    }

    public function setDelay(string $days): void
    {
        $this->delaiMax = ctype_digit($days) ? $days : '';
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->reset(['q', 'categorie', 'tri', 'prixMin', 'prixMax', 'delaiMax', 'competence']);
        $this->resetPage();
    }

    /** Retire UN filtre appliqué (puce). */
    public function removeFilter(string $name): void
    {
        if (in_array($name, ['q', 'categorie', 'prixMin', 'prixMax', 'delaiMax', 'competence'], true)) {
            $this->reset($name);
            $this->resetPage();
        }
    }

    public function render(SearchServices $search, ListCategories $categories, ListSkills $skills)
    {
        $criteria = ServiceSearchCriteria::make($this->q, $this->categorie, $this->tri, $this->prixMin, $this->prixMax, $this->delaiMax, $this->competence);
        $cats = $categories->withServiceCounts();

        return view('livewire.catalog.service-search', [
            'results' => $search($criteria, $this->getPage(), auth()->user()),
            'categories' => $cats,
            'skills' => $skills(),
            'criteria' => $criteria,
            'currentCategory' => collect($cats)->firstWhere('slug', $criteria->categorySlug),
        ]);
    }
}
