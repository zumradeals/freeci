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
    form.querySelector('.editor-pagination').hidden = false;
    const invalid = panels.findIndex(panel => panel.querySelector('.field-error'));
    const fragment = panels.findIndex(panel => `#${panel.dataset.editorPanel}` === location.hash);
    show(invalid >= 0 ? invalid : Math.max(0, fragment));

    const value = name => form.elements.namedItem(name)?.value || '';
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
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; preview(); });
    form.addEventListener('change', () => { dirty = true; preview(); });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
}
