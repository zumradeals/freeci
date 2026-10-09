@props(['user', 'short' => false, 'rate' => false])
@php($sg = app(\App\Modules\Catalog\Actions\SellerSignals::class)->for((string) $user))
@if(! $sg['available'])<span class="av-pill off"><i class="av-dot off" aria-hidden="true"></i> {{ $short ? ($sg['back_on'] ? 'Retour le '.$sg['back_on'] : 'Indisponible') : 'Indisponible'.($sg['back_on'] ? ' jusqu’au '.$sg['back_on'] : '') }}</span>
@elseif($sg['label'])<span class="av-pill"><x-fc.icon name="clock" :size="14" /> {{ $short ? $sg['label'] : 'Répond en '.$sg['label'] }}</span>@if($rate && $sg['rate'] !== null) <span class="av-pill">{{ $sg['rate'] }} % traitées dans le délai</span>@endif
@elseif($sg['is_new'])<span class="av-pill new">{{ $short ? 'Nouveau' : 'Nouveau sur FreeCI' }}</span>@endif
