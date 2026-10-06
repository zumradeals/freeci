<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ isset($title) ? $title.' — FreeCI' : 'FreeCI — des compétences en Côte d’Ivoire' }}</title>
<meta name="description" content="{{ $description ?? 'FreeCI : trouvez une prestation à prix et délai annoncés, suivez votre commande de la demande à la validation.' }}">
@php($robotsValue = config('freeci.noindex') ? 'noindex, nofollow' : ($robots ?? null))
@if($robotsValue)<meta name="robots" content="{{ $robotsValue }}">@endif
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
