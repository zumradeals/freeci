<x-layouts.info :title="$title" :slug="$slug" :approved="$approved">
  <dl class="prose">
    <div><dt><strong>Éditeur du service</strong></dt><dd>@if($legal['operator_name']){{ $legal['operator_name'] }}@else<em>à renseigner</em>@endif</dd></div>
    <div><dt><strong>Adresse</strong></dt><dd>@if($legal['operator_address']){{ $legal['operator_address'] }}@else<em>à renseigner</em>@endif</dd></div>
    <div><dt><strong>Immatriculation</strong></dt><dd>@if($legal['operator_registration']){{ $legal['operator_registration'] }}@else<em>à renseigner</em>@endif</dd></div>
    <div><dt><strong>Directeur de la publication</strong></dt><dd>@if($legal['publication_director']){{ $legal['publication_director'] }}@else<em>à renseigner</em>@endif</dd></div>
    <div><dt><strong>Hébergeur</strong></dt><dd>@if($legal['host']){{ $legal['host'] }}@else<em>à renseigner</em>@endif</dd></div>
    <div><dt><strong>Contact</strong></dt><dd>@if($legal['contact_email']){{ $legal['contact_email'] }}@else<em>à renseigner</em>@endif</dd></div>
  </dl>
</x-layouts.info>
