<x-layouts.public title="Services" description="Parcourez les services publiés : prix, délai et corrections annoncés." :seo="['jsonld' => [\App\Shared\Seo::breadcrumbs([['name' => 'Accueil', 'url' => \App\Shared\Seo::base().'/'], ['name' => 'Services', 'url' => \App\Shared\Seo::url('services.index')]])]]" main-class="catalog-page">
  <livewire:catalog.service-search />
</x-layouts.public>
