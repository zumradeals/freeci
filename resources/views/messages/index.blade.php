<x-layouts.account title="Messages" :space="$space">
  <div class="ms-shell is-index">
    <x-messages.list-pane :conversations="$conversations" :blocked="$blocked" :space="$space" />
    <section class="ms-conv ms-conv-empty" aria-label="Conversation">
      <div class="ms-empty"><x-fc.icon name="message" :size="30" /><p style="font-weight:600">Sélectionnez une conversation</p>
        <p class="note-line"><x-fc.icon name="lock" :size="16" /><span>Conversations privées entre deux personnes. <strong>Un message ou une pièce jointe n’est jamais une livraison, une modification de l’accord, l’acceptation d’un report ni une validation</strong> : ces actions se font depuis la commande.</span></p></div>
    </section>
  </div>
</x-layouts.account>
