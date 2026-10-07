{{-- Liens de navigation partagés (en-tête, tiroir). --}}
@props(['drawer' => false])
@foreach(\App\Modules\Admin\Navigation\MenuItems::links('header') as [$label, $url, $dest])
<a href="{{ $url }}">@if($drawer)<x-fc.icon :name="['services' => 'search', 'missions' => 'briefcase', 'freelances' => 'user', 'how' => 'info', 'freelance_activate' => 'briefcase'][$dest] ?? 'arrow-right'" />@endif {{ $label }}</a>
@endforeach
