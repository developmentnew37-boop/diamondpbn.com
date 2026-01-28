/**
 * Universal Select Manager
 * Supports: manual, random, select-all, highlight, persistence
 */
// function initSelectManager(options) {
//     const {
//         checkboxSelector,     // All checkboxes selector
//         selectAllSelector,    // Select All checkbox
//         countSelector,        // Where to display selected count
//         randomBtnSelector,    // Random select button (optional)
//         inputSelector,        // Input for random count (optional)
//         localStorageKey,      // Storage key name
//         highlightClass = "bg-blue-100", // Highlight class
//         parentSelector = "div,tr",        // What to highlight around checkbox
//         enableRandom = true
//     } = options;

//     const checkboxes = document.querySelectorAll(checkboxSelector);
//     const selectAll = document.querySelector(selectAllSelector);
//     const countDisplay = document.querySelector(countSelector);
//     const randomBtn = randomBtnSelector ? document.querySelector(randomBtnSelector) : '';
//     const randomInput = inputSelector ? document.querySelector(inputSelector) : '';

//     let selected = JSON.parse(localStorage.getItem(localStorageKey)) || [];

//     // --- Apply saved selections ---
//     checkboxes.forEach(cb => {
//         if (selected.includes(cb.value)) {
//             cb.checked = true;
//             highlight(cb, true);
//         }
//     });
//     updateCount();

//     // --- Helper: highlight parent (div or tr) ---
//     function highlight(cb, active) {
//         const parent = cb.closest(parentSelector);
//         if (parent) parent.classList.toggle(highlightClass, active);
//     }

//     // --- Helper: save + count ---
//     function save() {
//         localStorage.setItem(localStorageKey, JSON.stringify(selected));
//         updateCount();
//     }

//     function updateCount() {
//         if (countDisplay) countDisplay.textContent = selected.length;
//         if (selectAll) {
//             const allChecked = Array.from(checkboxes).every(cb => cb.checked);
//             selectAll.checked = allChecked;
//         }
//     }

//     // --- Handle checkbox toggle ---
//     checkboxes.forEach(cb => {
//         cb.addEventListener("change", () => {
//             if (cb.checked) {
//                 if (!selected.includes(cb.value)) selected.push(cb.value);
//             } else {
//                 selected = selected.filter(v => v !== cb.value);
//             }
//             highlight(cb, cb.checked);
//             save();
//         });
//     });

//     // --- Select All toggle ---
//     if (selectAll) {
//         selectAll.addEventListener("change", function () {
//             checkboxes.forEach(cb => {
//                 cb.checked = this.checked;
//                 highlight(cb, this.checked);
//             });
//             selected = this.checked ? Array.from(checkboxes).map(cb => cb.value) : [];
//             save();
//         });
//     }

//     // --- Random Select (optional) ---
//     if (enableRandom && randomBtn && randomInput) {
//         randomBtn.addEventListener("click", () => {
//             const qty = parseInt(randomInput.value, 10);
//             if (!qty || qty <= 0) return alert("Enter a valid number");

//             const unchecked = Array.from(checkboxes).filter(cb => !cb.checked);
//             if (unchecked.length === 0) return alert("All items already selected!");

//             const selectCount = Math.min(qty, unchecked.length);
//             const randomItems = unchecked.sort(() => Math.random() - 0.5).slice(0, selectCount);

//             randomItems.forEach(cb => {
//                 cb.checked = true;
//                 selected.push(cb.value);
//                 highlight(cb, true);
//             });
//             save();
//             randomInput.value = "";
//         });
//     }
// }


// -----------------------------------------------------------------------------------------------------------------

// export function initSelectManager(options) {
//     const {
//         checkboxSelector,
//         selectAllSelector,
//         countSelector,
//         randomBtnSelector,
//         inputSelector,
//         localStorageKey,
//         highlightClass = "bg-blue-100",
//         parentSelector = "div,tr",
//         enableRandom = true,
//         maxQuantityInputSelector // optional input for max quantity
//     } = options;

//     const checkboxes = document.querySelectorAll(checkboxSelector);
//     const selectAll = document.querySelector(selectAllSelector);
//     const countDisplay = document.querySelector(countSelector);
//     const randomBtn = randomBtnSelector ? document.querySelector(randomBtnSelector) : '';
//     const randomInput = inputSelector ? document.querySelector(inputSelector) : '';
//     const maxQuantityInput = maxQuantityInputSelector ? document.querySelector(maxQuantityInputSelector) : null;

