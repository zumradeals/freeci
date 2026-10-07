// Navigation only: every input stays in the same form and is submitted together.
const form = document.querySelector('[data-mission-editor]');
if (form) {
    const panels = [...form.querySelectorAll('[data-mission-panel]')];
    const steps = [...form.querySelectorAll('[data-mission-go]')];
    const previous = form.querySelector('[data-mission-prev]');
    const next = form.querySelector('[data-mission-next]');
    const final = form.querySelector('[data-mission-final]');
    let current = 0;
    const show = (index, focus = false) => {
        current = Math.max(0, Math.min(index, panels.length - 1));
        panels.forEach((panel, i) => { panel.hidden = i !== current; });
        steps.forEach((step, i) => {
            if (i === current) step.setAttribute('aria-current', 'step');
            else step.removeAttribute('aria-current');
        });
        previous.disabled = current === 0;
        next.hidden = current === panels.length - 1;
        if (final) final.hidden = current !== panels.length - 1;
        form.querySelector('[data-mission-progress]').textContent = `Étape ${current + 1} sur ${panels.length}`;
        history.replaceState(null, '', `#${panels[current].id}`);
        if (focus) {
            const heading = panels[current].querySelector('h2');
            heading.tabIndex = -1;
            heading.focus();
        }
    };
    steps.forEach((step, i) => step.addEventListener('click', () => show(i, true)));
    previous.addEventListener('click', () => show(current - 1, true));
    next.addEventListener('click', () => show(current + 1, true));
    form.querySelector('.editor-steps').hidden = false;
    form.querySelector('.editor-pagination').hidden = false;
    const invalid = panels.findIndex(panel => panel.querySelector('.field-error'));
    const fragment = panels.findIndex(panel => `#${panel.id}` === location.hash);
    show(invalid >= 0 ? invalid : Math.max(0, fragment));
    const value = name => form.elements.namedItem(name)?.value || '';
    form.elements.namedItem('budget_xof').setAttribute('inputmode', 'numeric');
    const preview = () => {
        const text = (key, content) => { form.querySelector(`[data-mission-${key}]`).textContent = content; };
        text('title', value('title') || 'Le titre de votre mission');
        const description = value('description').trim();
        text('description', description ? description.slice(0, 240) + (description.length > 240 ? '…' : '') : 'Décrivez le résultat attendu pour guider les freelances.');
        text('category', form.elements.namedItem('category_id').selectedOptions[0]?.textContent || 'Catégorie à choisir');
        const budget = Number(value('budget_xof').replace(/\s/g, ''));
        text('budget', Number.isFinite(budget) && budget > 0 ? `${new Intl.NumberFormat('fr-CI').format(budget)} FCFA` : 'À renseigner');
        const date = value('application_deadline');
        text('deadline', /^\d{4}-\d{2}-\d{2}$/.test(date) ? date.split('-').reverse().join('/') : 'À choisir');
        text('files', form.querySelector('input[type="checkbox"][name="brief_requires_files"]').checked ? 'Vous devrez joindre un fichier privé avant le démarrage du travail.' : 'Aucun fichier obligatoire de votre part.');
    };
    preview();
    form.querySelector('[data-mission-preview]').hidden = false;
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; preview(); });
    form.addEventListener('change', () => { dirty = true; preview(); });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
}
