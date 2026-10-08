// Progressive enhancement: all fields remain usable if JavaScript is unavailable.
const form = document.querySelector('[data-service-editor]');
if (form) {
    ['price_xof', 'delivery_days', 'revisions_included'].forEach(name => {
        form.elements.namedItem(name)?.setAttribute('inputmode', 'numeric');
    });
    const panels = [...form.querySelectorAll('[data-editor-panel]')];
    const steps = [...form.querySelectorAll('[data-editor-go]')];
    let current = 0;
    const show = (index, focus = false) => {
        current = Math.max(0, Math.min(index, panels.length - 1));
        panels.forEach((panel, i) => { panel.hidden = i !== current; });
        steps.forEach((step, i) => {
            if (i === current) step.setAttribute('aria-current', 'step');
            else step.removeAttribute('aria-current');
        });
        form.querySelector('[data-editor-prev]').disabled = current === 0;
        form.querySelector('[data-editor-next]').hidden = current === panels.length - 1;
        const final = form.querySelector('[data-editor-final]');
        if (final) final.hidden = current !== panels.length - 1;
        form.querySelector('[data-editor-progress]').textContent = `Étape ${current + 1} sur ${panels.length}`;
        form.querySelectorAll('[data-editor-final]').forEach(button => { button.hidden = current !== panels.length - 1; });
        history.replaceState(null, '', `#${panels[current].dataset.editorPanel}`);
        if (focus) {
            const heading = panels[current].querySelector('h2');
            heading.tabIndex = -1;
            heading.focus();
        }
    };
    steps.forEach((step, i) => step.addEventListener('click', () => show(i, true)));
    form.querySelector('[data-editor-prev]').addEventListener('click', () => show(current - 1, true));
    form.querySelector('[data-editor-next]').addEventListener('click', () => show(current + 1, true));
    form.querySelector('.editor-steps').hidden = false;
    form.querySelector('.editor-pagination')?.removeAttribute('hidden');
    form.querySelectorAll('[data-editor-prev], [data-editor-next]').forEach(button => { button.hidden = false; });
    const invalid = panels.findIndex(panel => panel.querySelector('.field-error'));
    const fragment = panels.findIndex(panel => `#${panel.dataset.editorPanel}` === location.hash);
    show(invalid >= 0 ? invalid : Math.max(0, fragment));

    const value = name => form.elements.namedItem(name)?.value || '';
    const L = JSON.parse(form.dataset.limits || '{}');
    const len = text => text.trim().length;
    const within = (n, range) => Array.isArray(range) && n >= range[0] && n <= range[1];
    const digits = text => Number(String(text).replace(/\D/g, '')) || 0;
    const lineCount = text => text.split(/\r?\n/).map(t => t.trim()).filter(Boolean).length;
    const evaluate = () => ({
        title: within(len(value('title')), L.title) && value('category_id') !== '',
        summary: within(len(value('summary')), L.summary),
        scope: within(len(value('scope')), L.scope),
        offer: within(digits(value('price_xof')), L.price_xof) && within(digits(value('delivery_days')), L.delivery_days) && within(Number(value('revisions_included')) || 0, L.revisions),
        deliverables: lineCount(value('deliverables')) >= 1,
        cover: !L.images_enabled || Number(L.image_count) >= 1,
        profile: !!L.profile_ok,
    });
    const refreshChecks = () => {
        const state = evaluate();
        const keys = Object.keys(state);
        form.querySelectorAll('[data-count]').forEach(el => {
            const n = len(value(el.dataset.count));
            el.textContent = `${n} / ${new Intl.NumberFormat('fr-FR').format(Number(el.dataset.max))}`;
            el.classList.toggle('over', n > Number(el.dataset.max));
        });
        let done = 0;
        form.querySelectorAll('[data-check]').forEach(li => {
            const ok = !!state[li.dataset.check];
            if (ok) done += 1;
            li.classList.toggle('ok', ok);
            li.classList.toggle('no', !ok);
            const sr = li.querySelector('[data-ck-sr]');
            if (sr) sr.textContent = ok ? ' : complet' : ' : à compléter';
            li.querySelector('use')?.setAttribute('href', ok ? '#i-check-circle' : '#i-warn');
        });
        const count = form.querySelector('[data-ck-count]');
        if (count) count.textContent = String(done);
        const bar = form.querySelector('[data-ck-bar]');
        if (bar) bar.style.width = `${Math.round(done / keys.length * 100)}%`;
        steps.forEach(step => {
            const missing = (step.dataset.stepKeys || '').split(',').filter(k => k && !state[k]).length;
            step.classList.toggle('is-done', missing === 0);
            step.classList.toggle('is-todo', missing > 0);
            const status = step.querySelector('[data-step-status]');
            if (status) status.textContent = missing === 0 ? 'Complet' : `${missing} point${missing > 1 ? 's' : ''} à compléter`;
        });
    };
    const preview = () => {
        const text = (key, content) => { form.querySelector(`[data-preview-${key}]`).textContent = content; };
        text('title', value('title') || 'Le titre de votre service');
        text('summary', value('summary'));
        text('category', form.elements.namedItem('category_id').selectedOptions[0]?.textContent || '');
        const price = Number(value('price_xof').replace(/\s/g, ''));
        text('price', price > 0 ? `${new Intl.NumberFormat('fr-CI').format(price)} FCFA` : 'Prix à renseigner');
        const days = Number(value('delivery_days'));
        text('delay', days > 0 ? `${days} jour${days > 1 ? 's' : ''}` : 'Délai à renseigner');
        const cover = form.querySelector('input[name="cover_image"]:checked');
        if (cover) {
            form.querySelector('[data-preview-image]').src = cover.dataset.coverSrc;
            form.querySelector('[data-preview-image]').hidden = false;
            form.querySelector('[data-preview-empty]').hidden = true;
        }
    };
    preview();
    refreshChecks();
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; preview(); refreshChecks(); });
    form.addEventListener('change', () => { dirty = true; preview(); refreshChecks(); });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
}
