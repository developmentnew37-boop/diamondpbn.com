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

//     const getCheckboxes = () => Array.from(document.querySelectorAll(checkboxSelector));

//     // ✅ IMPORTANT: kill previous listeners for SAME localStorageKey
//     window.__selectMgrHandlers = window.__selectMgrHandlers || {};
//     if (window.__selectMgrHandlers[localStorageKey]) {
//         const old = window.__selectMgrHandlers[localStorageKey];
//         document.removeEventListener("change", old.onDocChange, true);
//         if (selectAll) selectAll.removeEventListener("change", old.onSelectAllChange);
//         if (enableRandom && randomBtn) randomBtn.removeEventListener("click", old.onRandomClick);
//     }

//     let selected = JSON.parse(localStorage.getItem(localStorageKey)) || [];

//     const getMaxQty = () => {
//         if (!maxQuantityInput) return null;
//         const v = parseInt(maxQuantityInput.value, 10);
//         return Number.isFinite(v) && v > 0 ? v : null;
//     };

//     function highlight(cb, active) {
//         const parent = cb.closest(parentSelector);
//         if (parent) parent.classList.toggle(highlightClass, active);
//     }

//     function reconcileSelectedWithDOM() {
//         const domValues = new Set(getCheckboxes().map(cb => String(cb.value)));
//         selected = selected.filter(v => domValues.has(String(v)));
//         localStorage.setItem(localStorageKey, JSON.stringify(selected));
//     }

//     function updateCount() {
//         if (countDisplay) countDisplay.textContent = selected.length;

//         if (selectAll) {
//             const checkboxes = getCheckboxes();
//             if (checkboxes.length === 0) {
//                 selectAll.checked = false;
//                 return;
//             }
//             selectAll.checked = checkboxes.every(cb => cb.checked);
//         }
//     }

//     function save() {
//         localStorage.setItem(localStorageKey, JSON.stringify(selected));
//         updateCount();
//     }

//     function applySavedSelections() {
//         reconcileSelectedWithDOM();
//         const checkboxes = getCheckboxes();
//         checkboxes.forEach(cb => {
//             const isSelected = selected.includes(cb.value);
//             cb.checked = isSelected;
//             highlight(cb, isSelected);
//         });
//         updateCount();
//     }

//     // ✅ Document change handler (delegation)
//     const onDocChange = (e) => {
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
//     };

//     document.addEventListener("change", onDocChange, true);

//     // ✅ Select all handler
//     const onSelectAllChange = function () {
//         const checkboxes = getCheckboxes();
//         const maxQty = getMaxQty();

//         if (checkboxes.length === 0) {
//             this.checked = false;
//             return;
//         }

//         if (!this.checked) {
//             checkboxes.forEach(cb => {
//                 cb.checked = false;
//                 highlight(cb, false);
//             });
//             selected = [];
//             save();
//             return;
//         }

//         const limit = maxQty ? Math.min(maxQty, checkboxes.length) : checkboxes.length;
//         selected = [];
//         let count = 0;

//         checkboxes.forEach(cb => {
//             if (count < limit) {
//                 cb.checked = true;
//                 highlight(cb, true);
//                 selected.push(cb.value);
//                 count++;
//             } else {
//                 cb.checked = false;
//                 highlight(cb, false);
//             }
//         });

//         save();
//     };

//     if (selectAll) selectAll.addEventListener("change", onSelectAllChange);

//     // ✅ Random
//     const onRandomClick = () => {
//         const qty = parseInt(randomInput?.value || "0", 10);
//         if (!qty || qty <= 0) return alert("Enter a valid number");

//         const checkboxes = getCheckboxes();
//         const maxQty = getMaxQty();

//         const unchecked = checkboxes.filter(cb => !cb.checked);
//         const remaining = maxQty ? (maxQty - selected.length) : qty;
//         const selectCount = Math.min(qty, unchecked.length, remaining);

//         if (maxQty && selectCount <= 0) {
//             alert(`Cannot select more than ${maxQty} items`);
//             return;
//         }

//         unchecked.sort(() => Math.random() - 0.5).slice(0, selectCount).forEach(cb => {
//             cb.checked = true;
//             highlight(cb, true);
//             if (!selected.includes(cb.value)) selected.push(cb.value);
//         });

//         save();
//         if (randomInput) randomInput.value = "";
//     };

//     if (enableRandom && randomBtn && randomInput) randomBtn.addEventListener("click", onRandomClick);

//     // ✅ store handlers so next init can remove them
//     window.__selectMgrHandlers[localStorageKey] = {
//         onDocChange,
//         onSelectAllChange,
//         onRandomClick
//     };

//     // ✅ public helpers
//     function clear() {
//         selected = [];
//         localStorage.removeItem(localStorageKey);

//         const checkboxes = getCheckboxes();
//         checkboxes.forEach(cb => {
//             cb.checked = false;
//             highlight(cb, false);
//         });

//         if (selectAll) selectAll.checked = false;
//         updateCount();
//     }

//     applySavedSelections();

//     return {
//         refresh: applySavedSelections,
//         clear
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
        try {
            const raw = JSON.parse(localStorage.getItem(localStorageKey) || "[]");
            selected = Array.isArray(raw)
                ? [...new Set(raw.map((v) => String(v)).filter(Boolean))]
                : [];
        } catch (_) {
            selected = [];
        }
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
        try {
            const raw = JSON.parse(localStorage.getItem(localStorageKey) || "[]");
            selected = Array.isArray(raw)
                ? [...new Set(raw.map((v) => String(v)).filter(Boolean))]
                : [];
        } catch (_) {
            selected = [];
        }

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


        const currentPageIds = checkboxes.map(cb => String(cb.value));
        const selectedOutsidePage = selected.filter(v => !currentPageIds.includes(v));
        const selectedOnPage = selected.filter(v => currentPageIds.includes(v));

        // Remaining slots considering other pages first
        const remaining = maxQty
            ? Math.max(0, maxQty - selectedOutsidePage.length)
            : checkboxes.length;

        const allowOnPage = maxQty ? Math.min(remaining, checkboxes.length) : checkboxes.length;
        let kept = 0;

        checkboxes.forEach(cb => {
            const value = String(cb.value);
            const wasOnPageSelected = selectedOnPage.includes(value);
            if (kept < allowOnPage) {
                cb.checked = true;
                highlight(cb, true);
                if (!selected.includes(value)) selected.push(value);
                kept++;
            } else {
                cb.checked = false;
                highlight(cb, false);
                if (wasOnPageSelected) {
                    selected = selected.filter(v => v !== value);
                }
            }
        });

        if (maxQty && selectedOutsidePage.length + checkboxes.length > maxQty) {
            alert(`Cannot select all on this page. Max allowed is ${maxQty}.`);
        }

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
