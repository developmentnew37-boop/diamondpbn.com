(function () {
    document.querySelectorAll('[data-lcb-view]').forEach(function (wrapper) {
        const btn = wrapper.querySelector('[data-lcb-toggle-btn]');
        const panel = wrapper.querySelector('[data-lcb-panel]');
        const label = wrapper.querySelector('[data-lcb-toggle-label]');
        const icon = wrapper.querySelector('[data-lcb-toggle-icon]');
        if (!btn || !panel || !label || !icon) {
            return;
        }

        const storageKey = 'lcb-view-open-' + (wrapper.dataset.lcbKey || 'default');
        const showLabel = wrapper.dataset.lcbShowLabel || 'Show billing';
        const hideLabel = wrapper.dataset.lcbHideLabel || 'Hide billing';

        function setOpen(open) {
            panel.classList.toggle('is-collapsed', !open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            label.textContent = open ? hideLabel : showLabel;
            icon.textContent = open ? 'expand_less' : 'expand_more';

            try {
                localStorage.setItem(storageKey, open ? '1' : '0');
            } catch (e) {}
        }

        let open = false;
        try {
            open = localStorage.getItem(storageKey) === '1';
        } catch (e) {}

        setOpen(open);

        btn.addEventListener('click', function () {
            setOpen(panel.classList.contains('is-collapsed'));
        });
    });
})();
