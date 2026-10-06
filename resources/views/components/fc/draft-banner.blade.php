@props(['approved' => false])
@unless($approved)
<div class="notice tone-warning" role="note"><x-fc.icon name="warn" /><p><strong>Brouillon — texte non adopté.</strong> Cette page est un projet de travail : elle ne constitue pas un document définitif ni un engagement de l’exploitant. Les informations marquées « à renseigner » ne sont pas encore fournies.</p></div>
@endunless