//     let selected = JSON.parse(localStorage.getItem(localStorageKey)) || [];
//     let maxQty = maxQuantityInput ? parseInt(maxQuantityInput.value, 10) : null;

//     // Apply saved selections
//     checkboxes.forEach(cb => {
//         if (selected.includes(cb.value)) {
//             cb.checked = true;
//             highlight(cb, true);
//         }
//     });
//     updateCount();

//     function highlight(cb, active) {
//         const parent = cb.closest(parentSelector);
//         if (parent) parent.classList.toggle(highlightClass, active);
//     }

//     function save() {
//         localStorage.setItem(localStorageKey, JSON.stringify(selected));
//         updateCount();
//     }

//     function updateCount() {
//         if (countDisplay) countDisplay.textContent = selected.length;
//         if (selectAll) {
//             const allChecked = Array.from(checkboxes).every(cb => cb.checked);
//             selectAll.checked = allChecked;
//         }
//     }

//     // Update maxQty if input changes
//     if (maxQuantityInput) {
//         maxQuantityInput.addEventListener("input", () => {
//             maxQty = parseInt(maxQuantityInput.value, 10) || null;
//         });
//     }

//     // Handle checkbox toggle
//     checkboxes.forEach(cb => {
//         cb.addEventListener("change", () => {
//             if (cb.checked) {
//                 if (maxQty && selected.length >= maxQty) {
//                     alert(`You can select up to ${maxQty} items only!`);
//                     cb.checked = false;
//                     return;
//                 }
//                 if (!selected.includes(cb.value)) selected.push(cb.value);
//             } else {
//                 selected = selected.filter(v => v !== cb.value);
//             }
//             highlight(cb, cb.checked);
//             save();
//         });
//     });

//     // Select All toggle
//     // if (selectAll) {
//     //     selectAll.addEventListener("change", function () {
//     //         const allValues = Array.from(checkboxes).map(cb => cb.value);
//     //         if (maxQty && allValues.length > maxQty && this.checked) {
//     //             alert(`Cannot select more than ${maxQty} items`);
//     //             this.checked = false;
//     //             return;
//     //         }
//     //         checkboxes.forEach(cb => {
//     //             cb.checked = this.checked;
//     //             highlight(cb, this.checked);
//     //         });
//     //         selected = this.checked ? allValues : [];
//     //         save();
//     //     });
//     // }
//     if (selectAll) {
//         selectAll.addEventListener("change", function () {
//             const maxQty = maxQuantityInput ? parseInt(maxQuantityInput.value, 10) : null;

//             if (!this.checked) {
//                 // user unchecked select-all → unselect all
//                 checkboxes.forEach(cb => {
//                     cb.checked = false;
//                     highlight(cb, false);
//                 });
//                 selected = [];
//                 save();
//                 return;
//             }

//             // User clicked Select All → select only maxQty items
//             let count = 0;
//             selected = [];

//             checkboxes.forEach(cb => {
//                 if (count < maxQty) {
//                     cb.checked = true;
//                     selected.push(cb.value);
//                     highlight(cb, true);
//                     count++;
//                 } else {
//                     cb.checked = false;   // force remain unchecked
//                     highlight(cb, false);
//                 }
//             });

//             save();
//             alert(`Only ${maxQty} domains selected because that is your selected quantity.`);
//         });
//     }


//     // Random select
//     if (enableRandom && randomBtn && randomInput) {
//         randomBtn.addEventListener("click", () => {
//             const qty = parseInt(randomInput.value, 10);
//             if (!qty || qty <= 0) return alert("Enter a valid number");

//             const unchecked = Array.from(checkboxes).filter(cb => !cb.checked);
//             if (unchecked.length === 0) return alert("All items already selected!");

//             let remaining = maxQty ? maxQty - selected.length : qty;
//             const selectCount = Math.min(qty, unchecked.length, remaining);

//             if (maxQty && selected.length + qty > maxQty) {
//                 alert(`Cannot select more than ${maxQty} items`);
//                 return;
//             }

//             const randomItems = unchecked.sort(() => Math.random() - 0.5).slice(0, selectCount);
//             randomItems.forEach(cb => {
//                 cb.checked = true;
//                 selected.push(cb.value);
//                 highlight(cb, true);
//             });
//             save();
//             randomInput.value = "";
//         });
//     }
// }

