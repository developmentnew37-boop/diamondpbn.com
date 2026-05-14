// Invoice Generator JavaScript
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('invoice-form');
    const productsTbody = document.getElementById('products-tbody');
    const addProductBtn = document.getElementById('add-product-btn');
    const logoInput = document.getElementById('logo-input');
    const logoUploadArea = document.getElementById('logo-upload-area');
    const logoPreview = document.getElementById('logo-preview');
    const uploadText = document.getElementById('upload-text');
    const taxRateInput = document.getElementById('tax-rate');
    const discountInput = document.getElementById('discount');
    const generateBtn = document.getElementById('generate-btn');
    const customColorPicker = document.getElementById('custom-color-picker');
    const currencySelect = document.getElementById('currency-select');
    const customTotalInput = document.getElementById('custom-total');

    let productRowCount = 0;
    let currentCurrencySymbol = '$';

    // Initialize with one product row
    addProductRow();

    // Theme color selection
    initializeThemeSelector();

    // Add product row button
    addProductBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (productRowCount < 50) {
            addProductRow();
        } else {
            alert('Maximum 50 items allowed per invoice');
        }
    });

    // Logo upload handling
    logoUploadArea.addEventListener('click', () => {
        logoInput.click();
    });

    logoInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            // Validate file type
            if (!file.type.match('image.*')) {
                alert('Please select an image file');
                logoInput.value = '';
                return;
            }

            // Validate file size (2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('File size must be less than 2MB');
                logoInput.value = '';
                return;
            }

            // Preview logo
            const reader = new FileReader();
            reader.onload = (event) => {
                logoPreview.src = event.target.result;
                logoPreview.classList.add('active');
                uploadText.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    });

    // Tax rate and discount change handlers
    taxRateInput.addEventListener('input', debounce(updateTotals, 300));
    discountInput.addEventListener('input', debounce(updateTotals, 300));

    // Currency change handler
    currencySelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        currentCurrencySymbol = selectedOption.getAttribute('data-symbol') || '$';
        updateTotals();
    });

    // Form submission with AJAX
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Validate at least one product
        const rows = productsTbody.querySelectorAll('.product-row');
        if (rows.length === 0) {
            alert('Please add at least one item to the invoice');
            return;
        }

        // Validate all product rows have data
        let hasEmptyRow = false;
        rows.forEach(row => {
            const title = row.querySelector('.item-title').value.trim();
            const price = row.querySelector('.item-price').value;
            const quantity = row.querySelector('.item-quantity').value;

            if (!title || !price || !quantity) {
                hasEmptyRow = true;
            }
        });

        if (hasEmptyRow) {
            alert('Please fill in all item details or remove empty rows');
            return;
        }

        // Show loading state
        generateBtn.disabled = true;
        document.getElementById('btn-text').style.display = 'none';
        document.getElementById('btn-loader').style.display = 'inline';

        try {
            // Submit form via AJAX
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                // Download PDF from base64
                const linkSource = `data:application/pdf;base64,${result.pdf}`;
                const downloadLink = document.createElement('a');
                downloadLink.href = linkSource;
                downloadLink.download = result.filename;
                downloadLink.click();

                // Show success message
                alert(result.message || 'Invoice generated successfully!');

                // Reset form
                resetForm();
            } else {
                alert('Error generating invoice. Please try again.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error generating invoice. Please check your connection and try again.');
        } finally {
            // Reset button state
            generateBtn.disabled = false;
            document.getElementById('btn-text').style.display = 'inline';
            document.getElementById('btn-loader').style.display = 'none';
        }
    });

    // Functions
    function addProductRow() {
        productRowCount++;
        const row = document.createElement('tr');
        row.className = 'product-row';
        row.innerHTML = `
            <td>
                <input type="text"
                       name="items[${productRowCount}][title]"
                       class="item-title"
                       placeholder="Item description"
                       required>
            </td>
            <td>
                <input type="number"
                       name="items[${productRowCount}][price]"
                       class="item-price"
                       placeholder="0.00"
                       min="0"
                       step="0.01"
                       required>
            </td>
            <td>
                <input type="number"
                       name="items[${productRowCount}][quantity]"
                       class="item-quantity"
                       placeholder="1"
                       min="1"
                       value="1"
                       required>
            </td>
            <td class="text-right">
                <span class="item-subtotal">$0.00</span>
            </td>
            <td class="text-center">
                <button type="button" class="btn-remove-row" onclick="removeProductRow(this)">
                    Remove
                </button>
            </td>
        `;

        productsTbody.appendChild(row);

        // Add event listeners for calculation
        const priceInput = row.querySelector('.item-price');
        const quantityInput = row.querySelector('.item-quantity');

        priceInput.addEventListener('input', debounce(updateTotals, 300));
        quantityInput.addEventListener('input', debounce(updateTotals, 300));

        updateTotals();
    }

    window.removeProductRow = function(button) {
        const row = button.closest('.product-row');
        const rows = productsTbody.querySelectorAll('.product-row');

        if (rows.length > 1) {
            row.remove();
            updateTotals();
        } else {
            alert('Invoice must have at least one item');
        }
    };

    function updateTotals() {
        let subtotal = 0;

        // Calculate subtotal for each row
        const rows = productsTbody.querySelectorAll('.product-row');
        rows.forEach(row => {
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const quantity = parseInt(row.querySelector('.item-quantity').value) || 0;
            const rowSubtotal = price * quantity;

            row.querySelector('.item-subtotal').textContent = formatCurrency(rowSubtotal);
            subtotal += rowSubtotal;
        });

        // Get discount
        const discount = parseFloat(discountInput.value) || 0;
        const subtotalAfterDiscount = subtotal - discount;

        // Calculate tax
        const taxRate = parseFloat(taxRateInput.value) || 0;
        const taxAmount = (subtotalAfterDiscount * taxRate) / 100;

        // Calculate total
        const total = subtotalAfterDiscount + taxAmount;

        // Update display with current currency symbol
        document.getElementById('subtotal-display').textContent = formatCurrency(subtotal);
        document.getElementById('discount-display').textContent = formatCurrency(discount);
        document.getElementById('tax-display').textContent = formatCurrency(taxAmount);
        document.getElementById('total-display').textContent = formatCurrency(total);
    }

    function formatCurrency(amount) {
        const formatted = amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        return currentCurrencySymbol + formatted;
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function resetForm() {
        // Reset form fields
        form.reset();

        // Reset logo preview
        logoPreview.classList.remove('active');
        logoPreview.src = '';
        uploadText.style.display = 'block';
        logoInput.value = '';

        // Remove all product rows
        productsTbody.innerHTML = '';
        productRowCount = 0;

        // Add one fresh product row
        addProductRow();

        // Reset totals display
        document.getElementById('subtotal-display').textContent = '$0.00';
        document.getElementById('discount-display').textContent = '$0.00';
        document.getElementById('tax-display').textContent = '$0.00';
        document.getElementById('total-display').textContent = '$0.00';

        // Reset theme selection to first option
        const themeOptions = document.querySelectorAll('.theme-option');
        themeOptions.forEach((opt, index) => {
            if (index === 0) {
                opt.classList.add('active');
                const radio = opt.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            } else {
                opt.classList.remove('active');
            }
        });

        // Remove custom color hidden input if exists
        const customColorInput = document.getElementById('custom-theme-color');
        if (customColorInput) {
            customColorInput.remove();
        }
    }

    function initializeThemeSelector() {
        const themeOptions = document.querySelectorAll('.theme-option');
        const customColorOption = document.querySelector('.custom-color-option');

        // Handle theme option clicks
        themeOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove active class from all options
                themeOptions.forEach(opt => opt.classList.remove('active'));

                // Add active class to clicked option
                this.classList.add('active');

                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                }
            });
        });

        // Handle custom color picker
        if (customColorPicker) {
            customColorPicker.addEventListener('change', function() {
                const customColor = this.value;

                // Remove active class from all preset options
                themeOptions.forEach(opt => {
                    if (!opt.classList.contains('custom-color-option')) {
                        opt.classList.remove('active');
                    }
                });

                // Add active class to custom option
                customColorOption.classList.add('active');

                // Update or create hidden input for custom color
                let customColorInput = document.querySelector('input[name="theme_color"][value="' + customColor + '"]');
                if (!customColorInput) {
                    // Uncheck all preset radio buttons
                    document.querySelectorAll('.theme-option input[type="radio"]').forEach(radio => {
                        radio.checked = false;
                    });

                    // Create or update hidden input for custom color
                    let hiddenInput = document.getElementById('custom-theme-color');
                    if (!hiddenInput) {
                        hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.id = 'custom-theme-color';
                        hiddenInput.name = 'theme_color';
                        form.appendChild(hiddenInput);
                    }
                    hiddenInput.value = customColor;
                }
            });

            // Trigger change on initial load if custom color is different
            customColorPicker.addEventListener('input', function() {
                customColorOption.style.background = this.value;
            });
        }
    }
});
