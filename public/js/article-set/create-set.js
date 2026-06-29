window.addEventListener('DOMContentLoaded', () => {
    // Buttons and elements
    const cArticleSetBtn = document.getElementById('make_article_Set'); // this pop the pop for creating the box
    const ArticleSetBox = document.getElementById('create-article-box'); // pop box creating the article set 
    const updArticleBox = document.getElementById('update-article-box'); // update article set pop box
    const editSetBtns = document.querySelectorAll('.edit-set-btn'); // this are edit btns classes which box the pop boxes
    const popBoxes = document.querySelectorAll('.pop-box'); // pop box class to hide all box first
    const setOverlay = document.querySelector('.set-overlay'); // overlay
    const ArticleSetBoxParent = document.querySelector('.set-parent-div'); // main parent div
    const closeBtns = document.querySelectorAll('.article-set-close'); // if you have multiple close buttons / having multiple pop boxes

    // ================= FUNCTIONS =================

    function showArticleBox(box) {
        // Hide all popups first
        popBoxes.forEach(i => i.classList.add('hidden', 'opacity-0', '-translate-y-[20%]'));

        // Show overlay and parent container
        setOverlay.classList.remove('hidden');
        ArticleSetBoxParent.classList.remove('hidden');

        // Show the specific popup
        box.classList.remove('hidden');

        // Animate
        setTimeout(() => {
            setOverlay.classList.replace('opacity-0', 'opacity-30');
            box.classList.remove('opacity-0', '-translate-y-[20%]');
        }, 50);
    }

    function closeArticleBox(box) {
        // Animate hide
        setOverlay.classList.replace('opacity-30', 'opacity-0');
        box.classList.add('opacity-0', '-translate-y-[20%]');
        setTimeout(() => {
            box.classList.add('hidden');
            setOverlay.classList.add('hidden');
            ArticleSetBoxParent.classList.add('hidden');
        }, 400);
    }

    // ================= EVENTS ====================

    // Show create box
    if (cArticleSetBtn) {
        cArticleSetBtn.addEventListener('click', () => showArticleBox(ArticleSetBox));
    }

    // Show update box when clicking edit buttons
    editSetBtns.forEach(btn => {
        btn.addEventListener('click', () => showArticleBox(updArticleBox));
    });

    // Close buttons (if both popups have a close button)
    closeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            popBoxes.forEach(box => {
                if (!box.classList.contains('hidden')) {
                    closeArticleBox(box);
                }
            });
        });
    });

    // Click on overlay closes whichever box is open

    if (setOverlay) {
        setOverlay.addEventListener('click', () => {
            popBoxes.forEach(box => {
                if (!box.classList.contains('hidden')) {
                    closeArticleBox(box);
                }
            });
        });
    }

    // ================= Upd Btns ==================

    // pop data so we can make an update request // it is for updating domains category name

    if (document.getElementById('upd_set_title')) {

        let titleInp = document.getElementById('upd_set_title');
        let updateBtn = document.querySelector('.upd-domain-category');
        // ** Selecting Table ** //
        let Table = document.getElementById('domainCategoryTable');
        if (document.getElementsByClassName('upd-pop-btn')) {
            let updPopBtn = document.getElementsByClassName('upd-pop-btn');

            const categoryNameEl = (row) => row?.querySelector('.category-badge, .title');

            Array.from(updPopBtn).forEach((item) => {
                item.addEventListener('click', (e) => {
                    showArticleBox(updArticleBox);
                    const id = e.currentTarget.dataset.id;
                    const row = e.currentTarget.closest('tr');
                    const nameEl = categoryNameEl(row);
                    titleInp.value = nameEl?.textContent?.trim() ?? '';
                    updateBtn.setAttribute('data-id', id);
                    updateBtn.setAttribute('data-name', titleInp.value);
                });
            });
        }


        /*domain category update code here */

        if (updateBtn) {
            updateBtn.addEventListener('click', async () => {
                const updId = updateBtn.getAttribute('data-id');
                if (!updId) {
                    alert('something went wrong');
                    return;
                }

                const previousVal = updateBtn.getAttribute('data-name') ?? '';
                const loader = updateBtn.querySelector('.loader');
                const successRow = document.getElementById('success-row');
                const errorRow = document.getElementById('error-row');
                const newName = titleInp.value.trim();

                if (newName === '') {
                    alert('please fill the update field properly');
                    return;
                }

                if (newName === previousVal) {
                    return;
                }

                loader?.classList.remove('hidden');

                try {
                    const response = await fetch(`/api/admin/domain/category/${updId}`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ name: newName }),
                    });

                    const result = await response.json();

                    if (result.status) {
                        successRow?.classList.remove('hidden');
                        setTimeout(() => {
                            successRow?.classList.remove('opacity-0');
                            const messageEl = successRow?.querySelector('.message');
                            if (messageEl) {
                                messageEl.textContent = result.message ?? 'Updated';
                            }
                            setTimeout(() => {
                                successRow?.classList.add('opacity-0', 'hidden');
                            }, 5000);
                        }, 100);

                        const row = Table?.querySelector(`.upd-pop-btn[data-id="${updId}"]`)?.closest('tr');
                        const nameEl = row?.querySelector('.category-badge, .title');
                        if (nameEl) {
                            nameEl.textContent = result.data?.name ?? newName;
                            nameEl.setAttribute('title', result.data?.name ?? newName);
                        }

                        updateBtn.setAttribute('data-name', result.data?.name ?? newName);
                    } else {
                        errorRow?.classList.remove('hidden');
                        setTimeout(() => {
                            errorRow?.classList.remove('opacity-0');
                            const messageEl = errorRow?.querySelector('.message');
                            if (messageEl) {
                                messageEl.textContent = result.message ?? 'Update failed';
                            }
                            setTimeout(() => {
                                errorRow?.classList.add('opacity-0', 'hidden');
                            }, 3000);
                        }, 100);
                    }
                } catch (error) {
                    console.error('Domain category update failed', error);
                    alert('Could not update category. Please try again.');
                } finally {
                    loader?.classList.add('hidden');
                }
            });
        }
    }



 

});
