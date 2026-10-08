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
    form.querySelector('.editor-pagination')?.removeAttribute('hidden');
    [previous, next].forEach(button => { if (button) button.hidden = false; });
    const invalid = panels.findIndex(panel => panel.querySelector('.field-error'));
    const fragment = panels.findIndex(panel => `#${panel.id}` === location.hash);
    show(invalid >= 0 ? invalid : Math.max(0, fragment));
    const value = name => form.elements.namedItem(name)?.value || '';
    const L = JSON.parse(form.dataset.limits || '{}');
    const len = text => text.trim().length;
    const within = (n, range) => Array.isArray(range) && n >= range[0] && n <= range[1];
    const lineCount = text => text.split(/\r?\n/).map(t => t.trim()).filter(Boolean).length;
    const evaluate = () => {
        const date = value('application_deadline');
        let deadline = false;
        if (/^\d{4}-\d{2}-\d{2}$/.test(date)) {
            const chosen = new Date(`${date}T00:00:00`);
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const max = new Date(today); max.setDate(max.getDate() + Number(L.deadline_max_days || 0));
            deadline = chosen > today && chosen <= max;
        }
        return {
            title: within(len(value('title')), L.title) && value('category_id') !== '',
            description: within(len(value('description')), L.description),
            budget: within(Number(value('budget_xof').replace(/\D/g, '')) || 0, L.budget_xof),
            deadline,
            inputs: lineCount(value('client_inputs')) > 0,
        };
    };
    const refreshChecks = () => {
        const state = evaluate();
        form.querySelectorAll('[data-count]').forEach(el => {
            const n = len(value(el.dataset.count));
            el.textContent = `${n} / ${new Intl.NumberFormat('fr-FR').format(Number(el.dataset.max))}`;
            el.classList.toggle('over', n > Number(el.dataset.max));
        });
        let done = 0; let required = 0;
        form.querySelectorAll('[data-check]').forEach(li => {
            const ok = !!state[li.dataset.check];
            const optional = li.hasAttribute('data-optional');
            if (!optional) { required += 1; if (ok) done += 1; }
            li.classList.toggle('ok', ok);
            li.classList.toggle('no', !ok && !optional);
            li.classList.toggle('opt', !ok && optional);
            const sr = li.querySelector('[data-ck-sr]');
            if (sr) sr.textContent = ok ? ' : complet' : ' : à compléter';
            li.querySelector('use')?.setAttribute('href', ok ? '#i-check-circle' : (optional ? '#i-minus-circle' : '#i-warn'));
        });
        const count = form.querySelector('[data-ck-count]');
        if (count) count.textContent = String(done);
        const bar = form.querySelector('[data-ck-bar]');
        if (bar && required) bar.style.width = `${Math.round(done / required * 100)}%`;
        steps.forEach(step => {
            const keys = (step.dataset.stepKeys || '').split(',').filter(Boolean);
            const status = step.querySelector('[data-step-status]');
            if (!keys.length) { if (status) status.textContent = 'À vérifier'; return; }
            const missing = keys.filter(k => !state[k]).length;
            step.classList.toggle('is-done', missing === 0);
            step.classList.toggle('is-todo', missing > 0);
            if (status) status.textContent = missing === 0 ? 'Complet' : `${missing} point${missing > 1 ? 's' : ''} à compléter`;
        });
    };
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
    refreshChecks();
    form.querySelector('[data-mission-preview]').hidden = false;
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; preview(); refreshChecks(); });
    form.addEventListener('change', () => { dirty = true; preview(); refreshChecks(); });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
}
