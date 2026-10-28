(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const timers = new WeakMap();

    document.querySelectorAll('.domain-row-check').forEach((button) => {
        button.addEventListener('click', () => checkRow(button));
    });

    async function checkRow(button) {
        if (button.disabled) {
            return;
        }

        const cell = document.querySelector(`[data-domain-status="${button.dataset.domainId}"]`);
        const badge = cell?.querySelector('.domain-status-badge');
        const note = cell?.querySelector('.domain-status-note');

        if (cell) {
            clearTimer(cell);
        }

        button.disabled = true;
        button.classList.add('is-checking');
        button.setAttribute('aria-label', 'Checking');

        try {
            const response = await fetch(button.dataset.checkUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await readJson(response);

            if (data && data.saved === true && badge) {
                const connected = data.connected === true;
                badge.textContent = connected ? 'connected' : 'disconnected';
                badge.classList.toggle('bg-green-100', connected);
                badge.classList.toggle('text-green-600', connected);
                badge.classList.toggle('bg-red-100', !connected);
                badge.classList.toggle('text-red-600', !connected);
            }

            const message = data && typeof data.message === 'string' && data.message !== ''
                ? data.message
                : 'Could not check this domain.';
            const tone = data && data.saved === true
                ? (data.connected === true ? 'ok' : 'warn')
                : 'error';
            showNote(cell, note, message, tone);
        } catch (error) {
            showNote(cell, note, 'Could not check this domain.', 'error');
        } finally {
            button.disabled = false;
            button.classList.remove('is-checking');
            button.setAttribute('aria-label', 'Check');
        }
    }

    function showNote(cell, note, message, tone) {
        if (!cell || !note) {
            return;
        }

        const icon = note.querySelector('.domain-status-note-icon');
        const text = note.querySelector('.domain-status-note-text');
        if (!text) {
            return;
        }

        clearTimer(cell);
        note.classList.remove('is-ok', 'is-warn', 'is-error', 'hidden');
        note.classList.add(tone === 'ok' ? 'is-ok' : (tone === 'error' ? 'is-error' : 'is-warn'));
        if (icon) {
            icon.textContent = tone === 'ok' ? 'check_circle' : 'warning';
        }
        text.textContent = message;
        note.title = message;

        const timer = window.setTimeout(() => {
            text.textContent = '';
            if (icon) {
                icon.textContent = '';
            }
            note.removeAttribute('title');
            note.classList.add('hidden');
            timers.delete(cell);
            refreshSearch();
        }, 4000);

        timers.set(cell, timer);
    }

    function clearTimer(cell) {
        const existing = timers.get(cell);
        if (!existing) {
            return;
        }

        window.clearTimeout(existing);
        timers.delete(cell);
    }

    function refreshSearch() {
        const search = document.getElementById('search_category');
        if (search && search.value.trim().length >= 3) {
            search.dispatchEvent(new Event('input'));
        }
    }

    async function readJson(response) {
        const text = await response.text();

        try {
            const data = JSON.parse(text);
            return data && typeof data === 'object' ? data : null;
        } catch (error) {
            return null;
        }
    }
})();
