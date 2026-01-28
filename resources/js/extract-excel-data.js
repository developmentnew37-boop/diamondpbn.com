
let excelData = null;
let columns = [];
let selectedColumns = new Set();
// checking data is extracted or noot
let isDataExtract = false;
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const columnSelector = document.getElementById('columnSelector');
const dropdownButton = document.getElementById('dropdownButton');
const dropdownMenu = document.getElementById('dropdownMenu');
const selectedColumnsText = document.getElementById('selectedColumnsText');
const selectedColumnsList = document.getElementById('selectedColumnsList');
const dataPreview = document.getElementById('dataPreview');
const actionButtons = document.getElementById('actionButtons');
const results = document.getElementById('results');
let extractedDomainsData = [];

// selecting upload domain button
let uploadBtn = document.getElementById('uploadDomains');

// selecting Domains Category
let domainCategory = document.getElementById('domain_category');


/***************************************************************************************************/

/** setting the category value of hidden fields **/
let hiddenCatHolder = document.getElementById('hidden_category_id');

domainCategory.addEventListener('change', (e) => {
    hiddenCatHolder.value = e.target.value
})

/** ** ** ** ** ** ** ** ends here ** ** ** ** ** ** ** **/

// Click to upload
dropZone.addEventListener('click', () => fileInput.click());

// Drag and drop events
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('border-orange-500', 'bg-orange-50');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-orange-500', 'bg-orange-50');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-orange-500', 'bg-orange-50');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFile(files[0]);
    }
});

// File input change
fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        handleFile(e.target.files[0]);
    }
});

// Dropdown toggle
dropdownButton.addEventListener('click', () => {
    dropdownMenu.classList.toggle('hidden');
});

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (!dropdownButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
        dropdownMenu.classList.add('hidden');
    }
});

// Select All button
document.getElementById('selectAllBtn').addEventListener('click', (e) => {
    e.stopPropagation();
    columns.forEach((col, idx) => {
        selectedColumns.add(idx);
    });
    updateCheckboxes();
    updateSelectedDisplay();
});

// Clear All button
document.getElementById('clearAllBtn').addEventListener('click', (e) => {
    e.stopPropagation();
    selectedColumns.clear();
    updateCheckboxes();
    updateSelectedDisplay();
});

// Process button
document.getElementById('processButton').addEventListener('click', processData);

// Cancel button
document.getElementById('cancelButton').addEventListener('click', resetForm);

function handleFile(file) {
    if (!file.name.match(/\.(xlsx|xls)$/)) {
        alert('Please upload an Excel file (.xlsx or .xls)');
        return;
    }

    fileName.textContent = file.name;
    fileInfo.classList.remove('hidden');

    const reader = new FileReader();
    reader.onload = (e) => {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, {
            type: 'array'
        });
        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
        excelData = XLSX.utils.sheet_to_json(firstSheet, {
            header: 1
        });

        if (excelData.length > 0) {
            columns = excelData[0];
            populateDropdown();
            showPreview();
            columnSelector.classList.remove('hidden');
        }
    };
    reader.readAsArrayBuffer(file);

}

function populateDropdown() {
    const container = document.getElementById('columnCheckboxes');
    container.innerHTML = '';

    columns.forEach((col, index) => {
        const colName = col || `Column ${index + 1}`;
        const div = document.createElement('div');
        div.className = 'flex items-center !px-3 !py-2 hover:bg-gray-50 rounded cursor-pointer';
        div.innerHTML = `
                    <input type="checkbox" id="col_${index}" value="${index}" 
                           class="h-4 w-4 text-orange-600 rounded border-gray-300 focus:ring-orange-500">
                    <label for="col_${index}" class="!ml-3 text-sm text-gray-700 cursor-pointer flex-1">
                        ${colName}
                    </label>
                `;

        const checkbox = div.querySelector('input');
        checkbox.addEventListener('change', (e) => {
            if (e.target.checked) {
                selectedColumns.add(index);
            } else {
                selectedColumns.delete(index);
            }
            updateSelectedDisplay();
        });

        div.addEventListener('click', (e) => {
            if (e.target !== checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
            }
        });

        container.appendChild(div);
    });
}

function updateCheckboxes() {
    columns.forEach((col, index) => {
        const checkbox = document.getElementById(`col_${index}`);
        if (checkbox) {
            checkbox.checked = selectedColumns.has(index);
        }
    });
}

function updateSelectedDisplay() {
    if (selectedColumns.size === 0) {
        selectedColumnsText.textContent = 'Select columns...';
        selectedColumnsList.innerHTML = '';
        actionButtons.classList.add('hidden');
        return;
    }

    selectedColumnsText.textContent = `${selectedColumns.size} column(s) selected`;

    // Display selected columns as tags
    selectedColumnsList.innerHTML = '';
    Array.from(selectedColumns).sort((a, b) => a - b).forEach(index => {
        const colName = columns[index] || `Column ${index + 1}`;
        const tag = document.createElement('span');
        tag.className =
            'inline-flex items-center gap-1 !px-3 !py-1 bg-orange-100 text-orange-800 text-sm rounded-full';
        tag.innerHTML = `
                    ${colName}
                    <button class="hover:bg-orange-200 rounded-full !p-0.5" onclick="removeColumn(${index})">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                `;
        selectedColumnsList.appendChild(tag);
    });

    actionButtons.classList.remove('hidden');
    results.classList.add('hidden');
}

window.removeColumn = function (index) {
    selectedColumns.delete(index);
    updateCheckboxes();
    updateSelectedDisplay();
};