// export function initSelectManager(options) {
//     const {
//         checkboxSelector,
//         selectAllSelector,
//         countSelector,
//         randomBtnSelector,
//         inputSelector,
//         localStorageKey,
//         highlightClass = "bg-blue-100",
//         parentSelector = "div,tr",
//         enableRandom = true,
//         maxQuantityInputSelector
//     } = options;

//     const selectAll = document.querySelector(selectAllSelector);
//     const countDisplay = document.querySelector(countSelector);
//     const randomBtn = randomBtnSelector ? document.querySelector(randomBtnSelector) : null;
//     const randomInput = inputSelector ? document.querySelector(inputSelector) : null;
//     const maxQuantityInput = maxQuantityInputSelector ? document.querySelector(maxQuantityInputSelector) : null;

//     // ✅ Dynamic checkboxes (important when rows are added later)
//     const getCheckboxes = () => Array.from(document.querySelectorAll(checkboxSelector)); // selecting all the check boxes

//     let selected = JSON.parse(localStorage.getItem(localStorageKey)) || [];

//     const getMaxQty = () => {
//         if (!maxQuantityInput) return null; // 3
//         const v = parseInt(maxQuantityInput.value, 10); // 3
//         return Number.isFinite(v) && v > 0 ? v : null; // 3
//     };

//     function highlight(cb, active) {
//         const parent = cb.closest(parentSelector);
//         if (parent) parent.classList.toggle(highlightClass, active);
//     }

//     function updateCount() {
//         if (countDisplay) countDisplay.textContent = selected.length;

//         if (selectAll) {
//             const checkboxes = getCheckboxes();

//             // ✅ CRITICAL FIX: if there are no checkboxes, SelectAll must be false
//             if (checkboxes.length === 0) {
//                 selectAll.checked = false;
//                 return;
//             }

//             const allChecked = checkboxes.every(cb => cb.checked);
//             selectAll.checked = allChecked;
//         }
//     }

//     function save() {
//         localStorage.setItem(localStorageKey, JSON.stringify(selected));
//         updateCount();
//     }
//     function reconcileSelectedWithDOM() {
//         const currentIds = new Set(getCheckboxes().map(cb => String(cb.value)));
//         const before = selected.length;

//         selected = selected.filter(v => currentIds.has(String(v)));

//         // if removed ghost items, persist
//         if (selected.length !== before) {
//             localStorage.setItem(localStorageKey, JSON.stringify(selected));
//         }
//     }


//     // ✅ Apply saved selections to current DOM
//     function applySavedSelections() {
//         reconcileSelectedWithDOM(); // ✅ IMPORTANT (removes ghost selected)
//         const checkboxes = getCheckboxes();
//         checkboxes.forEach(cb => {
//             const isSelected = selected.includes(cb.value);
//             cb.checked = isSelected;
//             highlight(cb, isSelected);
//         });
//         updateCount();
//     }

//     // ✅ Update maxQty if input changes (keeps limit accurate)
//     if (maxQuantityInput) {
//         maxQuantityInput.addEventListener("input", () => {
//             updateCount();
//         });
//     }

//     // ✅ Handle checkbox toggle using EVENT DELEGATION
//     // This makes it work even if rows are appended later (or DataTables redraws)
//     document.addEventListener("change", (e) => {
//         const cb = e.target;
//         if (!(cb instanceof HTMLInputElement)) return;
//         if (!cb.matches(checkboxSelector)) return;

//         const maxQty = getMaxQty();

//         if (cb.checked) {
//             if (maxQty && selected.length >= maxQty) {
//                 alert(`You can select up to ${maxQty} items only!`);
//                 cb.checked = false;
//                 highlight(cb, false);
//                 return;
//             }
//             if (!selected.includes(cb.value)) selected.push(cb.value);
//             highlight(cb, true);
//         } else {
//             selected = selected.filter(v => v !== cb.value);
//             highlight(cb, false);
//         }

//         save();
//     });

//     // ✅ Select All toggle (respects maxQty)
//     if (selectAll) {
//         selectAll.addEventListener("change", function () {
//             const checkboxes = getCheckboxes();
//             const maxQty = getMaxQty();

//             // If none exist, don't allow selectAll to stay checked
//             if (checkboxes.length === 0) {
//                 this.checked = false;
//                 return;
//             }

