<x-layouts.public :title="$title" :robots="$approved ? null : 'noindex, nofollow'">
<div class="container" style="padding-block:32px;max-width:760px">
  <h1 class="t-h1">{{ $title }}</h1>
  <x-fc.draft-banner :approved="$approved" />
  <dl class="stack-sm" style="margin-top:16px">
    <div><dt><strong>Éditeur du service</strong></dt><dd>@if($legal['operator_name']){{ $legal['operator_name'] }}@else<em>à renseigner</em>@endif</dd></div>
    <div><dt><strong>Adresse</strong></dt><dd>@if($legal['operator_address']){{ $legal['operator_address'] }}@else<em>à renseigner</em>@endif</dd></div>
    <div><dt><strong>Immatriculation</strong></dt><dd>@if($legal['operator_registration']){{ $legal['operator_registration'] }}@else<em>à renseigner</em>@endif</dd></div>
    <div><dt><strong>Directeur de la publication</strong></dt><dd>@if($legal['publication_director']){{ $legal['publication_director'] }}@else<em>à renseigner</em>@endif</dd></div>
    <div><dt><strong>Hébergeur</strong></dt><dd>@if($legal['host']){{ $legal['host'] }}@else<em>à renseigner</em>@endif</dd></div>
    <div><dt><strong>Contact</strong></dt><dd>@if($legal['contact_email']){{ $legal['contact_email'] }}@else<em>à renseigner</em>@endif</dd></div>
  </dl>
</div></x-layouts.public>
