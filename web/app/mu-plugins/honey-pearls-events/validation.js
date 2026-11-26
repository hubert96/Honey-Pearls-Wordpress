(() => {
    const form = document.getElementById('hp-event-editor');
    if (!form) return;

    const fields = Array.from(form.querySelectorAll('.regular-text, textarea'));
    const summary = document.getElementById('hp-event-message');
    const touched = new Set();
    const messages = new Map();
    const required = {
        title: 'Enter the event name.',
        start: 'Enter the UK start date and time.',
        venue: 'Enter the venue and address.',
        price: 'Enter the price per guest (use 0 for a free event).',
        capacity: 'Enter the total number of seats.',
        menu: 'Enter the event menu.',
        allergens: 'Enter the allergen information.'
    };
    let publishing = true;
    let saving = false;

    function messageFor(field) {
        const { name, validity } = field;
        const value = field.value.trim();
        if (validity.badInput) return 'Complete this field using a valid ' +
            (field.type === 'number' ? 'number.' : 'date and time.');
        if (!value && (publishing || name === 'title') && required[name]) return required[name];
        if (name === 'capacity') {
            const min = Math.max(Number(form.dataset.minSeats), publishing ? 1 : 0);
            if (!/^\d+$/.test(value || '0') || Number(value) < min)
                return 'Enter a whole number of seats, at least ' + min + '.';
        }
        if (name === 'price' && value && !/^\d+(?:\.\d{1,2})?$/.test(value))
            return 'Enter a non-negative price with up to two decimal places.';
        if (name === 'close' && value) {
            const start = form.elements.namedItem('start').value;
            if (!start || value > start) return 'Choose a closing time at or before the event starts.';
        }
        return '';
    }

    function show(field, message) {
        const node = messages.get(field);
        if (node.textContent !== message) node.textContent = message;
        field.setAttribute('aria-invalid', String(Boolean(message)));
    }

    function update() {
        summary.textContent = '';
        for (const field of touched) show(field, messageFor(field));
    }

    for (const field of fields) {
        const node = document.createElement('span');
        node.id = 'hp-error-' + field.name;
        node.className = 'hp-event-error';
        node.setAttribute('aria-live', 'polite');
        field.closest('p').append(node);
        field.setAttribute('aria-describedby', node.id);
        messages.set(field, node);
        field.addEventListener('blur', () => { touched.add(field); update(); });
        field.addEventListener('input', () => { touched.add(field); update(); });
    }

    form.noValidate = true;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        const status = event.submitter?.value || 'publish';
        publishing = status === 'publish';
        for (const field of fields) touched.add(field);
        update();
        const invalid = fields.find(field => field.getAttribute('aria-invalid') === 'true');
        if (invalid) {
            summary.textContent = 'Please check the highlighted fields.';
            invalid.focus();
            return;
        }
        if (publishing && form.dataset.stockReady !== '1') {
            summary.textContent = 'Enable stock management and a positive stock hold time in WooCommerce before publishing.';
            return;
        }
        const data = new FormData(form);
        data.set('status', status);
        data.set('inline_validation', '1');
        saving = true;
        const buttons = form.querySelectorAll('button[name="status"]');
        buttons.forEach(button => { button.disabled = true; });
        try {
            const response = await fetch(form.getAttribute('action'), { method: 'POST', body: data });
            const result = await response.json();
            if (response.ok && result.success && result.data.redirect) {
                window.location.assign(result.data.redirect);
                return;
            }
            const field = fields.find(field => field.name === result.data?.field);
            if (field) {
                show(field, result.data.message);
                field.focus();
            } else {
                summary.textContent = result.data?.message || 'Could not save this event. Check your WordPress session.';
            }
        } catch {
            summary.textContent = 'Save could not be confirmed. Check the Events list in another tab before trying again.';
        } finally {
            saving = false;
            buttons.forEach(button => { button.disabled = false; });
        }
    });
})();