//             if (!this.checked) {
//                 // unselect all
//                 checkboxes.forEach(cb => {
//                     cb.checked = false;
//                     highlight(cb, false);
//                 });
//                 selected = [];
//                 save();
//                 return;
//             }

//             // select up to maxQty (or all if null)
//             const limit = maxQty ? Math.min(maxQty, checkboxes.length) : checkboxes.length;

//             selected = [];
//             let count = 0;

//             checkboxes.forEach(cb => {
//                 if (count < limit) {
//                     cb.checked = true;
//                     highlight(cb, true);
//                     selected.push(cb.value);
//                     count++;
//                 } else {
//                     cb.checked = false;
//                     highlight(cb, false);
//                 }
//             });

//             save();
//             if (maxQty) alert(`Only ${limit} items selected because that is your selected quantity.`);
//         });
//     }

//     // ✅ Random select (respects maxQty)
//     if (enableRandom && randomBtn && randomInput) {
//         randomBtn.addEventListener("click", () => {
//             const qty = parseInt(randomInput.value, 10);
//             if (!qty || qty <= 0) return alert("Enter a valid number");

//             const checkboxes = getCheckboxes();
//             const maxQty = getMaxQty();

//             const unchecked = checkboxes.filter(cb => !cb.checked);
//             if (unchecked.length === 0) return alert("All items already selected!");

//             const remaining = maxQty ? (maxQty - selected.length) : qty;
//             const selectCount = Math.min(qty, unchecked.length, remaining);

//             if (maxQty && selectCount <= 0) {
//                 alert(`Cannot select more than ${maxQty} items`);
//                 return;
//             }

//             const randomItems = unchecked.sort(() => Math.random() - 0.5).slice(0, selectCount);
//             randomItems.forEach(cb => {
//                 cb.checked = true;
//                 highlight(cb, true);
//                 if (!selected.includes(cb.value)) selected.push(cb.value);
//             });

//             save();
//             randomInput.value = "";
//         });
//     }

//     // ✅ Run initial sync (even if currently empty)
//     applySavedSelections();

//     // ✅ Return refresh for Step-2 (call after you append rows)
//     return {
//         refresh: applySavedSelections
//     };
// }

