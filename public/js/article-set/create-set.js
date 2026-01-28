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

        console.log(updateBtn.getAttribute('data-id'));


        // ** ends here **

        if (document.getElementsByClassName('upd-pop-btn')) {
            let updPopBtn = document.getElementsByClassName('upd-pop-btn');
            console.log(updPopBtn);
            Array.from(updPopBtn).forEach((item, index) => {
                item.addEventListener('click', (e) => {
                    showArticleBox(updArticleBox);
                    const id = e.currentTarget.dataset.id;  // clean & reliable
                    let title = e.currentTarget.closest('tr').querySelector('.title').textContent.trim();
                    titleInp.value = title;
                    updateBtn.setAttribute('data-id', id);
                    updateBtn.setAttribute('data-name', `${title}`);
                    updateBtn.setAttribute('data-index', `${index}`);

                })
            })

        }


        /*domain category update code here */

        if (updateBtn) {
            //api/admin/domain/category/{id}
            updateBtn.addEventListener('click', async (e) => {
                if (updateBtn.getAttribute('data-id') != '') {
                    let updId = updateBtn.getAttribute('data-id');
                    let previousVal = updateBtn.getAttribute('data-name');
                    let idx = updateBtn.getAttribute('data-index');
                    let loader = updateBtn.querySelector('.loader');
                    // selecting succes and errors rows

                    let successRow = document.getElementById('success-row');
                    let errorRow = document.getElementById('error-row');

                    // ** End here ** //
                    loader.classList.remove('hidden');
                    console.log(updId);
                    if (titleInp.value.trim() == '') {
                        alert("please fill the update field properly");
                        loader.classList.add('hidden');
                        return;
                    }
                    if (titleInp.value.trim() == previousVal) {
                        loader.classList.add('hidden');
                        return;
                    }

                    try {
                        let url = `/api/admin/domain/category/${updId}`
                        let response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                name: titleInp.value.trim()
                            })
                        })
                        loader.classList.add('hidden');
                        let result = await response.json();
                        console.log(result)
                        if (result.status) {
                            successRow.classList.remove('hidden');
                            setTimeout(() => {
                                successRow.classList.remove('opacity-0');
                                successRow.querySelector('.message').textContent = `${result.message}`;
                                setTimeout(() => {
                                    successRow.classList.add('opacity-0');
                                    successRow.classList.add('hidden');
                                }, 5000)
                            }, 100)
                            Table.querySelectorAll('tbody tr')[`${idx}`].querySelector('.title').textContent = `${result.data.name}`;

                        } else {
                            errorRow.classList.remove('hidden');
                            setTimeout(() => {
                                errorRow.classList.remove('opacity-0');
                                errorRow.querySelector('.message').textContent = `${result.message}`;
                                setTimeout(() => {
                                    errorRow.classList.add('opacity-0');
                                    errorRow.classList.add('hidden');
                                }, 3000)
                            }, 100)
                        }

                    } catch (error) {
                        throw new Error(error, "console something went wrong");

                    }
                } else {
                    alert("something went wrong")
                }

            })
        }
    }



 

});
