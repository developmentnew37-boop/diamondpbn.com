export function initDynamicRadioGroup(cls, label_class, hookFunc) { // cls stands for class
    if (document.getElementsByClassName(`${cls}`) && document.getElementsByClassName(`${cls}`).length > 0) {
        console.log("start")
        let selectArticleOpt = document.getElementsByClassName(`${cls}`);
        Array.from(selectArticleOpt).forEach((item) => {
            item.addEventListener('change', (e) => {
                e.stopImmediatePropagation();
                // Reset all
                document.querySelectorAll(`.${label_class} > span`).forEach((span) => {
                    span.classList.remove('bg-orange-400', 'border-orange-500');
                    span.classList.add('bg-gray-100', 'border-gray-300');
                    const inner = span.querySelector('span');
                    if (inner) inner.classList.add('opacity-0');
                });

                // Highlight selected
                const label = e.target.closest('div')?.querySelector('label');
                if (label) {
                    const span = label.querySelector('span');
                    if (span) {
                        span.classList.replace('bg-gray-100', 'bg-orange-400');
                        span.classList.replace('border-gray-300', 'border-orange-500');
                        const inner = span.querySelector('span');
                        if (inner) inner.classList.remove('opacity-0');
                    }
                }
                if (typeof hookFunc === 'function') {
                    hookFunc(e.target.value, e.target);
                }

                return e.target.value;
            })
        })
    }
}
// close popup
export function closePopup(box, overlay, parent, timeout = 600, closeCallBack = null) {
    // start closing animation
    overlay.classList.remove('opacity-30');
    overlay.classList.add('opacity-0');

    box.classList.add('opacity-0', '-translate-y-[20%]');
    box.classList.remove('opacity-100', 'translate-y-0');

    // Wait for CSS transition to finish
    setTimeout(() => {
        parent.classList.add('hidden');
        overlay.classList.add('hidden');
        box.classList.add('hidden');

        if (typeof closeCallBack === 'function') closeCallBack();
    }, timeout);
}

// 🧩 Opens popup (main or nested)
export function initPopup(parent, overlay, box, close, timeout = 600, callback = null) {
    // make visible
    parent.classList.remove('hidden');
    overlay.classList.remove('hidden');
    box.classList.remove('hidden');

    // small delay for transition trigger
    setTimeout(() => {
        overlay.classList.replace('opacity-0', 'opacity-30');
        box.classList.remove('opacity-0', '-translate-y-[20%]');
        box.classList.add('opacity-100', 'translate-y-0');

        // Call callback AFTER animation completes
        if (typeof callback === 'function') callback();
    }, 20);

    // overlay click close
    overlay.addEventListener('click', (e) => {
        e.stopImmediatePropagation();
        closePopup(box, overlay, parent, timeout);
    }, { once: true });

    // close button click
    close.addEventListener('click', (e) => {
        e.stopImmediatePropagation();
        closePopup(box, overlay, parent, timeout);
    }, { once: true });
}

// 🧩 Handle nested popup initialization
export function nestedPop(items) {
    if (!items || items.length === 0) return;

    const nestedParent = document.querySelector('.dy-nested-parent-div');
    const nestedOverlay = document.querySelector('.dy-nested-set-overlay');
    const nestedPopBox = document.querySelector('#dy-nested-article-box');
    const nestedCloseBtn = document.querySelector('#dy-nested-close-btn');

    if (!nestedParent || !nestedOverlay || !nestedPopBox || !nestedCloseBtn) {
        console.warn('Nested popup elements not found in DOM.');
        return;
    }

    items.forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('Nested popup opening...');
            initPopup(nestedParent, nestedOverlay, nestedPopBox, nestedCloseBtn, 600);
        }, { once: true }); // ✅ Auto-removes after first use
    });
}

