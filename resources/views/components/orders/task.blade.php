@props(['t', 'soft' => false])
<article class="action-card task {{ $t->actionable ? '' : 'soft' }} {{ $t->due && \App\Shared\Dates::isUrgent($t->due) ? 'is-urgent' : '' }}">
  <span class="ico"><x-fc.icon :name="$t->icon" :size="22" /></span>
  <div style="min-width:0"><h3 class="t">{{ $t->title }}</h3><p class="obj">{{ $t->object }}</p>
    <p class="due {{ $t->due ? '' : 'due-info' }}"><x-fc.icon :name="$t->due ? 'clock' : 'calendar'" :size="20" /><span>@if($t->due){{ $t->dueLabel }} <span class="{{ \App\Shared\Dates::isUrgent($t->due) ? 'urgent' : 'rel' }}">({{ \App\Shared\Dates::until($t->due) }})</span>@else{{ $t->dueLabel }}@endif</span></p>
    <p class="effect">{{ $t->effect }}</p></div>
  <div class="go"><a class="btn {{ $t->actionable ? 'btn-primary' : 'btn-secondary' }}" href="{{ $t->url }}">{{ $t->cta }}</a></div>
</article>
