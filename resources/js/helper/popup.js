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