export function initMultiCheckbox(options) {
    const bulkCheckBox = document.getElementById(options.bulkSelector);
    const checkboxes = document.querySelectorAll('.' + options.itemClass);
    const hiddenInput = options.hiddenInput ? document.getElementById(options.hiddenInput) : null;
    const counterBox = options.counterSelector ? document.querySelector(options.counterSelector) : null;



    if (!bulkCheckBox || !checkboxes.length) return;

    let selectedValues = [];

    // Bulk toggle
    bulkCheckBox.addEventListener('change', () => {
        checkboxes.forEach(cb => cb.checked = bulkCheckBox.checked);
        updateValues();

    });

    // Individual toggle
    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            if (!cb.checked) bulkCheckBox.checked = false;
            else if ([...checkboxes].every(c => c.checked)) bulkCheckBox.checked = true;
            updateValues();
        });
    });

    // Update selection
    function updateValues() {
        selectedValues = [...checkboxes]
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        if (hiddenInput) hiddenInput.value = selectedValues.join(',');

        // 🔥 Update counter if enabled
        if (counterBox) {
            counterBox.textContent = `${selectedValues.length}`;
        }

        if (typeof options.onChange === "function") {
            options.onChange(selectedValues);
        }

    }
}

// just allowing numbers in input -- function

export function allowOnlyNumbers(numInp) {
    if (numInp.length) {
        Array.from(numInp).forEach((i) => {
            i.addEventListener("input", function () {
                this.value = this.value.replace(/[^0-9]/g, "");
            });
        });
    }
}



