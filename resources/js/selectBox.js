
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

    function reconcileSelectedWithDOM() {
        const domValues = new Set(getCheckboxes().map(cb => String(cb.value)));
        selected = selected.filter(v => domValues.has(String(v)));
        localStorage.setItem(localStorageKey, JSON.stringify(selected));
    }

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
        reconcileSelectedWithDOM();
        const checkboxes = getCheckboxes();
        checkboxes.forEach(cb => {
            const isSelected = selected.includes(cb.value);
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
            if (!selected.includes(cb.value)) selected.push(cb.value);
            highlight(cb, true);
        } else {
            selected = selected.filter(v => v !== cb.value);
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

        if (!this.checked) {
            checkboxes.forEach(cb => {
                cb.checked = false;
                highlight(cb, false);
            });
            selected = [];
            save();
            return;
        }

        const limit = maxQty ? Math.min(maxQty, checkboxes.length) : checkboxes.length;
        selected = [];
        let count = 0;

        checkboxes.forEach(cb => {
            if (count < limit) {
                cb.checked = true;
                highlight(cb, true);
                selected.push(cb.value);
                count++;
            } else {
                cb.checked = false;
                highlight(cb, false);
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

        unchecked.sort(() => Math.random() - 0.5).slice(0, selectCount).forEach(cb => {
            cb.checked = true;
            highlight(cb, true);
            if (!selected.includes(cb.value)) selected.push(cb.value);
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