export function initSelectManager(options) {
    const {
        checkboxSelector,
        selectAllSelector,
        countSelector,
        randomBtnSelector,
        inputSelector,
        localStorageKey,
        highlightClass = "bg-blue-100",
        parentSelector = "div,tr",
        enableRandom = true,
        maxQuantityInputSelector
    } = options;

    const selectAll = document.querySelector(selectAllSelector);
    const countDisplay = document.querySelector(countSelector);
    const randomBtn = randomBtnSelector ? document.querySelector(randomBtnSelector) : null;
    const randomInput = inputSelector ? document.querySelector(inputSelector) : null;
    const maxQuantityInput = maxQuantityInputSelector ? document.querySelector(maxQuantityInputSelector) : null;

    const getCheckboxes = () => Array.from(document.querySelectorAll(checkboxSelector));

    // ✅ IMPORTANT: kill previous listeners for SAME localStorageKey
    window.__selectMgrHandlers = window.__selectMgrHandlers || {};
    if (window.__selectMgrHandlers[localStorageKey]) {
        const old = window.__selectMgrHandlers[localStorageKey];
        document.removeEventListener("change", old.onDocChange, true);
        if (selectAll) selectAll.removeEventListener("change", old.onSelectAllChange);
        if (enableRandom && randomBtn) randomBtn.removeEventListener("click", old.onRandomClick);
    }

    let selected = JSON.parse(localStorage.getItem(localStorageKey)) || [];

    const getMaxQty = () => {
        if (!maxQuantityInput) return null;
        const v = parseInt(maxQuantityInput.value, 10);
        return Number.isFinite(v) && v > 0 ? v : null;
    };

    function highlight(cb, active) {
        const parent = cb.closest(parentSelector);
        if (parent) parent.classList.toggle(highlightClass, active);
    }

    // function reconcileSelectedWithDOM() {
    //     const domValues = new Set(getCheckboxes().map(cb => String(cb.value)));
    //     selected = selected.filter(v => domValues.has(String(v)));
    //     localStorage.setItem(localStorageKey, JSON.stringify(selected));
    // }

    function updateCount() {
        if (countDisplay) countDisplay.textContent = selected.length;

        if (selectAll) {
            const checkboxes = getCheckboxes();
            if (checkboxes.length === 0) {
                selectAll.checked = false;
                return;
            }
            selectAll.checked = checkboxes.every(cb => cb.checked);
        }
    }

    function save() {
        localStorage.setItem(localStorageKey, JSON.stringify(selected));
        updateCount();
    }

    function applySavedSelections() {
        // reconcileSelectedWithDOM();
        const checkboxes = getCheckboxes();
        checkboxes.forEach(cb => {
            // const isSelected = selected.includes(cb.value);
            const isSelected = selected.includes(String(cb.value));

            cb.checked = isSelected;
            highlight(cb, isSelected);
        });
        updateCount();
    }

    // ✅ Document change handler (delegation)
    const onDocChange = (e) => {
        const cb = e.target;
        if (!(cb instanceof HTMLInputElement)) return;
        if (!cb.matches(checkboxSelector)) return;

        const maxQty = getMaxQty();

        if (cb.checked) {
            if (maxQty && selected.length >= maxQty) {
                alert(`You can select up to ${maxQty} items only!`);
                cb.checked = false;
                highlight(cb, false);
                return;
            }
            // if (!selected.includes(cb.value)) selected.push(cb.value);
            if (!selected.includes(String(cb.value))) {
                selected.push(String(cb.value));
            }

            highlight(cb, true);
        } else {
            // selected = selected.filter(v => v !== cb.value);
            selected = selected.filter(v => v !== String(cb.value));

            highlight(cb, false);
        }

        save();
    };

    document.addEventListener("change", onDocChange, true);

    // ✅ Select all handler
    const onSelectAllChange = function () {
        const checkboxes = getCheckboxes();
        const maxQty = getMaxQty();

        if (checkboxes.length === 0) {
            this.checked = false;
            return;
        }

        // if (!this.checked) {
        //     checkboxes.forEach(cb => {
        //         cb.checked = false;
        //         highlight(cb, false);
        //     });
        //     selected = [];
        //     save();
        //     return;
        // }
        if (!this.checked) {
            checkboxes.forEach(cb => {
                cb.checked = false;
                highlight(cb, false);
                selected = selected.filter(v => v !== String(cb.value));
            });
            save();
            return;
        }


        const limit = maxQty ? Math.min(maxQty, checkboxes.length) : checkboxes.length;
        // selected = [];
        let count = 0;

        // checkboxes.forEach(cb => {
        //     if (count < limit) {
        //         cb.checked = true;
        //         highlight(cb, true);
        //         selected.push(cb.value);
        //         count++;
        //     } else {
        //         cb.checked = false;
        //         highlight(cb, false);
        //     }
        // });

        checkboxes.forEach(cb => {
            if (count < limit) {
                cb.checked = true;
                highlight(cb, true);

                if (!selected.includes(String(cb.value))) {
                    selected.push(String(cb.value));
                }

                count++;
            } else {
                cb.checked = false;
                highlight(cb, false);
                selected = selected.filter(v => v !== String(cb.value));
            }
        });


        save();
    };

    if (selectAll) selectAll.addEventListener("change", onSelectAllChange);

    // ✅ Random
    const onRandomClick = () => {
        const qty = parseInt(randomInput?.value || "0", 10);
        if (!qty || qty <= 0) return alert("Enter a valid number");

        const checkboxes = getCheckboxes();
        const maxQty = getMaxQty();

        const unchecked = checkboxes.filter(cb => !cb.checked);
        const remaining = maxQty ? (maxQty - selected.length) : qty;
        const selectCount = Math.min(qty, unchecked.length, remaining);

        if (maxQty && selectCount <= 0) {
            alert(`Cannot select more than ${maxQty} items`);
            return;
        }

        // unchecked.sort(() => Math.random() - 0.5).slice(0, selectCount).forEach(cb => {
        //     cb.checked = true;
        //     highlight(cb, true);
        //     if (!selected.includes(cb.value)) selected.push(cb.value);
        // });

        unchecked.sort(() => Math.random() - 0.5)
            .slice(0, selectCount)
            .forEach(cb => {
                const value = String(cb.value);
                cb.checked = true;
                highlight(cb, true);
                if (!selected.includes(value)) selected.push(value);
            });


        save();
        if (randomInput) randomInput.value = "";
    };

    if (enableRandom && randomBtn && randomInput) randomBtn.addEventListener("click", onRandomClick);

    // ✅ store handlers so next init can remove them
    window.__selectMgrHandlers[localStorageKey] = {
        onDocChange,
        onSelectAllChange,
        onRandomClick
    };

    // ✅ public helpers
    function clear() {
        selected = [];
        localStorage.removeItem(localStorageKey);

        const checkboxes = getCheckboxes();
        checkboxes.forEach(cb => {
            cb.checked = false;
            highlight(cb, false);
        });

        if (selectAll) selectAll.checked = false;
        updateCount();
    }

    applySavedSelections();

    return {
        refresh: applySavedSelections,
        clear
    };
}