function showPreview() {
    const tableHeader = document.getElementById('tableHeader');
    const tableBody = document.getElementById('tableBody');

    // Header
    tableHeader.innerHTML = '<tr>' +
        columns.map(col =>
            `<th class="!px-6 !py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">${col || 'Column'}</th>`
        ).join('') +
        '</tr>';

    // Body (first 5 rows)
    tableBody.innerHTML = '';
    const rowsToShow = Math.min(5, excelData.length - 1);
    for (let i = 1; i <= rowsToShow; i++) {
        const row = document.createElement('tr');
        row.innerHTML = excelData[i].map(cell =>
            `<td class="!px-6 !py-4 whitespace-nowrap text-sm text-gray-900">${cell || ''}</td>`
        ).join('');
        tableBody.appendChild(row);
    }

    dataPreview.classList.remove('hidden');
}

// function processData() {
//     if (selectedColumns.size === 0) {
//         alert('Please select at least one column');
//         return;
//     }

//     const extractedArray = [];
//     const selectedIndices = Array.from(selectedColumns).sort((a, b) => a - b);
//     // Process each row (skip header row)
//     for (let i = 1; i < excelData.length; i++) {
//         const rowData = {};
//         let hasData = false;

//         selectedIndices.forEach(colIndex => {
//             const colName = columns[colIndex] || `Column_${colIndex + 1}`;
//             const value = excelData[i][colIndex];

//             if (value !== undefined && value !== null && value !== '') {
//                 hasData = true;
//             }

//             rowData[colName] = value || '';
//         });

//         // Only add rows that have at least some data
//         if (hasData) {
//             extractedArray.push(rowData);
//         }

//     }
//     extractedDomainsData = extractedArray;
//     isDataExtract = true;
//     console.log(JSON.stringify(extractedDomainsData));

//     // Show results
//     document.getElementById('recordCount').textContent = extractedArray.length;
//     document.getElementById('columnCount').textContent = selectedColumns.size;
//     document.getElementById('extractedData').textContent = JSON.stringify(extractedArray, null, 2);
//     results.classList.remove('hidden');

//     // Log to console
//     console.log('Extracted Data:', extractedArray);

//     // You can send this data to your backend here
//     // Example: sendToBackend(extractedArray);
// }

function processData() {
    if (selectedColumns.size === 0) {
        alert('Please select at least one column');
        return;
    }

    const extractedArray = [];
    const selectedIndices = Array.from(selectedColumns).sort((a, b) => a - b);

    for (let i = 1; i < excelData.length; i++) {
        let rowData = {};
        let hasValidData = false;

        selectedIndices.forEach(colIndex => {
            let colName = columns[colIndex] || `Column_${colIndex + 1}`;
            let value = excelData[i][colIndex];

            // Convert undefined to ""
            value = value == null ? "" : String(value).trim();

            if (value !== "") {
                hasValidData = true;       // Only true if real value exists
            }

            rowData[colName] = value;
        });

        if (hasValidData) {                // Only push non-empty rows
            extractedArray.push(rowData);
        }
    }

    extractedDomainsData = extractedArray;
    isDataExtract = true;

    console.log("Final cleaned data:", extractedDomainsData);
    document.getElementById('recordCount').textContent = extractedArray.length;
    document.getElementById('columnCount').textContent = selectedColumns.size;
    document.getElementById('extractedData').textContent = JSON.stringify(extractedArray, null, 2);
    results.classList.remove('hidden');
}


function resetForm() {
    excelData = null;
    columns = [];
    selectedColumns.clear();
    fileInput.value = '';
    fileInfo.classList.add('hidden');
    columnSelector.classList.add('hidden');
    dataPreview.classList.add('hidden');
    actionButtons.classList.add('hidden');
    results.classList.add('hidden');
    selectedColumnsText.textContent = 'Select columns...';
    selectedColumnsList.innerHTML = '';
}

uploadBtn.addEventListener('click', async () => {
    try {

        // ================= VALIDATIONS =================
        if (!isDataExtract) {
            alert("Please extract the data from file first.");
            return;
        }

        if (!Array.isArray(extractedDomainsData)) {
            alert("Invalid file data format.");
            return;
        }

        if (extractedDomainsData.length < 1) {
            alert("File does not contain enough data to upload.");
            return;
        }

        if (domainCategory.value === '') {
            alert("Please select a category first.");

            requestAnimationFrame(() => {
                domainCategory.scrollIntoView({ behavior: "smooth", block: "center" });
                domainCategory.focus({ preventScroll: true });  // enhances attention
            });

            return;
        }


        // ================= API REQUEST =================
        uploadBtn.querySelector('.loader').classList.remove('hidden'); // show loader

        const response = await fetch('/api/admin/domain/bulk/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                data: extractedDomainsData,
                category: domainCategory.value
            })
        });

        const result = await response.json();
        uploadBtn.querySelector('.loader').classList.add('hidden'); // hide loader

        console.log(result);

        if (!response.ok) {
            alert(result?.message || "Upload failed. Please try again.");
            return;
        }

        alert("Domains uploaded successfully!");
        window.location='/admin/domain/select/category';
        // Optional → reset UI
        // extractedDomainsData = [];
        // domainCategory.value = "";

    } catch (error) {
        console.error("Upload error:", error);

        // hide loader if error occurs to avoid stuck spinner
        uploadBtn.querySelector('.loader').classList.add('hidden');

        alert("Something went wrong. Please check console for details.");
    }

});
