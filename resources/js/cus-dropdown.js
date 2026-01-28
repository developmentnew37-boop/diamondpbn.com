document.addEventListener('DOMContentLoaded', function () {
    var selectedValue = '';
    var isOpen = false;
    var currentOffset = 20; // Track how many items loaded


    // Get elements
    var selectBtn = document.getElementById('selectBtn'); // button
    var selectText = document.getElementById('selectText'); // span present in button
    var chevron = document.getElementById('chevron'); // svg in button
    var dropdown = document.getElementById('dropdown'); // simply selecting the dropwdown
    var searchInput = document.getElementById('searchInput'); // simple search input
    var optionsList = document.getElementById('optionsList'); // containinig the option list
    var hiddenInput = document.getElementById('hidden-category'); // hidden inp that takes the value

    // Initialize - bind click events to existing options
    function bindOptionEvents() {
        var options = document.querySelectorAll('.option-item');
        options.forEach(function (option) {
            option.onclick = function () {
                var value = this.getAttribute('data-value');
                var label = this.getAttribute('data-label');
                selectOption(value, label);
            };
        });
    }

    // Select option
    function selectOption(value, label) {
        selectedValue = value;
        selectText.textContent = label;
        selectText.className = 'text-black';
        hiddenInput.value = value;

        // Update selected styling
        var options = document.querySelectorAll('.option-item');
        options.forEach(function (opt) {
            var span = opt.querySelector('span');
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
                var svg = opt.querySelector('svg');
                if (svg) svg.remove();
            }
        });

        closeDropdown();
    }

    // Open dropdown
    function openDropdown() {
        isOpen = true;
        dropdown.classList.remove('hidden');
        dropdown.classList.add('dropdown-menu-animate');
        chevron.style.transform = 'rotate(180deg)';
        searchInput.focus();
    }

    // Close dropdown
    function closeDropdown() {
        isOpen = false;
        dropdown.classList.add('hidden');
        chevron.style.transform = 'rotate(0deg)';
        searchInput.value = '';
        filterOptions('');
    }

    // Toggle dropdown
    if (selectBtn) {
        selectBtn.onclick = function (e) {
            e.stopPropagation();
            if (isOpen) {
                closeDropdown();
            } else {
                openDropdown();
            }
        };
    }


    // Search/Filter
    function filterOptions(searchTerm) {
        var options = document.querySelectorAll('.option-item');
        var visibleCount = 0;

        options.forEach(function (option) {
            var label = option.getAttribute('data-label').toLowerCase();
            if (label.indexOf(searchTerm.toLowerCase()) > -1) {
                option.style.display = 'flex';
                visibleCount++;
            } else {
                option.style.display = 'none';
            }
        });

        // Show "no results" message
        var noResults = document.getElementById('noResults');
        if (visibleCount === 0 && !noResults) {
            var div = document.createElement('div');
            div.id = 'noResults';
            div.className = '!px-4 !py-8 text-center text-gray-400 text-sm';
            div.textContent = 'No options found';
            optionsList.appendChild(div);
        } else if (visibleCount > 0 && noResults) {
            noResults.remove();
        }
    }

    if (searchInput) {
        searchInput.oninput = function () {
            filterOptions(this.value);
        };
    }



    // Close on outside click
    document.onclick = function (e) {
        if (!selectBtn.contains(e.target) && !dropdown.contains(e.target)) {
            closeDropdown();
        }
    };

    // Initialize
    bindOptionEvents();


});
