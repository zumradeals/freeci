{{-- Liens de navigation partagés (en-tête, tiroir). --}}
@props(['drawer' => false])
<a href="{{ route('services.index') }}">@if($drawer)<x-fc.icon name="search" />@endif Services</a>
<a href="{{ route('missions.index') }}">@if($drawer)<x-fc.icon name="briefcase" />@endif Missions</a>
<a href="{{ route('coming-soon', 'freelances') }}">@if($drawer)<x-fc.icon name="user" />@endif Freelances <x-fc.soon /></a>
<a href="{{ route('home') }}#comment">@if($drawer)<x-fc.icon name="info" />@endif Comment ça marche</a>