window.articleSelectMgr = initSelectManager({
    checkboxSelector: '.articles',
    selectAllSelector: '#selectAll',
    countSelector: '#selectedCount',
    randomBtnSelector: '#randomSelector',
    inputSelector: '#randomSelect',
    localStorageKey: 'selectArticles',
    highlightClass: '!bg-blue-100',
    parentSelector: 'tr',
    maxQuantityInputSelector: "#post-quantity",
    enableRandom: true
});

window.domainSelectMgr = initSelectManager({
    checkboxSelector: '.domains',
    selectAllSelector: '#selectAllDomains',
    countSelector: '#selectedDomainCount',
    localStorageKey: 'selectDomains',
    highlightClass: '!bg-blue-100',
    parentSelector: 'tr',
    maxQuantityInputSelector: "#post-quantity",
    enableRandom: false
});


window.domainSetSelectMgr = initSelectManager({
    checkboxSelector: '.setdomains',
    selectAllSelector: '#selectAllSetDomains',
    countSelector: '#selectedSetDomainCount',
    randomBtnSelector: '',
    inputSelector: '',
    localStorageKey: 'selectSetDomains',
    highlightClass: '!bg-blue-100',
    parentSelector: 'tr',
    maxQuantityInputSelector: "#post-quantity",
    enableRandom: false
});

// ** here campaigns custom select ends here **

// ** sidebar campaign data will come here

window.sideBarRandomDomainSelMgr = initSelectManager({
    checkboxSelector: '.sidebar_domains',
    selectAllSelector: '#selectAllSidebarDomains',
    countSelector: '#selectedSideBarDomainCount',
    randomBtnSelector: '',
    inputSelector: '',
    localStorageKey: 'selectSidebarDomains',
    highlightClass: '!bg-blue-100',
    parentSelector: 'tr',
    maxQuantityInputSelector: "#sidebar-quantity",
    enableRandom: false
});

// selectAllSidebarDomains
// sidebar_domains
// selectedSideBarDomainCount
// selectSidebarDomains // localStorage Key
// sidebar-quantity

window.sideBarDomainSetMgr = initSelectManager({
    checkboxSelector: '.setdomains',
    selectAllSelector: '#selectAllSidebarSetDomains',
    countSelector: '#selectedSideBarSetDomainCount',
    randomBtnSelector: '',
    inputSelector: '',
    localStorageKey: 'selectSidebarSetDomains',
    highlightClass: '!bg-blue-100',
    parentSelector: 'tr',
    maxQuantityInputSelector: "#sidebar-quantity",
    enableRandom: false
});


// ** it is for sidebar domains select
// selectAllSidebarSetDomains
// siderBarSetDomains
// selectedSideBarSetDomainCount
// selectSidebarSetDomains
// sidebar-quantity
// it is used for random domains so user can select


// ****** Hidden Links Data starts ********* //

//
//
window.HRandomDomainSelMgr = initSelectManager({
    checkboxSelector: '.sidebar_domains',
    selectAllSelector: '#selectAllHiddenDomains',
    countSelector: '#selectedHiddenDomainCount',
    randomBtnSelector: '',
    inputSelector: '',
    localStorageKey: 'selectHDomains',
    highlightClass: '!bg-blue-100',
    parentSelector: 'tr',
    maxQuantityInputSelector: "#hidden-quantity",
    enableRandom: false
});

// //

// // for sets hidden links

window.HDomainSetMgr = initSelectManager({
    checkboxSelector: '.setdomains',
    selectAllSelector: '#selectAllHiddenSetDomains',
    countSelector: '#selectedHiddenSetDomainCount',
    randomBtnSelector: '',
    inputSelector: '',
    localStorageKey: 'selectHSetDomains',
    highlightClass: '!bg-blue-100',
    parentSelector: 'tr',
    maxQuantityInputSelector: "#hidden-quantity",
    enableRandom: false
});
