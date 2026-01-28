import { initDynamicRadioGroup, initPopup, nestedPop } from "./helper/popup.js";
import { initSelectManager } from "./helper/selectBox.js";
import { allowOnlyNumbers } from "./helper/utility.js";


// *************** avoiding default input **********************

document.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && e.target.tagName === "INPUT") {
        e.preventDefault();
    }
});


// ************************************************************************************************************************************

// ✅ FULL CODE (with patches) — NO LINES REMOVED, ONLY ADDED/UPDATED INLINE

window.addEventListener("DOMContentLoaded", () => {

    // 🔴 RESET selection state on full page reload
    localStorage.removeItem("selectArticles");
    localStorage.removeItem("lastArticleSet");
    localStorage.removeItem("selectDomains");
    localStorage.removeItem("lastDomainSet");
    localStorage.removeItem("selectSetDomains");

    // --- campaigan form ---
    let form = document.getElementById('campaign-form');
    // tabs things which we will handle
    // --- Step manager (drop-in) ---
    const pqEl = document.getElementById("post-quantity");
    let postCount = parseInt(pqEl?.value || "10", 10);
    let domainId;
    if (isNaN(postCount) || postCount <= 0) postCount = 10;

    // ✅ PATCH: keep postCount always synced AFTER user types a valid value
    // (does NOT remove your old logic; it only updates postCount when a valid number exists)
    function syncPostCountFromInput() {
        const el = document.getElementById("post-quantity");
        let postQtyshower = document.querySelector('.dy-post-count');
        const v = parseInt((el?.value || "").trim(), 10);
        if (!isNaN(v) && v > 0) {
            postCount = v;
            postQtyshower.textContent = `(${v})`;
        }
        return postCount;
    }

    // ✅ PATCH: if user changes post quantity later, update postCount immediately
    if (pqEl) {
        pqEl.addEventListener("input", () => {
            syncPostCountFromInput();
        });
        pqEl.addEventListener("change", () => {
            syncPostCountFromInput();
        });
    }

    // ----------------------------------------------


    async function renderArticle({
        url = null,
        id,
        loader,
        articleTableBody,
        openPopup,
        per_page,
        paginationLoader,
        isPagination = false
    }) {
        try {
            const apiUrl = url ?? `/api/admin/set/articles/${id}?per_page=${per_page}`;

            // ✅ SHOW pagination loader ONLY on page click
            if (isPagination && paginationLoader) {
                paginationLoader.classList.remove("hidden");
            }

            const response = await fetch(apiUrl, {
                headers: { Accept: "application/json" },
            });

            const result = await response.json().catch(() => null);


            if (loader) loader.classList.add("hidden");

            if (!result || result.status !== true) {
                alert(result?.message || "Something went wrong.");
                return;
            }

            const setCount = result.data?.article_set?.count ?? 0;

            articleTableBody.innerHTML = '';

            if (setCount < 1) {
                alert("there is no article in the set");
                return;
            }

            if (setCount < postCount) {
                alert("the selective set does not have enough articles");
                return;
            }

            const articles = Array.isArray(result.data?.articles?.data)
                ? result.data.articles.data
                : [];



            let offset = parseInt(result.data?.articles?.per_page) * (parseInt(result.data?.articles?.current_page) - 1);





            articles.forEach((item, idx) => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';
                tr.innerHTML = `
                <td class="border !px-2 !py-1 text-center">
                    <input type="checkbox" class="articles" value="${item.id}"  >
                </td>
                <td class="border !px-2 !py-1">${idx + 1 + (offset)}</td>
                <td class="border !px-2 !py-1">${item.name}</td>
                <td class="border !px-2 !py-1 !text-center">
                    <a href="javascript:void(0)" class="bg-green-600 w-7 h-7 flex items-center justify-center rounded !mx-auto">
                        <span class="material-symbols-outlined text-white !text-sm ">visibility</span>
                    </a>
                </td>
            `;
                articleTableBody.appendChild(tr);



            });

            if (window.articleSelectMgr) {
                window.articleSelectMgr.refresh();
            }

            // ✅ IMPORTANT
            renderPagination(result.data.articles.links, {
                id,
                loader,
                articleTableBody,
                openPopup,
                per_page,
                paginationLoader
            });



            openPopup();

        } catch (error) {
            if (loader) loader.classList.add("hidden");
            console.error(error);
            alert("something went wrong");
        }
    }


    function renderPagination(links, context) {
        const container = document.getElementById('pagination-container');
        container.innerHTML = '';

        if (!links || links.length === 0) return;

        const { paginationLoader } = context;


        paginationLoader.classList.remove('hidden');
        if (paginationLoader) {
            paginationLoader.classList.remove('hidden');
        }

        links.forEach(link => {
            if (link.label === '...') {
                const span = document.createElement('span');
                span.textContent = '...';
                span.className = 'px-2 text-gray-500';
                container.appendChild(span);
                return;
            }

            const btn = document.createElement('button');
            btn.innerHTML = link.label;
            btn.className = 'page-btn';

            if (link.active) btn.classList.add('page-active');
            if (!link.url) {
                btn.disabled = true;
                btn.classList.add('page-disabled');
            }

            btn.onclick = () => {
                if (link.url) {
                    renderArticle({
                        url: link.url,
                        ...context
                    });
                }
            };

            container.appendChild(btn);
        });

        if (paginationLoader) {
            paginationLoader.classList.add('hidden');
        }
    }

    // render search articles with current data

    async function renderSearchArticle({
        searchData,
        url = "/api/admin/article/search",
        articleTableBody,
        loader,
        paginationLoader,
        per_page = 30,
        isPagination = false,
        postCount
    }) {
        try {
            if (isPagination && paginationLoader) {
                paginationLoader.classList.remove("hidden");
            }

            if (loader) loader.classList.remove("hidden");

            const response = await fetch(url, {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    ...searchData,
                    per_page
                })
            });

            const result = await response.json();

            console.log(result);

            let total = result?.data?.articles?.total;

            if (loader) loader.classList.add("hidden");
            if (paginationLoader) paginationLoader.classList.add("hidden");

            // 🔹 Clear table
            articleTableBody.innerHTML = '';

            if (total < postCount) {
                alert("Articles are not enough and less than then postCount");
                return;
            }



            if (!result || result.status !== true) {
                alert(result?.message || "Search failed");
                return;
            }

            const articles = result.data?.articles?.data || [];
            const meta = result.data?.articles;
            let offset = parseInt(result.data?.articles?.per_page) * (parseInt(result.data?.articles?.current_page) - 1);



            // 🔹 Render rows
            articles.forEach((item, idx) => {
                const tr = document.createElement("tr");
                tr.className = "hover:bg-gray-50";
                tr.innerHTML = `
                <td class="border !px-2 !py-1 text-center">
                    <input type="checkbox" class="articles" value="${item.id}">
                </td>
                <td class="border !px-2 !py-1">${idx + 1 + offset}</td>
                <td class="border !px-2 !py-1">${item.name}</td>
                <td class="border !px-2 !py-1 text-center">
                      <a href="javascript:void(0)" class="bg-green-600 w-7 h-7 flex items-center justify-center rounded !mx-auto">
                        <span class="material-symbols-outlined text-white !text-sm ">visibility</span>
                    </a>
                </td>
            `;
                articleTableBody.appendChild(tr);
            });

            // ✅ Restore checkbox highlight AFTER DOM exists
            if (window.articleSelectMgr) {
                window.articleSelectMgr.refresh();
            }

            // 🔹 Render pagination
            renderSearchPagination(meta.links, {
                searchData,
                articleTableBody,
                loader,
                paginationLoader,
                per_page
            });

        } catch (err) {
            console.error(err);
            alert("Something went wrong while searching");
            if (loader) loader.classList.add("hidden");
            if (paginationLoader) paginationLoader.classList.add("hidden");
        }
    }

    function renderSearchPagination(links, context) {
        const container = document.getElementById("pagination-container");
        container.innerHTML = '';

        if (!links || links.length === 0) return;

        links.forEach(link => {
            if (link.label === '...') {
                const span = document.createElement("span");
                span.textContent = "...";
                span.className = "px-2 text-gray-500";
                container.appendChild(span);
                return;
            }

            const btn = document.createElement("button");
            btn.innerHTML = link.label;
            btn.className = "page-btn";

            if (link.active) btn.classList.add("page-active");
            if (!link.url) {
                btn.disabled = true;
                btn.classList.add("page-disabled");
            }

            btn.onclick = () => {
                if (link.url) {
                    renderSearchArticle({
                        url: link.url,
                        ...context,
                        isPagination: true
                    });
                }
            };

            container.appendChild(btn);
        });
    }

    // ============================================
    // LANGUAGE ARTICLES FUNCTIONS
    // ============================================

    // Render articles by language

    async function renderLanguageArticle({
        languageId,
        url = "/api/admin/article/by-language",
        articleTableBody,
        loader,
        paginationLoader,
        per_page = 100,
        isPagination = false,
        postCount,
        openPopup
    }) {
        try {
            if (isPagination && paginationLoader) {
                paginationLoader.classList.remove("hidden");
            }

            if (loader) loader.classList.remove("hidden");

            // Use GET params if it's a pagination URL, otherwise POST
            let response;
            if (url.includes("?page=")) {
                // Pagination URL - use GET
                response = await fetch(url, {
                    method: "POST",
                    headers: {
                        "Accept": "application/json",
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        language_id: languageId,
                        per_page
                    })
                });
            } else {
                response = await fetch(url, {
                    method: "POST",
                    headers: {
                        "Accept": "application/json",
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        language_id: languageId,
                        per_page
                    })
                });
            }

            const result = await response.json();

            if (loader) loader.classList.add("hidden");
            if (paginationLoader) paginationLoader.classList.add("hidden");

            // Clear table
            articleTableBody.innerHTML = '';

            if (!result || result.status !== true) {
                alert(result?.message || "Failed to load articles");
                return { success: false, total: 0 };
            }

            const total = result.data?.meta?.total || 0;
            const available = result.data?.meta?.available || 0;

            // Check if articles are less than postCount
            if (available < postCount) {
                alert(`Not enough articles! Available: ${available}, Required: ${postCount}. Please select a language with at least ${postCount} articles.`);
                return { success: false, total: available };
            }

            const articles = result.data?.articles || [];

            let offset = (result.data?.meta?.per_page || per_page) * ((result.data?.meta?.current_page || 1) - 1);

            // Render rows
            articles.forEach((item, idx) => {
                const tr = document.createElement("tr");
                tr.className = "hover:bg-gray-50";
                tr.innerHTML = `
                <td class="border !px-2 !py-1 text-center">
                    <input type="checkbox" class="articles" value="${item.id}">
                </td>
                <td class="border !px-2 !py-1">${idx + 1 + offset}</td>
                <td class="border !px-2 !py-1">${item.name}</td>
                <td class="border !px-2 !py-1 text-center">
                      <a href="javascript:void(0)" class="bg-green-600 w-7 h-7 flex items-center justify-center rounded !mx-auto">
                        <span class="material-symbols-outlined text-white !text-sm ">visibility</span>
                    </a>
                </td>
            `;
                articleTableBody.appendChild(tr);
            });

            // Restore checkbox highlight AFTER DOM exists
            if (window.articleSelectMgr) {
                window.articleSelectMgr.refresh();
            }

            // Render pagination if there are multiple pages
            if (result.data?.meta?.last_page > 1) {
                renderLanguagePagination(result.data.meta, {
                    languageId,
                    articleTableBody,
                    loader,
                    paginationLoader,
                    per_page,
                    postCount,
                    openPopup
                });
            }

            // Open popup after rendering
            if (openPopup) openPopup();

            return { success: true, total: available };

        } catch (err) {
            console.error(err);
            alert("Something went wrong while loading language articles");
            if (loader) loader.classList.add("hidden");
            if (paginationLoader) paginationLoader.classList.add("hidden");
            return { success: false, total: 0 };
        }
    }

    function renderLanguagePagination(meta, context) {
        const container = document.getElementById("pagination-container");
        container.innerHTML = '';

        if (!meta || meta.last_page <= 1) return;

        const currentPage = meta.current_page;
        const lastPage = meta.last_page;

        // Create pagination buttons
        // Previous button
        const prevBtn = document.createElement("button");
        prevBtn.innerHTML = "&laquo;";
        prevBtn.className = "page-btn";
        if (currentPage === 1) {
            prevBtn.disabled = true;
            prevBtn.classList.add("page-disabled");
        }
        prevBtn.onclick = () => {
            if (currentPage > 1) {
                renderLanguageArticle({
                    ...context,
                    url: `/api/admin/article/by-language?page=${currentPage - 1}`,
                    isPagination: true
                });
            }
        };
        container.appendChild(prevBtn);

        // Page numbers
        for (let i = 1; i <= lastPage; i++) {
            if (i === 1 || i === lastPage || (i >= currentPage - 2 && i <= currentPage + 2)) {
                const btn = document.createElement("button");
                btn.textContent = i;
                btn.className = "page-btn";
                if (i === currentPage) btn.classList.add("page-active");
                btn.onclick = () => {
                    renderLanguageArticle({
                        ...context,
                        url: `/api/admin/article/by-language?page=${i}`,
                        isPagination: true
                    });
                };
                container.appendChild(btn);
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                const span = document.createElement("span");
                span.textContent = "...";
                span.className = "px-2 text-gray-500";
                container.appendChild(span);
            }
        }

        // Next button
        const nextBtn = document.createElement("button");
        nextBtn.innerHTML = "&raquo;";
        nextBtn.className = "page-btn";
        if (currentPage === lastPage) {
            nextBtn.disabled = true;
            nextBtn.classList.add("page-disabled");
        }
        nextBtn.onclick = () => {
            if (currentPage < lastPage) {
                renderLanguageArticle({
                    ...context,
                    url: `/api/admin/article/by-language?page=${currentPage + 1}`,
                    isPagination: true
                });
            }
        };
        container.appendChild(nextBtn);
    }

    // ============================================
    // END LANGUAGE ARTICLES FUNCTIONS
    // ============================================




    // render domains thing

    // async function renderDomains({
    //     url = null,
    //     domainCategoryId,
    //     tableBody,
    //     loader,
    //     paginationLoader,
    //     per_page = 30,
    //     isPagination = false
    // }) {
    //     try {
    //         const apiUrl =
    //             url ?? `/api/admin/domains/${domainCategoryId}?per_page=${per_page}`;

    //         if (isPagination && paginationLoader) {
    //             paginationLoader.classList.remove("hidden");
    //         }

    //         loader?.classList.remove("hidden");

    //         const res = await fetch(apiUrl, {
    //             headers: { Accept: "application/json" }
    //         }).then(r => r.json());

    //         if (!res.status) {
    //             alert(res.message || "Failed to load domains");
    //             return;
    //         }

    //         const domains = res.data.domains.data;
    //         const meta = res.data.domains;

    //         // ✅ VALIDATION (pagination-safe)
    //         if (meta.total < postCount) {
    //             alert("Domains quantity must be greater or equal to post quantity");
    //             return;
    //         }

    //         tableBody.innerHTML = '';

    //         domains.forEach((domain, index) => {
    //             const tr = document.createElement('tr');
    //             tr.className = 'hover:bg-gray-50';
    //             tr.innerHTML = `
    //             <td class="border !px-2 !py-1 text-center">
    //                 <input type="checkbox"
    //                     class="domains"
    //                     value="${domain.id}">
    //             </td>
    //             <td class="border !px-2 !py-1">${index + 1}</td>
    //             <td class="border !px-2 !py-1">
    //                 <label class="cursor-pointer w-full">
    //                     ${domain.name}
    //                 </label>
    //             </td>
    //             <td class="border !px-2 !py-1">${domain.da ?? '-'}</td>
    //             <td class="border !px-2 !py-1">${domain.tf ?? '-'}</td>
    //             <td class="border !px-2 !py-1">${domain.dr ?? '-'}</td>
    //             <td class="border !px-2 !py-1">${domain.ss ?? '-'}</td>
    //         `;
    //             tableBody.appendChild(tr);
    //         });

    //         // ✅ restore selection state
    //         if (window.domainSelectMgr) {
    //             window.domainSelectMgr.refresh();
    //         }

    //         renderDomainPagination(meta.links, {
    //             domainCategoryId,
    //             tableBody,
    //             loader,
    //             paginationLoader,
    //             per_page
    //         });

    //     } catch (err) {
    //         console.error(err);
    //         alert("Something went wrong while loading domains");
    //     } finally {
    //         loader?.classList.add("hidden");
    //         paginationLoader?.classList.add("hidden");
    //     }
    // }

    // ---------------

    async function renderDomains({
        url = null,
        domainCategoryId,
        tableBody,
        loader,
        paginationLoader,
        per_page = 30,
        isPagination = false
    }) {
        try {
            let apiUrl;

            // 🔴 CRITICAL FIX: Always preserve per_page
            if (url) {
                const u = new URL(url, window.location.origin);
                u.searchParams.set('per_page', per_page);
                apiUrl = u.toString();
            } else {
                apiUrl = `/api/admin/domains/${domainCategoryId}?per_page=${per_page}`;
            }

            // Show loaders
            if (isPagination && paginationLoader) {
                paginationLoader.classList.remove("hidden");
            }
            loader?.classList.remove("hidden");

            const res = await fetch(apiUrl, {
                headers: { Accept: "application/json" }
            }).then(r => r.json());

            if (!res.status) {
                alert(res.message || "Failed to load domains");
                return;
            }

            const meta = res.data.domains;
            const domains = meta.data;

            // ✅ Pagination-safe validation
            if (meta.total < postCount) {
                alert("Domains quantity must be greater or equal to post quantity");
                return;
            }

            tableBody.innerHTML = '';

            // Continuous serial number
            const offset = meta.per_page * (meta.current_page - 1);

            domains.forEach((domain, index) => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';
                tr.innerHTML = `
                <td class="border !px-2 !py-1 text-center">
                    <input type="checkbox"
                        class="domains"
                        value="${domain.id}">
                </td>
                <td class="border !px-2 !py-1">${index + 1 + offset}</td>
                <td class="border !px-2 !py-1">
                    <label class="cursor-pointer w-full">
                        ${domain.name}
                    </label>
                </td>
                <td class="border !px-2 !py-1">${domain.da ?? '-'}</td>
                <td class="border !px-2 !py-1">${domain.tf ?? '-'}</td>
                <td class="border !px-2 !py-1">${domain.dr ?? '-'}</td>
                <td class="border !px-2 !py-1">${domain.ss ?? '-'}</td>
            `;
                tableBody.appendChild(tr);
            });

            // ✅ Restore checkbox selections
            if (window.domainSelectMgr) {
                window.domainSelectMgr.refresh();
            }

            // Render pagination buttons
            renderDomainPagination(meta.links, {
                domainCategoryId,
                tableBody,
                loader,
                paginationLoader,
                per_page
            });

        } catch (err) {
            console.error(err);
            alert("Something went wrong while loading domains");
        } finally {
            loader?.classList.add("hidden");
            paginationLoader?.classList.add("hidden");
        }
    }


    function renderDomainPagination(links, context) {
        const container = document.getElementById("domain-pagination");
        container.innerHTML = '';

        if (!links || links.length === 0) return;

        links.forEach(link => {
            if (link.label === '...') {
                const span = document.createElement('span');
                span.textContent = '...';
                span.className = 'px-2 text-gray-500';
                container.appendChild(span);
                return;
            }

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.innerHTML = link.label;
            btn.className = 'page-btn';

            if (link.active) btn.classList.add('page-active');
            if (!link.url) btn.disabled = true;

            btn.onclick = () => {
                if (link.url) {
                    renderDomains({
                        url: link.url,
                        ...context,
                        isPagination: true
                    });
                }
            };

            container.appendChild(btn);
        });
    }

    // render domain set 

    async function renderDomainSet({
        url = null,
        domainSetId,
        tableBody,
        loader,
        paginationLoader,
        per_page = 30,
        isPagination = false
    }) {
        try {
            let apiUrl;

            // 🔴 preserve per_page on pagination clicks
            if (url) {
                const u = new URL(url, window.location.origin);
                u.searchParams.set('per_page', per_page);
                apiUrl = u.toString();
            } else {
                apiUrl = `/api/admin/domain/set/fetch/${domainSetId}?per_page=${per_page}`;
            }

            if (isPagination && paginationLoader) {
                paginationLoader.classList.remove('hidden');
            }
            loader?.classList.remove('hidden');

            const res = await fetch(apiUrl, {
                headers: { Accept: 'application/json' }
            }).then(r => r.json());

            if (!res?.status) {
                alert(res?.message || 'Failed to load domains');
                return;
            }

            const meta = res.data.domains;
            const domains = meta.data;

            tableBody.innerHTML = '';

            const offset = meta.per_page * (meta.current_page - 1);

            domains.forEach((d, index) => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';
                tr.innerHTML = `
                <td class="border !px-2 !py-1 text-center">
                    <input type="checkbox"
                        class="setdomains"
                        value="${d.id}">
                </td>
                <td class="border !px-2 !py-1">${index + 1 + offset}</td>
                <td class="border !px-2 !py-1">
                    <label class="cursor-pointer w-full">${d.name ?? ''}</label>
                </td>
                <td class="border !px-2 !py-1">${d.da ?? '-'}</td>
                <td class="border !px-2 !py-1">${d.tf ?? '-'}</td>
                <td class="border !px-2 !py-1">${d.dr ?? '-'}</td>
                <td class="border !px-2 !py-1">${d.ss ?? '-'}</td>
            `;
                tableBody.appendChild(tr);
            });

            // ✅ restore checkbox highlights
            if (window.domainSetSelectMgr) {
                window.domainSetSelectMgr.refresh();
            }

            renderDomainSetPagination(meta.links, {
                domainSetId,
                tableBody,
                loader,
                paginationLoader,
                per_page
            });

        } catch (err) {
            console.error(err);
            alert('Something went wrong');
        } finally {
            loader?.classList.add('hidden');
            paginationLoader?.classList.add('hidden');
        }
    }

    function renderDomainSetPagination(links, context) {
        const container = document.getElementById('domain-set-pagination');
        container.innerHTML = '';

        if (!links || links.length === 0) return;

        links.forEach(link => {
            if (link.label === '...') {
                const span = document.createElement('span');
                span.textContent = '...';
                span.className = 'px-2 text-gray-500';
                container.appendChild(span);
                return;
            }

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.innerHTML = link.label;
            btn.className = 'page-btn';

            if (link.active) btn.classList.add('page-active');
            if (!link.url) btn.disabled = true;

            btn.onclick = () => {
                renderDomainSet({
                    url: link.url,
                    ...context,
                    isPagination: true
                });
            };

            container.appendChild(btn);
        });
    }

    // ---------------------------------



    (function () {
        // Tab buttons and sections
        const tabBtnArr = Array.from(document.getElementsByClassName("tab-switcher") || []);
        const campaignsSection = Array.from(document.querySelectorAll(".campaigns-section") || []);
        let currentStep = 0;
        // let postCount = 10;

        // helper: update visual active state
        function updateTabVisuals(activeIndex) {
            tabBtnArr.forEach((btn, i) => {
                const dots = btn.querySelectorAll(".heading-dots");
                const line = btn.querySelector(".heading-line");
                if (i === activeIndex) {
                    // active styles
                    btn.classList.add("text-[var(--primary-color)]");
                    dots.forEach(dot => {
                        dot.classList.remove("bg-gray-300");
                        dot.classList.add("bg-[var(--primary-color)]");
                    });
                    line.classList.remove("bg-gray-300");
                    line.classList.add("bg-[var(--primary-color)]");
                } else {
                    // default (inactive) styles
                    btn.classList.remove("text-[var(--primary-color)]");
                    dots.forEach(dot => {
                        dot.classList.remove("bg-[var(--primary-color)]");
                        dot.classList.add("bg-gray-300");
                    });
                    line.classList.add("bg-gray-300");
                    line.classList.remove("bg-[var(--primary-color)]");
                }
            });
        }

        // enable/disable tab buttons
        function setTabEnabled(index, enabled) {
            const btn = tabBtnArr[index];
            if (!btn) return;
            if (enabled) {
                btn.classList.remove("opacity-40", "pointer-events-none");
                btn.dataset.locked = "false";
            } else {
                btn.classList.add("opacity-40", "pointer-events-none");
                btn.dataset.locked = "true";
            }
        }

        // initialize: lock all tabs except first
        function initTabsLocking() {
            tabBtnArr.forEach((b, i) => {
                b.removeEventListener("click", tabClickHandlerBound);
                b.addEventListener("click", tabClickHandlerBound);
                (i === 0) ? setTabEnabled(i, true) : setTabEnabled(i, false);
            });
            showSection(0);
        }

        // show section and update visuals
        function showSection(index) {
            campaignsSection.forEach((s, i) => {
                if (i === index) {
                    s.classList.remove("hidden");
                    setTimeout(() => s.classList.remove("opacity-0", "translate-y-5"), 20);
                } else {
                    s.classList.add("hidden", "opacity-0", "translate-y-5");
                }
            });
            updateTabVisuals(index);
            if (index === 3 && typeof window.__onEnterStep04 === "function") {
                window.__onEnterStep04();
            }
        }

        // prevent clicking locked tabs
        function tabClickHandlerBound(e) {
            const idx = tabBtnArr.indexOf(this);
            const locked = this.dataset.locked === "true";
            if (locked) {
                e.preventDefault();
                alert("Complete previous step first.");
                return false;
            }
            showSection(idx);
            currentStep = idx;

            // ✅ PATCH: whenever user lands on any tab, ensure postCount is up to date
            syncPostCountFromInput();
        }

        // Step validators
        function validateStep01() {
            const campaignId = document.getElementById("campaign-no");
            const selectDomain = document.getElementById("campaign-domain");
            const postQuantity = document.getElementById("post-quantity");

            const fromDateInp = document.getElementById("schedule-from-date");
            const toDateInp = document.getElementById("schedule-to-date");

            if (!campaignId || !selectDomain || !postQuantity || !fromDateInp || !toDateInp) {
                return { ok: false, msg: "Missing fields on step 1" };
            }

            if (
                campaignId.value.trim() === "" ||
                selectDomain.value.trim() === "" ||
                postQuantity.value.trim() === "" ||
                fromDateInp.value === "" ||
                toDateInp.value === ""
            ) {
                return { ok: false, msg: "All fields are required" };
            }

            // -------------------------
            // Post quantity validation
            // -------------------------
            const pq = parseInt(postQuantity.value, 10);
            if (isNaN(pq) || pq <= 0) {
                return { ok: false, msg: "Enter a valid post quantity" };
            }

            // -------------------------
            // Date validation
            // -------------------------
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const fromDate = new Date(fromDateInp.value);
            const toDate = new Date(toDateInp.value);

            fromDate.setHours(0, 0, 0, 0);
            toDate.setHours(0, 0, 0, 0);

            // Rule: From date must be today or future
            if (fromDate < today) {
                return { ok: false, msg: "From date must be today or a future date" };
            }

            // Rule: To date must be after from date
            if (toDate <= fromDate) {
                return { ok: false, msg: "To date must be at least 1 day after From date" };
            }

            // Rule: Minimum 1 day gap
            const diffDays = (toDate - fromDate) / (1000 * 60 * 60 * 24);
            if (diffDays < 1) {
                return { ok: false, msg: "Campaign duration must be at least 1 day" };
            }

            // -------------------------
            // Assign globals (existing logic)
            // -------------------------
            postCount = pq;
            domainId = selectDomain.value;

            return { ok: true };
        }

        function validateStep02() {
            const articleNiche = document.getElementById("article-niche");
            const articleSelOpt = Array.from(document.querySelectorAll(".article-sel-opts") || []);
            const selectedArticlesVal = document.getElementById("selected_articles_val");

            // if (!articleNiche) return { ok: false, msg: "Missing article niche field" };
            // if (articleNiche.value.trim() === "") return { ok: false, msg: "Select article niche" };

            const chosen = articleSelOpt.find((r) => r.checked)?.value || "";
            if (!chosen) return { ok: false, msg: "Select article selection method" };

            if (chosen === "own_article") {
                const own = document.getElementById("own-articles");
                if (!own || own.value.trim() === "")
                    return { ok: false, msg: "Select your article set to continue" };
            }

            if (chosen === "system_article") {
                const searchInp = document.getElementById("search-article-inp");
                const searchingType = document.getElementById("searching-type");
                const searchOpts = Array.from(document.querySelectorAll(".article-search-opts") || []);
                const selectedSearch = searchOpts.filter(s => s.checked).map(s => s.value);
                if (!searchInp || searchInp.value.trim() === "" || selectedSearch.length === 0 || !searchingType || searchingType.value.trim() === "")
                    return { ok: false, msg: "Enter proper search Article section data" };
            }

            if (chosen === "language_article") {
                const languageSelect = document.getElementById("language-articles-select");
                if (!languageSelect || languageSelect.value.trim() === "")
                    return { ok: false, msg: "Select a language to load articles" };
            }

            if (!selectedArticlesVal || selectedArticlesVal.value.trim() === "")
                return { ok: false, msg: "Select at least one article to continue" };

            return { ok: true };
        }

        function validateStep03() {
            // ✅ PATCH: step 3 always uses latest postCount, not old default 10
            syncPostCountFromInput();

            const keywordsDataHolder = document.getElementById("keywordsDataHolder");
            if (!keywordsDataHolder) return { ok: false, msg: "Missing keyword data holder" };
            try {
                const parsed = JSON.parse(keywordsDataHolder.value || "[]");
                if (!Array.isArray(parsed) || parsed.length < 1)
                    return { ok: false, msg: "Enter keywords data to continue" };
            } catch (err) {
                return { ok: false, msg: "Invalid keywords JSON" };
            }
            return { ok: true };
        }

        // next-step flow
        function goToNextStep() {
            // ✅ PATCH: always sync before checking step validations / limits
            syncPostCountFromInput();

            const validators = [validateStep01, validateStep02, validateStep03];
            const currentValidator = validators[currentStep];
            if (!currentValidator) return;

            const result = currentValidator();
            if (!result.ok) {
                alert(result.msg);
                return;
            }

            if (currentStep + 1 < tabBtnArr.length) {
                setTabEnabled(currentStep + 1, true);
                showSection(currentStep + 1);
                currentStep++;
            }
        }

        // Continue buttons hookup
        const continueMap = [
            document.getElementById("continue_campaign_step_01"),
            document.getElementById("continue_campaign_step_02"),
            document.getElementById("continue_campaign_step_03"),
        ];

        continueMap.forEach((btn, i) => {
            if (!btn) return;
            btn.addEventListener("click", function (ev) {
                ev.preventDefault();
                ev.stopPropagation();

                // ✅ PATCH: sync postCount for ALL steps (not only step 1)
                syncPostCountFromInput();

                if (i === 0) {
                    const pqElem = document.getElementById("post-quantity");
                    if (pqElem && pqElem.value.trim() !== "")
                        postCount = parseInt(pqElem.value) || postCount;
                }

                goToNextStep();
            });
        });

        // expose globally
        window.__campaign_step_manager = {
            goToStep: function (idx) {
                if (typeof idx !== "number") return;
                if (idx < 0 || idx >= tabBtnArr.length) return;

                // ✅ PATCH: sync before manual step jumps too
                syncPostCountFromInput();

                for (let s = 0; s < idx; s++) {
                    const validators = [validateStep01, validateStep02, validateStep03];
                    if (validators[s]) {
                        const r = validators[s]();
                        if (!r.ok) return { ok: false, msg: `Step ${s + 1} invalid: ${r.msg}` };
                    }
                }
                for (let k = 0; k <= idx; k++) setTabEnabled(k, true);
                showSection(idx);
                currentStep = idx;
                return { ok: true };
            },
            validateStep01,
            validateStep02,
            validateStep03,
            getPostCount: () => postCount
        };

        initTabsLocking();
    })();


    // tab ends here
    let numInp = document.getElementsByClassName("num-inp");
    allowOnlyNumbers(numInp);

    // tab switch use for nested switching?
    function tabStyleSwitcher(btns, section, func = false) {
        Array.from(btns).forEach((i) => {

            i.addEventListener("change", (e) => {
                e.stopImmediatePropagation();
                e.preventDefault();
                Array.from(btns).forEach((k) => {
                    k.classList.remove(
                        "border-[var(--primary-color)]",
                        "bg-[var(--primary-color)]",
                        "text-white"
                    );
                    k.classList.add("border-gray-300", "bg-gray-50");
                });

                // hidding all tab
                Array.from(section).forEach((item) => {
                    item.classList.add("hidden");
                });

                document
                    .getElementById(`${e.target.getAttribute("data-id")}`)
                    .classList.remove("hidden");

                if (func instanceof Function) {
                    func(e.target);
                }

                i.classList.remove("border-gray-300", "bg-gray-50");
                i.classList.add(
                    "border-[var(--primary-color)]",
                    "bg-[var(--primary-color)]",
                    "text-white"
                );
            });
        });
    }

    //create campiagns
    let step__01 = document.getElementById("continue_campaign_step_01");
    let step__02 = document.getElementById("continue_campaign_step_02");
    let step__03 = document.getElementById("continue_campaign_step_03");
    let step__04 = document.getElementById("continue_campaign_step_04");

    // utility function for showing boxes on radio button values
    function showArticleBoxes(value, item) {
        let articleBox = document.getElementById(`${value}`);
        let articleOptBox = document.getElementsByClassName("article-opt-box");
        Array.from(articleOptBox).forEach((i) => {
            i.classList.add("hidden", "opacity-0");
        });
        articleBox.classList.remove("hidden");
        setTimeout(() => {
            articleBox.classList.remove("opacity-0");
        }, 200);
    }

    if (step__02) {
        initDynamicRadioGroup(
            "article-sel-opts",
            "articles-opts-label",
            showArticleBoxes
        );
        initDynamicRadioGroup("article-search-opts", "articles-search-label");

        // 🧩 Initialize main popup with nested support
        const addOwnArticles = document.getElementById("add_own_article");
        const addSearchArticle = document.getElementById("add_search_article");
        let articleNiche = document.getElementById('article-niche');

        if (addOwnArticles || addSearchArticle) {
            const dyParent = document.querySelector(".dy-parent-div");
            const dyOverlay = document.querySelector(".dy-set-overlay");
            const dyCloseBtn = document.getElementById("dy-close-btn");
            const selectiveBox = document.getElementById("dy-article-box");
            // selecting article table
            let articleTable = document.getElementById('myTable');
            let articleTableBody = articleTable.querySelector('tbody');
            // ✅ Single reusable function
            let articleMgrInitialized = false;

            const openPopup = () => {
                const nestedBtn =
                    document.querySelectorAll(".dy-nested-pop-btn");
                initPopup(
                    dyParent,
                    dyOverlay,
                    selectiveBox,
                    dyCloseBtn,
                    600,
                    () => {
                        nestedPop(nestedBtn);
                    }
                );
            };


            if (addOwnArticles) {
                const LAST_SET_KEY = "lastArticleSet";
                let ownArticleInp = document.getElementById("own-articles");

                addOwnArticles.addEventListener("click", async (e) => {
                    e.stopImmediatePropagation();
                    e.preventDefault();

                    if (!ownArticleInp || ownArticleInp.value === "") {
                        alert("please select your articles or set");
                        return;
                    }

                    const id = ownArticleInp.value;
                    const lastSet = localStorage.getItem(LAST_SET_KEY);

                    // ✅ DATASET CHANGE → CLEAR, NOT REFRESH
                    if (lastSet !== String(id)) {
                        localStorage.setItem(LAST_SET_KEY, id);
                        if (window.articleSelectMgr) {
                            window.articleSelectMgr.clear();
                        }
                    }

                    const btn = e.currentTarget;
                    const loader = btn.querySelector(".article-set-loader");
                    if (loader) loader.classList.remove("hidden");

                    let currentPage = 1;
                    let per_page = 30;
                    let paginationLoader = document.querySelector('.pagination-loader');
                    let isPagination = false;
                    // ✅ WAIT for DOM to render
                    await renderArticle({
                        id,
                        currentPage,
                        loader,
                        articleTableBody,
                        openPopup,
                        per_page,
                        paginationLoader,
                        isPagination
                    });

                    // ✅ INIT ONCE
                    if (!articleMgrInitialized) {
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
                        articleMgrInitialized = true;
                    } else {
                        // ✅ SAME DATASET → JUST REFRESH UI
                        window.articleSelectMgr.refresh();
                    }
                });
            }

            if (addSearchArticle) {

                addSearchArticle.addEventListener("click", async (e) => {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    let appendDiv = document.getElementById("append_items_here");
                    let searchInp = document.getElementById("search-article-inp");
                    let search_items_opt = document.querySelectorAll(".article-search-opts");
                    let searchType = document.getElementById("searching-type");
                    let searchLoader = e.currentTarget.querySelector(".search-articles-loader");

                    // 🔹 Basic validation
                    if (!searchInp.value.trim()) {
                        let p = document.createElement("p");
                        p.textContent = "Enter search value for filter content";
                        p.className = "text-red-400 text-sm";
                        appendDiv.append(p);
                        searchInp.focus();
                        searchInp.addEventListener("input", () => p.remove(), { once: true });
                        return;
                    }

                    let searchItem = null;
                    search_items_opt.forEach(itm => {
                        if (itm.checked) searchItem = itm.value;
                    });

                    if (!searchItem) {
                        alert("select the search item 'In Title' or 'In Articles'");
                        return;
                    }

                    // 🔹 Prepare payload
                    let search__data = {
                        search: searchInp.value.trim(),
                        searchItem: searchItem,
                        searchType: searchType.value
                    };

                    if (articleNiche?.value) {
                        search__data.article_category_id = articleNiche.value;
                    }

                    try {
                        // 🔴 Search = NEW DATASET → clear selection ONCE
                        if (window.articleSelectMgr) {
                            window.articleSelectMgr.clear();
                        }

                        // 🔹 Render search results (separate flow)
                        await renderSearchArticle({
                            searchData: search__data,
                            articleTableBody: articleTableBody,
                            loader: searchLoader,
                            paginationLoader: document.querySelector('.pagination-loader'),
                            per_page: 30,
                            postCount
                        });

                        // 🔹 Open popup after data is rendered
                        openPopup();

                        if (!articleMgrInitialized) {
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
                            articleMgrInitialized = true;
                        } else {
                            // ✅ SAME DATASET → JUST REFRESH UI
                            window.articleSelectMgr.refresh();
                        }

                    } catch (err) {
                        console.error(err);
                        alert("something went wrong");
                    }
                });
            }

            // ============================================
            // LANGUAGE ARTICLES HANDLER
            // ============================================
            const addLanguageArticle = document.getElementById("add_language_article");
            const languageSelectInp = document.getElementById("language-articles-select");
            const languageInfoDiv = document.getElementById("language-articles-info");
            const languageStatusDiv = document.getElementById("language-articles-status");
            const languageIcon = document.getElementById("language-articles-icon");
            const languageMessage = document.getElementById("language-articles-message");
            const postQtyDisplay = document.getElementById("postQtyDisplay");

            // Update post quantity display when step 2 is shown
            if (postQtyDisplay) {
                const updatePostQtyDisplay = () => {
                    syncPostCountFromInput();
                    postQtyDisplay.textContent = postCount;
                };
                updatePostQtyDisplay();
                // Also update when post quantity input changes
                const pqInput = document.getElementById("post-quantity");
                if (pqInput) {
                    pqInput.addEventListener("input", updatePostQtyDisplay);
                    pqInput.addEventListener("change", updatePostQtyDisplay);
                }
            }

            // Show article count info when language is selected
            if (languageSelectInp && languageInfoDiv) {
                // Listen for changes on the dropdown (using MutationObserver since it's a custom dropdown)
                const observer = new MutationObserver(() => {
                    if (languageSelectInp.value) {
                        // Find the selected option's article count
                        const allOptions = document.querySelectorAll('#language_article [data-option-item]');
                        let articleCount = 0;
                        allOptions.forEach(opt => {
                            if (opt.dataset.value === languageSelectInp.value) {
                                articleCount = parseInt(opt.dataset.articleCount) || 0;
                            }
                        });

                        syncPostCountFromInput();
                        languageInfoDiv.classList.remove("hidden");

                        if (articleCount < postCount) {
                            languageStatusDiv.className = "flex items-center gap-2 p-3 rounded bg-red-100 text-red-700";
                            languageIcon.textContent = "error";
                            languageMessage.innerHTML = `<strong>Not enough articles!</strong> Available: ${articleCount}, Required: ${postCount}`;
                        } else {
                            languageStatusDiv.className = "flex items-center gap-2 p-3 rounded bg-green-100 text-green-700";
                            languageIcon.textContent = "check_circle";
                            languageMessage.innerHTML = `<strong>${articleCount}</strong> articles available (Required: ${postCount})`;
                        }
                    } else {
                        languageInfoDiv.classList.add("hidden");
                    }
                });
                observer.observe(languageSelectInp, { attributes: true, attributeFilter: ['value'] });

                // Also listen for value changes directly
                languageSelectInp.addEventListener("change", () => {
                    observer.disconnect();
                    setTimeout(() => {
                        observer.observe(languageSelectInp, { attributes: true, attributeFilter: ['value'] });
                    }, 100);
                });
            }

            if (addLanguageArticle) {
                const LAST_LANGUAGE_KEY = "lastArticleLanguage";

                addLanguageArticle.addEventListener("click", async (e) => {
                    e.stopImmediatePropagation();
                    e.preventDefault();

                    if (!languageSelectInp || languageSelectInp.value === "") {
                        alert("Please select a language first");
                        return;
                    }

                    const languageId = parseInt(languageSelectInp.value);
                    const lastLanguage = localStorage.getItem(LAST_LANGUAGE_KEY);

                    // Check article count before proceeding
                    const allOptions = document.querySelectorAll('#language_article [data-option-item]');
                    let articleCount = 0;
                    allOptions.forEach(opt => {
                        if (opt.dataset.value === String(languageId)) {
                            articleCount = parseInt(opt.dataset.articleCount) || 0;
                        }
                    });

                    syncPostCountFromInput();

                    if (articleCount < postCount) {
                        alert(`Not enough articles! This language has ${articleCount} articles but you need ${postCount}. Please select a different language or reduce the post quantity.`);
                        return;
                    }

                    // Clear selection if language changed
                    if (lastLanguage !== String(languageId)) {
                        localStorage.setItem(LAST_LANGUAGE_KEY, languageId);
                        if (window.articleSelectMgr) {
                            window.articleSelectMgr.clear();
                        }
                    }

                    const btn = e.currentTarget;
                    const loader = btn.querySelector(".language-article-loader");
                    if (loader) loader.classList.remove("hidden");

                    try {
                        const result = await renderLanguageArticle({
                            languageId: languageId,
                            articleTableBody: articleTableBody,
                            loader: loader,
                            paginationLoader: document.querySelector('.pagination-loader'),
                            per_page: 100,
                            postCount: postCount,
                            openPopup: openPopup
                        });

                        if (result.success) {
                            if (!articleMgrInitialized) {
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
                                articleMgrInitialized = true;
                            } else {
                                window.articleSelectMgr.refresh();
                            }
                        }
                    } catch (err) {
                        console.error(err);
                        alert("Something went wrong while loading language articles");
                    }
                });
            }
            // ============================================
            // END LANGUAGE ARTICLES HANDLER
            // ============================================




            let multipleArticleSelect = document.getElementById(
                "multiple-articles-select"
            );
            multipleArticleSelect.addEventListener("click", (e) => {
                e.stopImmediatePropagation();
                e.preventDefault();

                let selectArticleValHolder = document.getElementById(
                    "selected_articles_val"
                );

                let selectArticles = JSON.parse(localStorage.getItem('selectArticles'));

                if (!selectArticles) {
                    alert("Articles are not selected yet!!");
                    return;
                }

                if (postCount != selectArticles.length) {
                    alert(`you must select articles equal to ${postCount} quantity`);
                    return;
                }
                // store values in a hidden input
                if (selectArticleValHolder) {
                    selectArticleValHolder.value = selectArticles;
                    document.getElementById("dy-close-btn").click(); // we are transfering click so it closes the popup
                    console.log(selectArticles)
                }
            });
        }
    }

    if (step__03) {
        // ✅ PATCH: step 3 must also refresh postCount at init time
        syncPostCountFromInput();

        // this is keyword pop showing
        let addKeywordsUrl = document.getElementById("add-keywords-url-btn");
        // keyword popup elements
        const dyKeywordParent = document.querySelector(".dy-key-parent-div");
        const dyKeywordOverlay = document.querySelector(".dy-key-set-overlay");
        const dyKeywordCloseBtn = document.getElementById("dy-key-close-btn");
        const selectiveKeywordBox =
            document.getElementById("dy-key-article-box");
        addKeywordsUrl.addEventListener("click", (e) => {
            e.stopImmediatePropagation();
            initPopup(
                dyKeywordParent,
                dyKeywordOverlay,
                selectiveKeywordBox,
                dyKeywordCloseBtn,
                600
            );
        });
        // selecting hidden input which will hold the keyword-json data
        let keywordsDataHolder = document.getElementById("keywordsDataHolder");
        // hidden inputs ends here

        function checkNormal(target) {
            if (target.value == "normal") {
                document
                    .querySelector(".add-more-window")
                    .classList.remove("hidden");
            }
        }

        // ===============================

        let keywordTabBtn = document.querySelectorAll(".keyword-tab-btn");
        let keywordTab = document.getElementsByClassName("keyword-tab-sec");

        tabStyleSwitcher(keywordTabBtn, keywordTab, checkNormal);

        // === Global references ===
        let AddMoreBtn = document.getElementById("add-more-keyword-URL");
        let keywordUrlCon = document.getElementById("keyword-url-container");
        let totalBoxCount = document.querySelector(".total-box-count");

        // ✅ PATCH: whenever user clicks AddMore, ensure postCount latest (not default 10)
        // (we will call syncPostCountFromInput inside the handler below)

        // === Utility: Update borders dynamically ===
        function updateBorders() {
            const boxes = document.querySelectorAll(".keyword-url-box");
            boxes.forEach((box, i) => {
                box.classList.remove(
                    "border-b",
                    "border-b-[var(--primary-color)]"
                );
                if (i < boxes.length - 1) {
                    box.classList.add(
                        "border-b",
                        "border-b-[var(--primary-color)]"
                    );
                }
            });
        }

        // === Utility: Update count labels ===
        function updateBoxCount() {
            const boxes = [...document.querySelectorAll(".keyword-url-box")];
            boxes.forEach((box, i) => {
                const boxCounter = box.querySelector(".box-count");
                if (boxCounter) {
                    boxCounter.textContent = String(i + 1).padStart(2, "0");
                }
            });
            if (totalBoxCount) totalBoxCount.textContent = boxes.length;
        }

        // ======= utility function to check wether input value is greater than postcount
        function checkExceedPostCount(inp, count) {
            Array.from(inp).forEach((i, currentIndex) => {
                i.addEventListener("input", function (e) {
                    e.stopImmediatePropagation();

                    // ✅ PATCH: always sync before using count
                    syncPostCountFromInput();
                    count = postCount;

                    let usedPost = [...inp].reduce((sum, num, idx) => {
                        if (idx !== currentIndex) {
                            return sum + (parseInt(num.value) || 0);
                        }
                        return sum;
                    }, 0);
                    let val = parseInt(this.value || 0);
                    let maxAllowed = count - usedPost;
                    if (val > maxAllowed) {
                        alert(
                            "URL quantity cannot exceed total post count: " +
                            maxAllowed
                        );
                        this.value = maxAllowed;
                        val = maxAllowed;
                    }
                    updateKeywordQuantity(this, val);
                });
            });
        }

        // utility function to make to distribute the number based on input value
        function keywordNumDistribute(keyArea) {
            Array.from(keyArea).forEach((keyAr, indx) => {
                keyAr.addEventListener("input", (e) => {
                    const text = e.target.value;
                    const lines = text
                        .split("\n")
                        .filter((line) => line.trim() !== "");
                    const keywordCount = lines.length;

                    if (keywordCount === 0) return;

                    const inputNum =
                        parseInt(
                            e.target
                                .closest(".keyword-url-box")
                                .querySelector(".client-url-quantity").value
                        ) || 0;

                    let keyArr = [];

                    if (inputNum > keywordCount) {
                        let base = Math.floor(inputNum / keywordCount); // base per keyword
                        let remainder = inputNum % keywordCount; // leftover to distribute

                        for (let i = 0; i < keywordCount; i++) {
                            keyArr[i] = base + (i < remainder ? 1 : 0); // add 1 to first 'remainder' keywords
                        }
                    } else {
                        // if inputNum <= keywordCount, assign 1 to first 'inputNum' keywords, 0 to the rest
                        for (let i = 0; i < keywordCount; i++) {
                            keyArr[i] = i < inputNum ? 1 : 0;
                        }
                    }

                    // Convert keyArr to lines and set as .keywords-quantity-area
                    const boxContent = e.target
                        .closest(".keyword-url-box")
                        .querySelector(".keywords-quantity-area");
                    if (boxContent) {
                        boxContent.value = keyArr.join("\n"); // each number on a new line
                    }
                });
            });
        }

        // === Utility: Reflect quantity into keyword area ===
        function updateKeywordQuantity(input, val) {
            const parentBox = input.closest(".keyword-url-box");
            if (!parentBox) return;
            let keywordsArea = parentBox.querySelector(".keywords-area");
            let kQuantityArea = parentBox.querySelector(
                ".keywords-quantity-area"
            );
            if (keywordsArea) {
                let keywordCount = keywordsArea.value
                    .split("\n")
                    .filter((line) => line.trim() !== "").length;
                if (keywordCount > 0) {
                    let inputVal = input.value;

                    let keyQuantityArr = [];

                    if (inputVal > keywordCount) {
                        let base = Math.floor(inputVal / keywordCount); // base per keyword
                        let remainder = inputVal % keywordCount; // leftover to distribute

                        for (let i = 0; i < keywordCount; i++) {
                            keyQuantityArr[i] = base + (i < remainder ? 1 : 0); // add 1 to first 'remainder' keywords
                        }
                    } else {
                        // if inputNum <= keywordCount, assign 1 to first 'inputNum' keywords, 0 to the rest
                        for (let i = 0; i < keywordCount; i++) {
                            keyQuantityArr[i] = i < inputVal ? 1 : 0;
                        }
                    }
                    kQuantityArea.value = keyQuantityArr.join("\n"); // each number on a new line
                    return;
                }
            }
            const keywordQuantityArea = parentBox.querySelector(
                ".keywords-quantity-area"
            );
            if (keywordQuantityArea) keywordQuantityArea.value = val;
        }

        // === Delete handler ===
        keywordUrlCon.addEventListener("click", (e) => {
            if (e.target.classList.contains("remove-keyword-box")) {
                e.preventDefault();
                e.stopPropagation();
                e.target.closest(".keyword-url-box").remove();
                updateBoxCount();
                updateBorders();
                // redistributeQuantities();
            }
        });

        // === Add new keyword box ===
        AddMoreBtn.addEventListener("click", (e) => {
            e.preventDefault();

            // ✅ PATCH: sync right before checking keywordBoxCount >= postCount
            syncPostCountFromInput();

            let div = document.createElement("div");
            div.className =
                "flex w-full bg-orange-100 keyword-url-box relative";
            div.innerHTML = `
        <div class="w-3/5 flex flex-col gap-2 !p-4 !pt-[45px]">
            <div class="w-full flex items-center">
                <label class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-1/5">Client Url</label>
                <div class="w-4/5 flex items-center justify-between">
                    <input type="text" placeholder="Enter Url" class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-[78%] client-url">
                    <input type="text" value="0" class="bg-gray-50 !p-2 text-sm outline-none text-center border border-gray-300 w-1/5 client-url-quantity num-inp">
                </div>
            </div>
            <div class="w-full flex items-center">
                <label class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-1/5 ">Media Link</label>
                <div class="w-4/5 flex items-center justify-between">
                    <textarea class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none media-link" rows="2" placeholder="Enter Media Link Here"></textarea>
                </div>
            </div>
            <div class="w-full flex items-center justify-end">
                <button class="!p-1 bg-red-600 rounded text-sm cursor-pointer text-white remove-keyword-box">delete</button>
            </div>
        </div>
        <div class="w-2/5 flex flex-col gap-1 !p-4">
            <label class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Client Keyword</label>
            <div class="w-full flex flex-wrap justify-between keywords-area-parent">
                <div class="w-[78%] flex flex-wrap">
                    <textarea rows="5" class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none keywords-area"></textarea>
                </div>
                <div class="w-1/5 flex flex-wrap">
                    <textarea rows="5" class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none text-center keywords-quantity-area"></textarea>
                </div>
            </div>
        </div>
        <div class="box-count flex !px-3 !py-1 text-sm font-semibold bg-[var(--primary-color)] text-white rounded absolute top-3 left-3">01</div>
    `;

            //checking box not exceed the postcount
            let keywordBoxCount =
                document.getElementsByClassName("keyword-url-box").length;
            if (keywordBoxCount >= postCount) {
                alert("you cannot add boxes more than posts");
                return;
            }

            keywordUrlCon.appendChild(div);
            updateBoxCount();
            updateBorders();
            // this selecting all input
            const newInput = document.querySelectorAll(".client-url-quantity");
            allowOnlyNumbers(newInput);
            checkExceedPostCount(newInput, postCount);

            // select keywords area
            let keywordsArea = document.getElementsByClassName("keywords-area");
            keywordNumDistribute(keywordsArea);
        });

        // === Initialize for existing boxes ===
        updateBoxCount();
        updateBorders();
        let clientUrlQuantity = document.getElementsByClassName(
            "client-url-quantity"
        );
        checkExceedPostCount(clientUrlQuantity, postCount);
        // select keywords area
        let keywordsArea = document.getElementsByClassName("keywords-area");
        keywordNumDistribute(keywordsArea);

        // (rest of your step__03 code remains unchanged below...)
        // NOTE: your remaining code continues to use `postCount`,
        // now it will always reflect user's entered value, not stuck on 10.

        // xxxxxxxxxxxxxxxxxxxxxxx this is bulk input data  xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

        let bulkInput = document.getElementsByClassName("bulk-input");
        Array.from(bulkInput).forEach((item) => {
            item.addEventListener("input", (e) => {
                let splitVal = e.target.value
                    .split("\n")
                    .filter((line) => line.trim() !== "");
                let parent = e.target.closest("div");
                parent.querySelector(
                    ".bulk-count"
                ).textContent = `(${splitVal.length})`;
            });
        });

        // xxxxxxxxxxxxxxxxxxxxxxx bulk data ends  xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

        // ==========================================================================================================================
        // ** 

        let multiUrlKeywordContainer = document.getElementById('multi-level-keyword-url-container');
        let multiUrlKeywordWrap = document.getElementById('multi-key-url-box-parent');
        let multiBoxCount = document.querySelector('.multi-total-box-count');

        multiUrlKeywordWrap.addEventListener('click', (e) => {
            if (
                e.target.closest("input") ||
                e.target.tagName === "INPUT"

            ) {
                return;
            }
            // it is adding the fields row
            if (e.target.closest('.add-multipe-box')) {
                let btn = e.target.closest('.add-multipe-box');
                let parent = btn.closest('.multi-keyword-url-box');
                let fieldsAttachParent = parent.querySelector('.multi-url-keyword-attachment-box');
                let spanCount = (fieldsAttachParent.querySelectorAll('.multi-keyword-url-count').length) + 1;
                if (spanCount > 5) {
                    alert("you can not add more than 5 keywords & urls in a post");
                    return;
                }
                let paddedNumber = String(spanCount).padStart(2, '0');
                // console.log(typeof(paddedNumber));
                // making div 
                let div = document.createElement('div');
                div.className = 'w-full flex flex-col gap-2 md:flex-row mf:gap-0 md:justify-between relative !px-10 !py-1 multi-keyword-url-row';
                div.innerHTML = `
                   <span
                        class="multi-keyword-url-count cursor-move flex items-center  justify-center absolute top-1/2 -translate-y-1/2 left-2 text-white bg-black w-6 h-6 !p-1 text-[12px] rounded">${paddedNumber}</span>
                    <div class="w-full md:w-[49.5%] flex">
                        <input type="text" placeholder="Enter Url"
                            class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full multi-url-inp">
                    </div>
                    <div class="w-full md:w-[49.5%] flex">
                        <input type="text" placeholder="Enter Keyword"
                                class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full multi-keyowrd-inp">
                    </div>
                    <button
                        class="remove-multi-keyword-url-row w-6 h-6 bg-red-600 rounded-full text-white flex items-center justify-center absolute top-1/2 -translate-y-1/2 right-2 cursor-pointer">
                        <span class="material-symbols-outlined !text-sm">
                            close
                        </span>
                    </button>
                `
                fieldsAttachParent.append(div);

                // ---------- Auto-scroll to newly added row ----------
                div.scrollIntoView({ behavior: 'smooth', block: 'end' });

                // ---------- Initialize Sortable (if not already) ----------
                if (!fieldsAttachParent.sortableInitialized) {
                    new Sortable(fieldsAttachParent, {
                        animation: 150,
                        handle: '.multi-keyword-url-count', // drag by number
                        ghostClass: 'sortable-ghost',
                        onEnd: function () {
                            // Renumber all rows after drag
                            fieldsAttachParent.querySelectorAll('.multi-keyword-url-row').forEach((row, index) => {
                                let countSpan = row.querySelector('.multi-keyword-url-count');
                                if (countSpan) {
                                    countSpan.textContent = String(index + 1).padStart(2, '0');
                                }
                            });
                        }
                    });
                    fieldsAttachParent.sortableInitialized = true;
                }

            }
            // it is removing the fields row
            if (e.target.closest('.remove-multi-keyword-url-row')) {
                let btn = e.target.closest('.remove-multi-keyword-url-row');
                let parent = btn.closest('.multi-keyword-url-row');
                let fieldsAttachParent = parent.closest('.multi-url-keyword-attachment-box');
                parent.remove() //
                //------------------------------
                let rows = fieldsAttachParent.querySelectorAll('.multi-keyword-url-row');
                rows.forEach((row, index) => {
                    let countSpan = row.querySelector('.multi-keyword-url-count');
                    if (countSpan) {
                        countSpan.textContent = String(index + 1).padStart(2, '0');
                    }
                });

            }
            // it is used for collapsing the box
            if (e.target.closest('.multi-keyword-url-collapser')) {
                let collapser = e.target.closest('.multi-keyword-url-collapser');
                let parent = collapser.closest('.multi-keyword-url-box');
                let accordionDiv = parent.querySelector('.multi-keyword-url-accordion');
                // selecting all box

                collapser.querySelector('.arrow-rotate').classList.toggle('rotate-180');
                // accordion 
                if (accordionDiv.classList.contains('flex')) {
                    accordionDiv.classList.add('opacity-0');
                    setTimeout(() => {
                        accordionDiv.classList.replace('flex', 'hidden');
                    }, 500)
                } else if (accordionDiv.classList.contains('hidden')) {
                    accordionDiv.classList.replace('hidden', 'flex');
                    setTimeout(() => {
                        accordionDiv.classList.remove('opacity-0');
                    }, 100)
                    accordionDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            if (e.target.closest('.remove-multi-keyword-box')) {
                let btn = e.target.closest('.remove-multi-keyword-box');
                let parent = btn.closest('.multi-keyword-url-box');
                parent.remove();
                let multiBoxCountSpan = document.getElementsByClassName('multi-box-count');
                multiBoxCount.textContent = (multiBoxCountSpan.length);
                Array.from(multiBoxCountSpan).forEach((itm, indx) => {
                    itm.textContent = `${String(indx + 1).padStart(2, '0')}`
                })

            }

        })
        // it is for apply same data for others
        multiUrlKeywordWrap.addEventListener(
            "input",
            (e) => {
                const current = e.target;

                // Only run on the TARGET input
                if (!current.classList.contains("multi-keyword-url-box-quantity")) return;

                // Fix empty or invalid value
                if (current.value.trim() === "" || isNaN(parseInt(current.value))) {
                    current.value = 0;
                }

                // Convert current input value
                const currentValue = parseInt(current.value);

                // Get all inputs
                const allInputs = [...multiUrlKeywordWrap.querySelectorAll(".multi-keyword-url-box-quantity")];

                // Calculate total of all inputs EXCEPT the current one
                const sumOtherInputs = allInputs
                    .filter((inp) => inp !== current) // exclude current
                    .reduce((total, inp) => total + parseInt(inp.value || "0"), 0);

                // You can now do anything with sumOtherInputs …
                // Example: check limits
                if (sumOtherInputs + currentValue > postCount) {
                    alert(`Total exceeds allowed limit (${postCount})`);

                    // Set allowed maximum for this input
                    current.value = postCount - sumOtherInputs;

                    if (current.value < 0) current.value = 0;
                }
            },
            true
        );

        // it is adding the multiple box
        multiUrlKeywordContainer.addEventListener('click', (e) => {
            if (e.target.closest('#add-more-multi-keyword-Url')) {

                let btn = e.target.closest('#add-more-multi-keyword-Url');
                let span = (multiUrlKeywordWrap.querySelectorAll('.multi-box-count').length) + 1;
                if (span > postCount) {
                    alert("boxes cannot exceed the post Quantity");
                    return;
                }
                let paddedNumber = String(span).padStart(2, '0');
                multiBoxCount.textContent = `${paddedNumber}`;
                let div = document.createElement('div');
                div.className = 'flex w-full flex-col multi-keyword-url-box relative'
                div.innerHTML = `
                   <div
                      class="w-full flex items-center !px-4 !py-2 !pr-10  bg-[var(--primary-color)] justify-end cursor-pointer relative multi-keyword-url-collapser">
                      <div class="w-1/2 flex items-center">
                          <span
                              class="multi-box-count flex !px-3 !py-1 text-sm font-semibold text-white rounded">
                              ${paddedNumber}</span>
                      </div>
                      <div class="w-1/2 flex gap-2 justify-end items-center">
                          <label for="" class="text-white">Apply for</label>
                          <input type="text" value="0"
                              class="bg-gray-50 !p-2 text-sm outline-none text-center border border-gray-300 w-1/5 multi-keyword-url-box-quantity num-inp">
                      </div>
                      <span
                          class="material-symbols-outlined  text-gray-100 absolute top-1/2 -translate-y-1/2 right-2 duration-500 transition-all arrow-rotate rotate-180">
                          keyboard_arrow_up
                      </span>
                  </div>
                  <div
                      class="w-full hidden flex-col duration-300 bg-orange-100 transition-all opacity-0 !pb-2 multi-keyword-url-accordion">
                    
                      <div class="w-full flex flex-col gap-2 items-center !px-4 !py-1">
                          <label for=""
                              class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-full ">Media
                              Link</label>
                          <input type="text" placeholder="Enter Url"
                              class="bg-gray-50 !p-4 text-sm outline-none border border-gray-300 w-full multiple-box-media-link">

                      </div>
                     
                      <div class="w-full flex items-center !px-4 !py-2">
                          <div class="w-1/2 flex">
                              <label for=""
                                  class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-full ">Add
                                  Multiple Urls & Keywords
                              </label>
                          </div>
                          <div class="w-1/2 flex gap-2 justify-end items-center">
                              <button
                                  class="w-6 h-6 cursor-pointer bg-green-500 flex items-center text-white outline-none add-multipe-box justify-center rounded">
                                  <span class="material-symbols-outlined !text-sm">
                                      add
                                  </span>
                              </button>
                          </div>
                      </div>
                    
                      <div
                          class="w-full flex flex-col gap-1 max-h-[120px] overflow-hidden overflow-y-auto multi-url-keyword-attachment-box">
                          <div
                              class="w-full flex flex-col gap-2 md:flex-row mf:gap-0 md:justify-between relative !px-10 !py-1 multi-keyword-url-row">
                              <span
                                  class="multi-keyword-url-count cursor-move flex items-center justify-center absolute top-1/2 -translate-y-1/2 left-2 text-white bg-black w-6 h-6 !p-1 text-[12px] rounded">01</span>
                              <div class="w-full md:w-[49.5%] flex">
                                  <input type="text" placeholder="Enter Url"
                                      class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full multi-url-inp">
                              </div>
                              <div class="w-full md:w-[49.5%] flex">
                                  <input type="text" placeholder="Enter Keyword"
                                      class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full multi-keyowrd-inp">
                              </div>
                              <button
                                  class="remove-multi-keyword-url-row w-6 h-6 bg-red-600 rounded-full text-white flex items-center justify-center absolute top-1/2 -translate-y-1/2 right-2 cursor-pointer">
                                  <span class="material-symbols-outlined !text-sm">
                                      close
                                  </span>
                              </button>
                          </div>

                      </div>
                     
                      <div class="w-full flex flex-col items-start !px-4 !py-1 !mt-2">
                          <button
                              class="!p-1 bg-red-600 rounded text-sm cursor-pointer text-white remove-multi-keyword-box">delete</button>
                      </div>
                  </div>
                `
                multiUrlKeywordWrap.append(div);

                div.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        })


        let addKeywordBtn = document.getElementById("add-keywords-links");
        addKeywordBtn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopImmediatePropagation();
            const method =
                document.querySelector(".keyword-method-inp:checked")?.value ||
                "";
            document.getElementById('keyWordsMethod').value = `${method}`;
            const noFollow = document.getElementById("no_follow")?.checked
                ? document.getElementById("no_follow").value
                : "";

            let keyWordBox = document.querySelectorAll(".keyword-url-box");
            let keywords_url_data = [];

            // this data is for bulk section



            if (method == "normal") {
                if (keyWordBox.length === 0) {
                    alert("At least 1 box data is required");
                    return;
                }

                let emptyUrl = false;
                let emptyKeywordArea = false;
                Array.from(keywordUrlCon.querySelectorAll('.client-url')).forEach((i) => {
                    if (i.value == '') {
                        emptyUrl = true;
                    }
                })
                // keyword area
                Array.from(keywordUrlCon.querySelectorAll('.keywords-area')).forEach((i) => {
                    if (i.value == '') {
                        emptyKeywordArea = true;
                    }
                })

                if (emptyUrl) {
                    alert("one of the url field is not filled properly");
                    return;
                }
                if (emptyKeywordArea) {
                    alert("some of keyword section contains empty value");
                    return;
                }

                // first checking input quantity it should be eqaul to post qunanity
                let clientUrlQuantityInp = document.querySelectorAll(
                    ".client-url-quantity"
                );
                let totalLinks = [...clientUrlQuantityInp].reduce(
                    (sum, num) => {
                        return sum + parseInt(num.value);
                    },
                    0
                );
                // links quantity should match the postCount
                if (totalLinks != postCount) {
                    alert("client url quantity should match the postCount");
                    return;
                }
                // box data here
                let boxData = [];

                let right = true;
                keyWordBox.forEach((box) => {
                    // let data = {};
                    // client url + media link + quantity to be repeat
                    var urlQuantity = box.querySelector(
                        ".client-url-quantity"
                    ).value; // it make multiple urls
                    var url = box.querySelector(".client-url").value; // simple url
                    var media = box.querySelector(".media-link").value; // simple media link
                    var keywords = box
                        .querySelector(".keywords-area")
                        .value.split("\n")
                        .filter((line) => line.trim() !== "");
                    var keywordsNum = box
                        .querySelector(".keywords-quantity-area")
                        .value.split("\n")
                        .filter((line) => line.trim() !== "");
                    var keywordQTotal = [...keywordsNum].reduce((sum, num) => {
                        return sum + parseInt(num);
                    }, 0);

                    //
                    if (keywordQTotal != urlQuantity) {
                        right = false;
                        alert(
                            "keywords quantity should be equal to url quanity"
                        );
                        box.scrollIntoView({
                            behavior: "smooth",
                            block: "center",
                        });
                        box.querySelector(".keywords-quantity-area").focus();
                        return;
                    }
                    // generate boxData
                    let keywordIndex = 0;
                    let repeatCount = keywordsNum[keywordIndex]; // how many times current keyword should repeat

                    for (let i = 0; i < urlQuantity; i++) {
                        boxData.push({
                            url: url,
                            media: media ? media : "-",
                            keyword: keywords[keywordIndex],
                            nofollow: noFollow,
                        });

                        repeatCount--;
                        if (repeatCount === 0) {
                            keywordIndex++; // move to next keyword
                            repeatCount = keywordsNum[keywordIndex] || 0;
                        }
                    }

                    // right ? boxData.push(data) : '';
                });
                boxData = !right ? "" : boxData;

                keywords_url_data = boxData;
            }
            if (method == "bulk") {
                //select as general
                let bulkData = []; // empty the boxDtata
                // select bulk elements
                let bulkUrls = document.getElementById("bulk-client-urls");
                let bulkKeywords = document.getElementById(
                    "bulk-client-keywords"
                );
                let bulkMedia = document.getElementById("bulk-media-links");

                // selecting value as array || filtering the empty valyes
                let bulkUrlsVal = document
                    .getElementById("bulk-client-urls")
                    .value.split("\n")
                    .filter((line) => line.trim() !== "");
                let bulkKeywordsVal = document
                    .getElementById("bulk-client-keywords")
                    .value.split("\n")
                    .filter((line) => line.trim() !== "");
                let bulkMediaVal = document
                    .getElementById("bulk-media-links")
                    .value.split("\n")
                    .filter((line) => line.trim() !== "");

                // getting value

                if (bulkUrlsVal.length < 1 || bulkKeywordsVal.length < 1) {
                    alert("urls | keywords quantity should be greater than 0");
                    return;
                }
                if (bulkUrlsVal.length != bulkKeywordsVal.length) {
                    alert("urls & keywords quantity should match");
                    return;
                }

                if (
                    bulkUrlsVal.length != postCount ||
                    bulkKeywordsVal.length != postCount
                ) {
                    alert(
                        "urls & keywords quantity should match to the post quantity also"
                    );
                    return;
                }

                // let bulkData = true;

                for (let x = 0; x < bulkUrlsVal.length; x++) {
                    bulkData.push({
                        url: bulkUrlsVal[x],
                        keyword: bulkKeywordsVal[x],
                        media: bulkMediaVal[x] ? bulkMediaVal[x] : "-",
                        nofollow: noFollow,
                    });
                }

                keywords_url_data = bulkData;
            }
            if (method == "multiple") {

                let multiKeyWordUrlBoxes = document.getElementsByClassName('multi-keyword-url-box');
                // multiple-box-media-link | media link
                // multi-url-inp
                // multi-keyowrd-inp
                let multiData = [];
                // validation Check 
                let key__url__validation = [];
                Array.from(multiKeyWordUrlBoxes).forEach((item, indx) => {
                    let singleBoxData = {};
                    singleBoxData.media = item.querySelector('.multiple-box-media-link').value;
                    let urlInpVal = [...item.querySelectorAll('.multi-url-inp')]
                        .map(el => el.value.trim());
                    let keywordInpVal = [...item.querySelectorAll('.multi-keyowrd-inp')]
                        .map(el => el.value.trim());
                    // validation code here
                    if (urlInpVal.includes("") || keywordInpVal.includes("")) {
                        key__url__validation.push((indx + 1))
                    }
                    // validation ends here
                    singleBoxData.url = urlInpVal;
                    singleBoxData.keyword = keywordInpVal;
                    // setting quantity
                    let quantity = item.querySelector('.multi-keyword-url-box-quantity').value;
                    singleBoxData.nofollow = noFollow;
                    // check this
                    for (let z = 0; z < parseInt(quantity); z++) {
                        multiData.push(singleBoxData)
                    }


                });

                if (key__url__validation.length > 0) {
                    alert(`the ${key__url__validation.length > 1 ? 'boxes' : 'box'} ${key__url__validation.join(',')} have empty urls & keywords`);
                    return;
                }
                if (multiData.length != postCount) {
                    alert("please add proper quantity in boxes or add boxes according to Post Quantity");
                    return;
                }

                keywords_url_data = multiData
            }

            let keywordsDataTable = document.getElementById(
                "keywords-data-table"
            );
            keywordsDataHolder.value = JSON.stringify(keywords_url_data);
            console.log(keywordsDataHolder.value)
            //===============================

            keywordsDataTable.querySelector("tbody").innerHTML = "";

            keywords_url_data.forEach((item, idx) => {
                let tr = document.createElement("tr");
                tr.className = "hover:bg-gray-50";
                tr.innerHTML = `
                 <td class="border border-gray-200 font-sans !p-2"><div>${Array.isArray(item.url) ? (item.url).join('<br>') : item.url}</div></td>
                 <td class="border border-gray-200 font-sans !p-2"><div>${Array.isArray(item.keyword) ? (item.keyword).join('<br>') : item.keyword}</div></td>
                 <td class="border border-gray-200 font-sans !p-2"><div>${item.media != '' ? item.media : '-'}</div></td>
                `;
                keywordsDataTable.querySelector("tbody").append(tr);
            });
            dyKeywordCloseBtn.click();
        });
    }

    if (step__04) {

        let domainTabBtns = document.getElementsByClassName('domain-tab-btn'); //
        let domainSections = document.getElementsByClassName('domains-sections');

        function buildDomainRows(domains) {
            return domains.map((domain, index) => {
                const checkbox = `
            <input
                type="checkbox"
                name="bulk_domain_select[]"
                class="domains"
                value="${domain.id}"
                id="sel-domain-check-box-${index + 1}"
            >
        `;

                const sno = index + 1;

                const domainCol = `
            <label for="sel-domain-check-box-${index + 1}" class="cursor-pointer w-full">
                ${domain.name}
            </label>
        `;

                return [
                    checkbox,
                    sno,
                    domainCol,
                    domain.da ?? '-',
                    domain.tf ?? '-',
                    domain.dr ?? '-',
                    domain.ss ?? '-'
                ];
            });
        }


        window.__onEnterStep04 = async function () {

            const selected = document.querySelector('input[name="sel_domains"]:checked');
            if (!selected) return;

            const showDomainLoader = document.querySelector('.domain-loader-pop');
            const paginationLoader = document.querySelector('.domain-pagination-loader');
            const tableBody = document.querySelector('#RandomDomainsTable tbody');

            try {
                showDomainLoader.classList.remove('hidden');
                setTimeout(() => showDomainLoader.classList.remove('opacity-0'), 200);

                // ✅ Load FIRST page of paginated domains
                await renderDomains({
                    domainCategoryId: domainId,
                    tableBody: tableBody,
                    loader: showDomainLoader,
                    paginationLoader: paginationLoader,
                    per_page: 30,
                    isPagination: false
                });

                // ✅ INIT domain selection manager ONCE
                if (!window.domainSelectMgr) {
                    window.domainSelectMgr = initSelectManager({
                        checkboxSelector: '.domains',
                        selectAllSelector: '#selectAllDomains',
                        countSelector: '#selectedDomainCount',
                        localStorageKey: 'selectDomains',
                        parentSelector: 'tr',
                        maxQuantityInputSelector: '#post-quantity'
                    });
                } else {
                    // ✅ Restore highlight when re-entering step
                    window.domainSelectMgr.refresh();
                }

            } catch (e) {
                console.error(e);
                alert("Something went wrong while loading domains");
            } finally {
                showDomainLoader.classList.add('opacity-0');
                setTimeout(() => showDomainLoader.classList.add('hidden'), 300);
            }
        };



        // ********* fetching domains base on the domains set ********* //

        let fetchDomainSet = document.getElementById('fetch-domains-set');
        let domainSetId = document.getElementById('domain-set-id');

        let domainSetMgrInitialized = false;
        const LAST_DOMAIN_SET_KEY = 'lastDomainSet';

        fetchDomainSet.addEventListener('click', async (e) => {
            e.preventDefault();

            const btn = e.currentTarget;
            const loader = btn.querySelector('.loader');
            const setId = parseInt(domainSetId.value, 10);
            if (!setId) return;

            const lastSet = localStorage.getItem(LAST_DOMAIN_SET_KEY);

            // 🔴 new set → clear selection ONCE
            if (lastSet !== String(setId)) {
                localStorage.setItem(LAST_DOMAIN_SET_KEY, setId);
                localStorage.removeItem('selectSetDomains');
                if (window.domainSetSelectMgr) {
                    window.domainSetSelectMgr.clear();
                }
            }

            await renderDomainSet({
                domainSetId: setId,
                tableBody: document.querySelector('#domainSetTable tbody'),
                loader,
                paginationLoader: document.querySelector('.domain-set-pagination-loader'),
                per_page: 30,
                isPagination: false
            });

            // ✅ init ONCE
            if (!domainSetMgrInitialized) {
                window.domainSetSelectMgr = initSelectManager({
                    checkboxSelector: '.setdomains',
                    selectAllSelector: '#selectAllSetDomains',
                    countSelector: '#selectedSetDomainCount',
                    localStorageKey: 'selectSetDomains',
                    highlightClass: '!bg-blue-100',
                    parentSelector: 'tr',
                    maxQuantityInputSelector: '#post-quantity',
                    enableRandom: false
                });
                domainSetMgrInitialized = true;
            } else {
                window.domainSetSelectMgr.refresh();
            }
        });


        // fetchDomainSet.addEventListener('click', async (e) => {
        //     e.preventDefault();

        //     // ✅ use currentTarget so it works even if you click icon/span inside button
        //     const btn = e.currentTarget;
        //     const loader = btn.querySelector('.loader');

        //     const setId = parseInt((domainSetId?.value || '').trim(), 10);
        //     if (!Number.isInteger(setId) || setId <= 0) return;

        //     if (!window.domainSetDT) {
        //         alert('domainSetDT not initialized');
        //         return;
        //     }

        //     try {
        //         loader?.classList.remove('hidden');

        //         const api = await fetch(`/api/admin/domain/set/fetch/${setId}`, {
        //             method: 'GET',
        //             headers: { Accept: 'application/json' }
        //         });

        //         const response = await api.json().catch(() => null);

        //         if (!response || response.status !== true || !Array.isArray(response.data)) {
        //             alert(response?.message || 'Failed to fetch domains');
        //             return;
        //         }

        //         // ✅ build rows for DataTable
        //         const rows = response.data.map((d, index) => {
        //             const cbId = `sel-set-domain-check-box-${index + 1}`;
        //             const checkboxCol = `
        //                                  <input type="checkbox"
        //                                    name="bulk_domain_select[]"
        //                                    id="${cbId}"
        //                                    class="setdomains"
        //                                    value="${d.id}">
        //                                `;

        //             const snoCol = index + 1;

        //             const domainCol = `
        //                             <label for="${cbId}" class="cursor-pointer w-full">
        //                               ${d.name ?? ''}
        //                             </label>
        //                           `;

        //             return [
        //                 checkboxCol,
        //                 snoCol,
        //                 domainCol,
        //                 d.da ?? '-',
        //                 d.tf ?? '-',
        //                 d.dr ?? '-',
        //                 d.ss ?? '-'
        //             ];
        //         });

        //         // ✅ put into datatable
        //         const dt = window.domainSetDT;
        //         dt.clear();
        //         dt.rows.add(rows).draw(false);

        //         // ✅ if you have selection manager for setdomains, refresh it
        //         if (window.domainSetSelectMgr) {
        //             window.domainSetSelectMgr.refresh();
        //         }

        //     } catch (err) {
        //         console.error(err);
        //         alert('Something went wrong');
        //     } finally {
        //         loader?.classList.add('hidden');
        //     }
        // });

        // ********* fetching ends here ********* //




        tabStyleSwitcher(domainTabBtns, domainSections);

        let manualDomainsArea = document.getElementById('manual-domains-area');
        let manualDomainCount = document.getElementById('manualDomainsCount');
        manualDomainsArea.addEventListener('input', (e) => {
            e.stopImmediatePropagation();
            e.preventDefault();

            let count = e.target.value.split("\n")
                .filter((line) => line.trim() !== "").length;

            manualDomainCount.textContent = `${count}`;
        })

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const selectedRadio = document.querySelector('input[name="sel_domains"]:checked');
            const campaignDomainHolder = document.getElementById('campaigns_domains_holder');

            if (!selectedRadio || !campaignDomainHolder) {
                alert('Required form fields are missing.');
                return;
            }

            const selectedDomainMethod = selectedRadio.value;

            /* -----------------------------
               RANDOM / SET DOMAINS
            ------------------------------*/
            if (selectedDomainMethod === '0') {
                const domains = localStorage.getItem('selectDomains');
                if (!domains) {
                    alert('No domains found in local storage.');
                    return;
                }
                let domainsLength = JSON.parse(domains).length;
                if (domainsLength != postCount) {
                    alert('domains should be selected equal to post count');
                    return;
                }
                campaignDomainHolder.value = domains;

            } else if (selectedDomainMethod === '1') {
                const domains = localStorage.getItem('selectSetDomains');
                if (!domains) {
                    alert('No domain set found in local storage.');
                    return;
                }
                let domainsLength = JSON.parse(domains).length;
                if (domainsLength != postCount) {
                    alert('domains should be selected equal to post count');
                    return;
                }
                campaignDomainHolder.value = domains;

            }
            /* -----------------------------
               MANUAL DOMAINS
            ------------------------------*/
            else {
                if (!manualDomainsArea) {
                    alert('Manual domains field not found.');
                    return;
                }

                const domains = manualDomainsArea.value
                    .split('\n')
                    .map(d => d.trim())
                    .filter(Boolean);

                if (domains.length !== postCount) {
                    alert(`Manual domains must be equal to postCount (${postCount})`);
                    return;
                }

                const icon = step__04?.querySelector('.check-icon');
                const loader = step__04?.querySelector('.loader');

                try {
                    icon?.classList.add('hidden');
                    loader?.classList.remove('hidden');

                    const response = await fetch('/api/admin/domains/validate', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ domains })
                    });

                    const res = await response.json();

                    if (!res.status) {
                        alert(res.message || 'Domain validation failed.');
                        return;
                    }

                    campaignDomainHolder.value = JSON.stringify(res.data.domain_ids);

                } catch (error) {
                    console.error('Domain validation error:', error);
                    alert('Something went wrong while validating domains.');

                } finally {
                    icon?.classList.remove('hidden');
                    loader?.classList.add('hidden');
                }
            }

            // submit the form programmatically
            e.target.submit();
        });


    }
});
