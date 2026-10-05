<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ isset($title) ? $title.' — FreeCI' : 'FreeCI — des compétences en Côte d’Ivoire' }}</title>
<meta name="description" content="{{ $description ?? 'FreeCI : trouvez une prestation à prix et délai annoncés. Version de démonstration, données fictives.' }}">
@isset($robots)<meta name="robots" content="{{ $robots }}">@endisset
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
