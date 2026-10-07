@foreach($lines as $line)
{{ $line }}

@endforeach
@if($url)
{{ $url }}

@endif
{{ \App\Modules\Admin\Settings\AppSettings::text('mailtpl.account_warning') }}
