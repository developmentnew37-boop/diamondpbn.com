/**
 * Toggle show/hide for password and OTP fields in auth forms.
 */
(function () {
    function isVisible(input) {
        return input.type === 'text';
    }

    function syncToggleButton(button, input) {
        const visible = isVisible(input);
        const showIcon = button.querySelector('[data-icon-show]');
        const hideIcon = button.querySelector('[data-icon-hide]');

        showIcon?.classList.toggle('auth-secret-icon-hidden', visible);
        hideIcon?.classList.toggle('auth-secret-icon-hidden', !visible);
        button.setAttribute('aria-label', visible ? 'Hide value' : 'Show value');
        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    }

    function initField(wrapper) {
        const input = wrapper.querySelector('[data-secret-input]');
        const button = wrapper.querySelector('[data-secret-toggle]');

        if (!input || !button) {
            return;
        }

        syncToggleButton(button, input);

        button.addEventListener('click', function () {
            input.type = isVisible(input) ? 'password' : 'text';
            syncToggleButton(button, input);
            input.focus();
        });
    }

    document.querySelectorAll('.auth-secret-field').forEach(initField);
})();