window.addEventListener('DOMContentLoaded', () => {
    if (document.querySelectorAll('.article_radio_opt')) {
        const contentRadioBtns = document.querySelectorAll('.article_radio_opt');
        let selectedValue = null;
        const selLabels = document.querySelectorAll('.select-label');
        const checkSpans = document.querySelectorAll('.check-span');


        // for redirecting 

        contentRadioBtns.forEach((radio) => {
            radio.addEventListener('change', (e) => {
                console.log("hello")
                // Reset all label borders
                selLabels.forEach((label) => {
                    label.classList.remove('border-[var(--primary-color)]');
                    label.classList.add('border-gray-200');
                });

                // Hide all check marks
                checkSpans.forEach((span) => {
                    span.classList.add('opacity-0');
                });

                // Style the selected one
                if (e.target.checked) {
                    const selectedLabel = e.target.closest('label');
                    const selectedSpan = selectedLabel.querySelector('.check-span');

                    if (selectedLabel) {
                        selectedLabel.classList.remove('border-gray-200');
                        selectedLabel.classList.add('border-[var(--primary-color)]');
                    }

                    if (selectedSpan) {
                        selectedSpan.classList.remove('opacity-0');
                    }

                    selectedValue = e.target.value
                    // console.log('Selected radio value:', e.target.value);
                }
            });
        });
        // for redirecting to page for simple generation

        if (document.getElementById('redirect_btn')) {
            const redirectBtn = document.getElementById('redirect_btn');

            redirectBtn.addEventListener('click', (e) => {
                if (selectedValue == null) return;
                if (selectedValue == 'manual_create') {
                    window.location.href = `/admin/article/create`;
                }
                if (selectedValue == 'bulk_upload') {
                    window.location.href = '/admin/article/upload/docx'
                }
            })
        }



    }; // Exit early if no radios found

    // radio button thing for article set creation

    let fileUploadInitialized = false;

    function fileUploadSystem(format) {
        // Prevent multiple initializations
        if (fileUploadInitialized) return;
        fileUploadInitialized = true;

        console.log("file function running");
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');
        const successCount = document.getElementById('successCount');
        const errorCount = document.getElementById('errorCount');
        const fileList = document.getElementById('fileList');
        const clearAllBtn = document.getElementById('clearAllBtn');
        const ButtonContainer = document.getElementById('buttons-container');
        let validFiles = [];
        let invalidCount = 0;
        const MAX_SIZE = 100 * 1024; // 100 KB

        // Refresh file list UI
        function updateFileList() {
            fileList.innerHTML = '';
            validFiles.forEach((file, index) => {
                const li = document.createElement('li');
                li.classList.add('flex', 'justify-between', 'items-center', 'bg-white', '!p-2', 'rounded', 'shadow-sm');

                const info = document.createElement('span');
                info.textContent = `📄 ${file.name} — ${Math.round(file.size / 1024)} KB`;

                const removeBtn = document.createElement('button');
                removeBtn.textContent = 'Remove';
                removeBtn.classList.add('text-red-500', 'hover:text-red-700', 'text-xs', 'font-semibold');
                removeBtn.addEventListener('click', () => {
                    validFiles.splice(index, 1);
                    updateFileList();
                });

                li.appendChild(info);
                li.appendChild(removeBtn);
                fileList.appendChild(li);
            });

            successCount.textContent = validFiles.length;
            errorCount.textContent = invalidCount;
            ButtonContainer.classList.toggle('hidden', validFiles.length === 0 && invalidCount === 0);
        }

        // Handle file selection
        function handleFiles(files) {
            [...files].forEach(file => {
                if (!format.includes(file.type)) {
                    invalidCount++;
                } else if (file.size > MAX_SIZE) {
                    invalidCount++;
                } else {
                    validFiles.push(file);
                }
            });

            updateFileList();
        }

        // Events
        dropzone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('border-orange-500', 'bg-orange-50');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('border-orange-500', 'bg-orange-50');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-orange-500', 'bg-orange-50');
            handleFiles(e.dataTransfer.files);
        });

        // Clear All Files
        clearAllBtn.addEventListener('click', () => {
            validFiles = [];
            invalidCount = 0;
            fileInput.value = ''; // Reset file input
            updateFileList();
        });
    }

    if (document.getElementsByClassName('article_set_radio_opt') && Array.from(document.getElementsByClassName('article_set_radio_opt')).length > 0) { // this are custom radio buttons shows pop 
        let articleSetRadioBtn = Array.from(document.getElementsByClassName('article_set_radio_opt'));
        let articleSetParent = document.querySelector('.set-parent-div');
        let setOverlay = document.querySelector('.set-overlay'); // this is for overlay present create campign page
        let closeBtn = document.getElementById('manual-article-set-close');
        let selectedValue = null;
        let currentRadio = null; // ✅ Track the currently selected radio
        const selLabels = document.querySelectorAll('.select-label');
        const checkSpans = document.querySelectorAll('.check-span');

        // ✅ Initialize file upload system only once
        // let fileUploadInitialized = false;

        function resetStyle() {
            selLabels.forEach((label) => {
                label.classList.remove('border-[var(--primary-color)]');
                label.classList.add('border-gray-200');
            });

            checkSpans.forEach((span) => {
                span.classList.add('opacity-0');
            });
        }

        // ✅ Close popup function (defined once, reused)
        function closePopup(displayBox, radio) {
            setOverlay.classList.replace('opacity-30', 'opacity-0');
            displayBox.classList.add('opacity-0', '-translate-y-[20%]');

            setTimeout(() => {
                articleSetParent.classList.add('hidden');
                setOverlay.classList.add('hidden');
                displayBox.classList.add('hidden');

                // ✅ Deselect the radio button properly
                if (radio) {
                    radio.checked = false;
                    currentRadio = null;
                }
                resetStyle();
            }, 600);
        }

        articleSetRadioBtn.forEach((radio) => {
            radio.addEventListener('change', (e) => {
                if (!e.target.checked) return;

                resetStyle();
                currentRadio = e.target; // ✅ Store reference to current radio

                // Style the selected one
                const selectedLabel = e.target.closest('label');
                const selectedSpan = selectedLabel.querySelector('.check-span');

                if (selectedLabel) {
                    selectedLabel.classList.remove('border-gray-200');
                    selectedLabel.classList.add('border-[var(--primary-color)]');
                }

                if (selectedSpan) {
                    selectedSpan.classList.remove('opacity-0');
                }

                selectedValue = e.target.value;

                // Hide all boxes
                if (document.getElementsByClassName('option-box')) {
                    let optBox = Array.from(document.getElementsByClassName('option-box'));
                    optBox.forEach((item) => {
                        item.classList.add('opacity-0', '-translate-y-[20%]', 'hidden');
                    });
                }

                if (document.getElementById(`${selectedValue}`)) {
                    let displayBox = document.getElementById(`${selectedValue}`);
                    let closeBtn = displayBox.querySelector('.close-pop');

                    articleSetParent.classList.remove('hidden');
                    setOverlay.classList.remove('hidden');
                    displayBox.classList.remove('hidden');

                    setTimeout(() => {
                        setOverlay.classList.replace('opacity-0', 'opacity-30');
                        displayBox.classList.remove('opacity-0', '-translate-y-[20%]');
                    }, 200);

                    // ✅ Initialize file upload only once
                    if (selectedValue == 'files-article-box' && !fileUploadInitialized) {
                        let format = [
                            'application/pdf',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'text/plain'
                        ];
                        fileUploadSystem(format);
                        fileUploadInitialized = true;
                    }

                    // ✅ Remove old listener before adding new one
                    let newCloseBtn = closeBtn.cloneNode(true);
                    closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);

                    // ✅ Add close button listener with proper radio deselection
                    newCloseBtn.addEventListener('click', () => {
                        closePopup(displayBox, currentRadio);
                    });
                }
            });
        });

        // ✅ Optional: Close on overlay click
        // setOverlay.addEventListener('click', () => {
        //     let openBox = document.querySelector('.option-box:not(.hidden)');
        //     if (openBox) {
        //         closePopup(openBox, currentRadio);
        //     }
        // });
        // closeBtn.addEventListener('click', () => {
        //     let openBox = document.querySelector('.option-box:not(.hidden)');
        //     if (openBox) {
        //         closePopup(openBox, currentRadio);
        //     }
        // });
        console.log("hello");  
        [setOverlay, closeBtn].forEach(el => {
            el.addEventListener('click', () => {
                const openBox = document.querySelector('.option-box:not(.hidden)');
                if (openBox) closePopup(openBox, currentRadio);
            });
        });

    }



    // add domain list page
    // working for choosing domains in set page

    if (document.getElementsByClassName('domain-add-opts')) {
        let btns = Array.from(document.getElementsByClassName('domain-add-opts'));
        let dMethodDivs = Array.from(document.getElementsByClassName('add-domains'));
        let methodType = document.getElementById('method-type');
        let showDomainCount = document.getElementById('selectSetDomainsCount');

        btns.forEach((item) => {
            item.addEventListener('click', function () {
                btns.forEach((t) => {
                    t.classList.remove('text-white', 'bg-black');
                    t.classList.add('bg-gray-200');
                })
                dMethodDivs.forEach((i) => {
                    i.classList.add('hidden', 'opacity-0', 'translate-y-[20px]')
                })
                let id = this.getAttribute('data-id');



                // just hidding the number one
                if (id == 'select-method') {
                    if (document.getElementById('selectDomainsCount')) {
                        document.getElementById('selectDomainsCount') ? document.getElementById('selectDomainsCount').classList.remove('hidden') : '';
                    }
                    methodType ? methodType.value = '0' : '';
                    showDomainCount ? showDomainCount.classList.remove('hidden') : ''
                } else {
                    if (document.getElementById('selectDomainsCount')) {
                        document.getElementById('selectDomainsCount') ? document.getElementById('selectDomainsCount').classList.add('hidden') : '';
                    }
                    methodType ? methodType.value = '1' : '';
                    showDomainCount ? showDomainCount.classList.add('hidden') : ''
                }
                if (document.getElementById(`${id}`)) {
                    let currentDiv = document.getElementById(`${id}`);
                    currentDiv.classList.remove('hidden');
                    setTimeout(() => {
                        currentDiv.classList.remove('opacity-0', 'translate-y-[20px]')
                    }, 100)
                }

                this.classList.add('text-white', 'bg-black');
                this.classList.remove('bg-gray-200');
            })
        })
    }


    // create campaigns page article-sel-opts & this is hook function






    // for closing nested box

    // utilty function close popup

    // 🧩 Utility: Close popup smoothly







});


