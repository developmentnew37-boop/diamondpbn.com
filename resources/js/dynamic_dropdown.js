document.addEventListener('DOMContentLoaded', () => {

    // CustomDropdown Class
    class CustomDropdown {
        constructor(container) {
            this.container = container;
            this.selectedValue = '';
            this.isOpen = false;
            this.currentOffset = 20;

            // Get elements within this specific dropdown
            this.selectBtn = container.querySelector('[data-select-btn]');
            this.selectText = container.querySelector('[data-select-text]');
            this.chevron = container.querySelector('[data-chevron]');
            this.dropdown = container.querySelector('[data-dropdown]');
            this.searchInput = container.querySelector('[data-search-input]');
            this.optionsList = container.querySelector('[data-options-list]');
            this.hiddenInput = container.querySelector('[data-hidden-input]');

            // Initialize
            this.bindEvents();
            this.bindOptionEvents();
            this.initializeSelected()
        }

        // Add this new method
        initializeSelected() {
            // Check if hidden input has a pre-filled value (for edit page)
            // Check if hidden input has a pre-filled value (for edit page)
            const preSelectedValue = this.hiddenInput.value;

            if (preSelectedValue) {
                // Use setTimeout to ensure DOM is fully ready
                setTimeout(() => {
                    // Find the option with this value
                    const selectedOption = this.optionsList.querySelector(`[data-option-item][data-value="${preSelectedValue}"]`);

                    if (selectedOption) {
                        const label = selectedOption.getAttribute('data-label');
                        // Manually set the display text and value
                        this.selectedValue = preSelectedValue;
                        this.selectText.textContent = label;
                        this.selectText.className = 'text-black';

                        // Update the option styling
                        const options = this.optionsList.querySelectorAll('[data-option-item]');
                        options.forEach((opt) => {
                            const span = opt.querySelector('span');
                            if (opt.getAttribute('data-value') === preSelectedValue) {
                                span.className = 'text-sm text-[var(--primary-color)] font-medium';
                                // Add check mark if not exists
                                if (!opt.querySelector('svg')) {
                                    opt.innerHTML +=
                                        '<svg class="w-4 h-4 text-[var(--primary-color)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                                }
                            }
                        });
                    } else {
                        console.warn('Option not found for value:', preSelectedValue);
                    }
                }, 0);
            }
        }

        bindEvents() {
            // Toggle dropdown
            this.selectBtn.onclick = (e) => {
                e.stopPropagation();
                this.isOpen ? this.closeDropdown() : this.openDropdown();
            };

            // Search/Filter
            this.searchInput.oninput = () => {
                this.filterOptions(this.searchInput.value);
            };

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!this.container.contains(e.target)) {
                    this.closeDropdown();
                }
            });
        }

        bindOptionEvents() {
            const options = this.optionsList.querySelectorAll('[data-option-item]');

            options.forEach((option) => {
                option.onclick = () => {
                    const value = option.getAttribute('data-value');
                    const label = option.getAttribute('data-label');
                    this.selectOption(value, label);
                };
            });
        }

        selectOption(value, label) {
            this.selectedValue = value;
            this.selectText.textContent = label;
            this.selectText.className = 'text-black';
            this.hiddenInput.value = value;

            // Update selected styling
            const options = this.optionsList.querySelectorAll('[data-option-item]');

            options.forEach((opt) => {
                const span = opt.querySelector('span');
                if (opt.getAttribute('data-value') === value) {
                    span.className = 'text-sm text-[var(--primary-color)] font-medium';
                    // Add check mark if not exists
                    if (!opt.querySelector('svg')) {
                        opt.innerHTML +=
                            '<svg class="w-4 h-4 text-[var(--primary-color)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                    }
                } else {
                    span.className = 'text-sm';
                    // Remove check mark
                    const svg = opt.querySelector('svg');
                    if (svg) svg.remove();
                }
            });

            this.closeDropdown();
        }

        openDropdown() {
            this.isOpen = true;
            this.dropdown.classList.remove('hidden');
            this.dropdown.classList.add('dropdown-menu-animate');
            this.chevron.style.transform = 'rotate(180deg)';
            this.searchInput.focus();
        }

        closeDropdown() {
            this.isOpen = false;
            this.dropdown.classList.add('hidden');
            this.chevron.style.transform = 'rotate(0deg)';
            this.searchInput.value = '';
            this.filterOptions('');
        }

        filterOptions(searchTerm) {
            const options = this.optionsList.querySelectorAll('[data-option-item]');

            // If there are no options at all, don't show search results message
            if (options.length === 0) {
                return;
            }

            let visibleCount = 0;

            options.forEach((option) => {
                const label = option.getAttribute('data-label').toLowerCase();
                if (label.includes(searchTerm.toLowerCase())) {
                    option.style.display = 'flex';
                    visibleCount++;
                } else {
                    option.style.display = 'none';
                }
            });

            // Show "no results" message only if there ARE options but none match
            const noResults = this.optionsList.querySelector('[data-no-results]');
            if (visibleCount === 0 && !noResults) {
                const div = document.createElement('div');
                div.setAttribute('data-no-results', '');
                div.className = '!px-4 !py-8 text-center text-gray-400 text-sm';
                div.textContent = 'No options found';
                this.optionsList.appendChild(div);
            } else if (visibleCount > 0 && noResults) {
                noResults.remove();
            }
        }
    }

    // Initialize all dropdowns on the page
    const dropdownContainers = document.querySelectorAll('[data-dropdown-container]');
    dropdownContainers.forEach((container) => {
        new CustomDropdown(container);
    });
});