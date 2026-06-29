import { initDynamicRadioGroup, initPopup, nestedPop } from "./helper/popup.js";
import { initSelectManager } from "./helper/selectBox.js";
import { allowOnlyNumbers } from "./helper/utility.js";
//export


// *************** avoiding default input **********************

document.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && e.target.tagName === "INPUT") {
        e.preventDefault();
    }
});

// *************** initiating selectbox things **********************




// ************************************************************************************************************************************

// ✅ FULL CODE (with patches) — NO LINES REMOVED, ONLY ADDED/UPDATED INLINE

window.addEventListener("DOMContentLoaded", () => {

    // 🔴 RESET selection state on full page reload
    localStorage.removeItem("selectArticles");
    localStorage.removeItem("lastArticleSet");
    localStorage.removeItem("selectDomains");
    localStorage.removeItem("lastDomainSet");
    localStorage.removeItem("selectSetDomains");


    // --- ends here ----

    // --- campaigan form ---
    let form = document.getElementById('campaign-form');
    // tabs things which we will handle
    // --- Step manager (drop-in) ---
    const pqEl = document.getElementById("post-quantity");
    const campaignDomainEl = document.getElementById("campaign-domain");
    let postCount = parseInt(pqEl?.value || "10", 10);
    let domainId;
    let lastDomainCategoryId = String(campaignDomainEl?.value || "");
    if (isNaN(postCount) || postCount <= 0) postCount = 10;

    // ✅ PATCH: keep postCount always synced AFTER user types a valid value
    // (does NOT remove your old logic; it only updates postCount when a valid number exists)
    function syncPostCountFromInput() {
        const el = document.getElementById("post-quantity");
        let postQtyshower = document.querySelector('.dy-post-count');
        const v = parseInt((el?.value || "").trim(), 10);
        if (!isNaN(v) && v > 0) {
            postCount = v;
            postQtyshower.textContent=`(${v})`;
            trimStoredSelection("selectArticles", v);
            trimStoredSelection("selectDomains", v);
            trimStoredSelection("selectSetDomains", v);

            if (window.articleSelectMgr && typeof window.articleSelectMgr.refresh === "function") {
                window.articleSelectMgr.refresh();
            }
            if (window.domainSelectMgr && typeof window.domainSelectMgr.refresh === "function") {
                window.domainSelectMgr.refresh();
            }
            if (window.domainSetSelectMgr && typeof window.domainSetSelectMgr.refresh === "function") {
                window.domainSetSelectMgr.refresh();
            }

            const selectedArticlesVal = document.getElementById("selected_articles_val");
            if (selectedArticlesVal) {
                selectedArticlesVal.value = "";
            }
        }
        return postCount;
    }

    function autoSelectByQuantity({
        checkboxSelector,
        localStorageKey,
        qty,
        manager = null
    }) {
        const limit = Math.max(0, parseInt(qty || 0, 10));
        if (limit <= 0) return;
        const existing = (() => {
            try {
                const raw = JSON.parse(localStorage.getItem(localStorageKey) || "[]");
                return Array.isArray(raw)
                    ? [...new Set(raw.map((v) => String(v)).filter(Boolean))]
                    : [];
            } catch (_) {
                return [];
            }
        })();

        const pageIds = Array.from(document.querySelectorAll(checkboxSelector))
            .map((cb) => String(cb.value))
            .filter(Boolean);

        const selected = [...existing];
        if (selected.length < limit) {
            for (const id of pageIds) {
                if (selected.length >= limit) break;
                if (!selected.includes(id)) selected.push(id);
            }
        }

        localStorage.setItem(localStorageKey, JSON.stringify(selected.slice(0, limit)));
        if (manager && typeof manager.refresh === "function") {
            manager.refresh();
        }
    }

    function trimStoredSelection(localStorageKey, qty) {
        const limit = Math.max(0, parseInt(qty || 0, 10));
        try {
            const raw = JSON.parse(localStorage.getItem(localStorageKey) || "[]");
            const ids = Array.isArray(raw)
                ? [...new Set(raw.map((v) => String(v)).filter(Boolean))]
                : [];
            localStorage.setItem(localStorageKey, JSON.stringify(ids.slice(0, limit)));
        } catch (_) {
            localStorage.setItem(localStorageKey, JSON.stringify([]));
        }
    }

    function getStoredSelectionCount(localStorageKey) {
        try {
            const raw = JSON.parse(localStorage.getItem(localStorageKey) || "[]");
            return Array.isArray(raw) ? raw.length : 0;
        } catch (_) {
            return 0;
        }
    }

    function sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    async function waitForPageMutation(containerSelector, checkboxSelector, previousSignature, timeoutMs = 5000) {
        const startedAt = Date.now();
        while ((Date.now() - startedAt) < timeoutMs) {
            const container = document.querySelector(containerSelector);
            const active = container?.querySelector(".page-active")?.textContent?.trim() || "";
            const firstId = document.querySelector(checkboxSelector)?.value || "";
            const currentSignature = `${active}::${firstId}`;
            if (currentSignature !== previousSignature) return true;
            await sleep(120);
        }
        return false;
    }

    function getCurrentPageSignature(containerSelector, checkboxSelector) {
        const container = document.querySelector(containerSelector);
        const active = container?.querySelector(".page-active")?.textContent?.trim() || "";
        const firstId = document.querySelector(checkboxSelector)?.value || "";
        return `${active}::${firstId}`;
    }

    function getNextPaginationButton(containerSelector) {
        const container = document.querySelector(containerSelector);
        if (!container) return null;
        const buttons = Array.from(container.querySelectorAll("button.page-btn, button"));
        if (!buttons.length) return null;

        const activeIndex = buttons.findIndex((btn) => btn.classList.contains("page-active"));
        if (activeIndex >= 0) {
            for (let i = activeIndex + 1; i < buttons.length; i++) {
                const candidate = buttons[i];
                if (candidate.disabled) continue;
                return candidate;
            }
        }

        for (const btn of buttons) {
            if (btn.disabled) continue;
            const txt = (btn.textContent || "").trim().toLowerCase();
            if (txt.includes("next") || txt === "»" || txt === ">") return btn;
        }

        return null;
    }

    async function autoSelectAcrossPages({
        localStorageKey,
        checkboxSelector,
        containerSelector,
        progressSelector,
        manager
    }) {
        syncPostCountFromInput();
        const progressEl = document.querySelector(progressSelector);
        const updateProgress = () => {
            const selected = getStoredSelectionCount(localStorageKey);
            if (progressEl) progressEl.textContent = `${selected}/${postCount} selected`;
            return selected;
        };

        let selectedCount = updateProgress();
        if (selectedCount >= postCount) return { done: true, selectedCount };

        let guard = 0;
        while (selectedCount < postCount && guard < 300) {
            guard += 1;

            autoSelectByQuantity({
                checkboxSelector,
                localStorageKey,
                qty: postCount,
                manager
            });
            selectedCount = updateProgress();
            if (selectedCount >= postCount) return { done: true, selectedCount };

            const before = getCurrentPageSignature(containerSelector, checkboxSelector);
            const nextBtn = getNextPaginationButton(containerSelector);
            if (!nextBtn) break;

            nextBtn.click();
            await waitForPageMutation(containerSelector, checkboxSelector, before, 6000);
            await sleep(120);
            selectedCount = updateProgress();
        }

        return { done: selectedCount >= postCount, selectedCount };
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

    function clearDomainSelectionsForCategoryChange() {
        localStorage.removeItem("selectDomains");
        localStorage.removeItem("selectSetDomains");
        localStorage.removeItem("lastDomainSet");

        if (window.domainSelectMgr && typeof window.domainSelectMgr.clear === "function") {
            window.domainSelectMgr.clear();
        }
        if (window.domainSetSelectMgr && typeof window.domainSetSelectMgr.clear === "function") {
            window.domainSetSelectMgr.clear();
        }

        const randomCount = document.getElementById("selectedDomainCount");
        const setCount = document.getElementById("selectedSetDomainCount");
        if (randomCount) randomCount.textContent = "0";
        if (setCount) setCount.textContent = "0";
    }

    if (campaignDomainEl) {
        campaignDomainEl.addEventListener("change", () => {
            const nowVal = String(campaignDomainEl.value || "");
            if (nowVal !== lastDomainCategoryId) {
                lastDomainCategoryId = nowVal;
                clearDomainSelectionsForCategoryChange();
            }
        });
    }

    (function () {
        // Tab buttons and sections
        const tabBtnArr = Array.from(document.getElementsByClassName("tab-switcher") || []);
        const campaignsSection = Array.from(document.querySelectorAll(".campaigns-section") || []);
        let currentStep = 0;
        // let postCount = 10;

        // --- ---

        // --- ---

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
            const selectDomain = document.getElementById("campaign-domain"); // it is domain category
            const postQuantity = document.getElementById("post-quantity");

            if (!campaignId || !selectDomain || !postQuantity)
                return { ok: false, msg: "Missing fields on step 1" };

            if (campaignId.value.trim() === "" || selectDomain.value.trim() === "" || postQuantity.value.trim() === "")
                return { ok: false, msg: "All fields should be filled" };

            const pq = parseInt(postQuantity.value);
            if (isNaN(pq) || pq <= 0)
                return { ok: false, msg: "Enter a valid post quantity" };

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

            const selectedArticles = (() => {
                try {
                    const raw = JSON.parse(localStorage.getItem("selectArticles") || "[]");
                    return Array.isArray(raw) ? raw : [];
                } catch (_) {
                    return [];
                }
            })();

            if (selectedArticlesVal) {
                selectedArticlesVal.value = selectedArticles.join(",");
            }

            if (selectedArticles.length !== postCount)
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

    // utility function for pagination container

    // async function renderArticle(id, currentPage, loader, articleTable, openPopup, per_page) {
    //     try {

    //         const url = `/api/admin/set/articles/${id}?page=${currentPage}&per_page=${per_page}`; // ✅ avoid hardcoded host
    //         const response = await fetch(url, {
    //             headers: { Accept: "application/json" },
    //         });

    //         // ✅ handle non-200 responses safely
    //         const result = await response.json().catch(() => null);
    //         console.log("result here")
    //         console.log(result);

    //         if (loader) loader.classList.add("hidden");

    //         if (!result || result.status !== true) {
    //             alert(result?.message || "Something went wrong.");
    //             return;
    //         }

    //         // based on your API response:
    //         // result.data.article_set.count
    //         // result.data.articles
    //         const setCount = result.data?.article_set?.count ?? 0;

    //         if (setCount < 1) {
    //             alert("there is no article in the set");
    //             return;
    //         }

    //         if (setCount < postCount) {
    //             alert("the selective set doesnot have enough articles according to post count");
    //             return;
    //         }
    //         console.log("Articles");
    //         console.log(result.data.articles)
    //         const articles = Array.isArray(result.data?.articles?.data) ? result.data.articles.data : [];
    //         console.log(articles)
    //         // ✅ Clear and add rows using DataTables API (pagination works)
    //         // const dt = window.myDT;
    //         if (window.articleSelectMgr) {
    //             window.articleSelectMgr.clear();
    //         }
    //         articleTable.innerHTML = '';
    //         Array.from(articles).forEach((item, idx) => {
    //             console.log(item)
    //             let tr = document.createElement('tr');
    //             tr.className = 'hover:bg-gray-50'
    //             tr.innerHTML = `
    //                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center "> 
    //                                    <input
    //                                         type="checkbox" name="bulk_category_select[]"
    //                                         id="sel-check-box-${idx + 1}" class="articles"
    //                                         value="${item.id}" id="">
    //                                     </td>
    //                                     <td class="border border-gray-200 font-sans !px-2 !py-[6px]">${idx + 1}
    //                                     </td>
    //                                     <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
    //                                         <label for="sel-check-box-${idx + 1}" class="cursor-pointer">
    //                                             ${item.name}
    //                                     </td></label>

    //                                     <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
    //                                         <div class="flex flex-wrap gap-2 justify-center">

    //                                             <a href="javascript:void(0)" data-id="${item.id}"
    //                                                 class="bg-green-600 flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-green-800 relative overflow dy-nested-pop-btn">
    //                                                 <span class="material-symbols-outlined !text-sm  text-white">
    //                                                     visibility
    //                                                 </span>
    //                                             </a>
    //                                         </div>
    //                                     </td>
    //                         `;
    //             articleTable.append(tr);
    //         })
    //         renderPagination(result.data.articles.links)
    //         // dt.rows.add(rows).draw();
    //         if (window.articleSelectMgr) {
    //             window.articleSelectMgr.refresh();
    //         }

    //         // ****** we init the table so it doesnot get all value ***

    //         window.articleSelectMgr = initSelectManager({
    //             checkboxSelector: '.articles',
    //             selectAllSelector: '#selectAll',
    //             countSelector: '#selectedCount',
    //             randomBtnSelector: '#randomSelector',
    //             inputSelector: '#randomSelect',
    //             localStorageKey: 'selectArticles',
    //             highlightClass: '!bg-blue-100',
    //             parentSelector: 'tr',
    //             maxQuantityInputSelector: "#post-quantity",
    //             enableRandom: true
    //         });

    //         // ***** ends here *****


    //         // ✅ open popup after data is ready
    //         openPopup();

    //         // ✅ if table is inside popup/modal, adjust columns
    //         // setTimeout(() => {
    //         //     dt.columns.adjust().responsive?.recalc?.();
    //         // }, 80);


    //     } catch (error) {
    //         if (loader) loader.classList.add("hidden");
    //         console.error(error);
    //         alert("something went wrong");
    //     }
    // }

    async function renderArticle({
        url = null,
        id,
        loader,
        articleTableBody,
        openPopup,
        per_page,
        paginationLoader,
        isPagination = false,
        autoSelect = false
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

            articleTableBody.innerHTML = '';



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

            if (autoSelect) {
                autoSelectByQuantity({
                    checkboxSelector: ".articles",
                    localStorageKey: "selectArticles",
                    qty: postCount,
                    manager: window.articleSelectMgr
                });
            }

            // ✅ IMPORTANT
            renderPagination(result.data.articles.links, {
                id,
                loader,
                articleTableBody,
                openPopup,
                per_page,
                paginationLoader,
                autoSelect
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
        postCount,
        autoSelect = false
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

            if (autoSelect) {
                autoSelectByQuantity({
                    checkboxSelector: ".articles",
                    localStorageKey: "selectArticles",
                    qty: postCount,
                    manager: window.articleSelectMgr
                });
            }

            // 🔹 Render pagination
            renderSearchPagination(meta.links, {
                searchData,
                articleTableBody,
                loader,
                paginationLoader,
                per_page,
                postCount,
                autoSelect
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
        openPopup,
        autoSelect = false
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

            if (autoSelect) {
                autoSelectByQuantity({
                    checkboxSelector: ".articles",
                    localStorageKey: "selectArticles",
                    qty: postCount,
                    manager: window.articleSelectMgr
                });
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
                    openPopup,
                    autoSelect
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
        per_page = 100,
        isPagination = false,
        autoSelect = false
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

            tableBody.innerHTML = '';

            // ✅ Pagination-safe validation
            if (meta.total < postCount) {
                alert("Domains quantity must be greater or equal to post quantity");
                return;
            }



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

            if (autoSelect) {
                autoSelectByQuantity({
                    checkboxSelector: ".domains",
                    localStorageKey: "selectDomains",
                    qty: postCount,
                    manager: window.domainSelectMgr
                });
            }

            // Render pagination buttons
            renderDomainPagination(meta.links, {
                domainCategoryId,
                tableBody,
                loader,
                paginationLoader,
                per_page,
                autoSelect
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
        per_page = 100,
        isPagination = false,
        autoSelect = false
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

            tableBody.innerHTML = '';

            if (!res?.status) {
                alert(res?.message || 'Failed to load domains');
                return;
            }

            const meta = res.data.domains;
            const domains = meta.data;



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

            if (autoSelect) {
                autoSelectByQuantity({
                    checkboxSelector: ".setdomains",
                    localStorageKey: "selectSetDomains",
                    qty: postCount,
                    manager: window.domainSetSelectMgr
                });
            }

            renderDomainSetPagination(meta.links, {
                domainSetId,
                tableBody,
                loader,
                paginationLoader,
                per_page,
                autoSelect
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

    // ---------------


    // function renderPagination(links) {
    //     const container = document.getElementById('pagination-container')
    //     container.innerHTML = ''

    //     if (!links || links.length === 0) return

    //     links.forEach(link => {
    //         // Dots (...)
    //         if (link.label === '...') {
    //             const span = document.createElement('span')
    //             span.textContent = '...'
    //             span.className = 'px-2 text-gray-500'
    //             container.appendChild(span)
    //             return
    //         }

    //         const btn = document.createElement('button')
    //         btn.innerHTML = link.label
    //         btn.className = 'page-btn'

    //         if (link.active) {
    //             btn.classList.add('page-active')
    //         }

    //         if (!link.url) {
    //             btn.classList.add('page-disabled')
    //             btn.disabled = true
    //         }

    //         btn.onclick = () => {
    //             if (link.url) {
    //                 fetchArticles(link.url) // 🔥 follow Laravel URL
    //             }
    //         }

    //         container.appendChild(btn)
    //     })
    // }


    // *** ends here ***

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
                    let per_page = 100;
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
                        isPagination,
                        autoSelect: true
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
                            per_page: 100,
                            postCount,
                            autoSelect: true
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
                            openPopup: openPopup,
                            autoSelect: true
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

            const autoSelectArticlesBtn = document.getElementById("autoSelectArticlesBtn");
            const autoSelectArticlesProgress = document.getElementById("autoSelectArticlesProgress");
            if (autoSelectArticlesBtn) {
                autoSelectArticlesBtn.addEventListener("click", async (e) => {
                    e.preventDefault();
                    syncPostCountFromInput();

                    if (!document.querySelector(".articles")) {
                        alert("Load articles first, then click Auto Select Required.");
                        return;
                    }

                    autoSelectArticlesBtn.disabled = true;
                    if (autoSelectArticlesProgress) {
                        autoSelectArticlesProgress.textContent = "Auto selecting...";
                    }

                    try {
                        const result = await autoSelectAcrossPages({
                            localStorageKey: "selectArticles",
                            checkboxSelector: ".articles",
                            containerSelector: "#pagination-container",
                            progressSelector: "#autoSelectArticlesProgress",
                            manager: window.articleSelectMgr
                        });

                        if (!result.done) {
                            alert(`Only ${result.selectedCount}/${postCount} articles are available in loaded pages.`);
                        }
                    } catch (err) {
                        console.error(err);
                        alert("Auto select failed for articles.");
                    } finally {
                        autoSelectArticlesBtn.disabled = false;
                    }
                });
            }


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

        function countNonEmptyLines(value) {
            return String(value || "")
                .split("\n")
                .map((line) => line.trim())
                .filter((line) => line !== "").length;
        }

        function updateMultiBulkProgress() {
            syncPostCountFromInput();

            const postQtyNode = document.getElementById("multi-bulk-post-qty");
            const filledPairsNode = document.getElementById("multi-bulk-filled-pairs");
            const totalUrlNode = document.getElementById("multi-bulk-total-url-lines");
            const totalKeywordNode = document.getElementById("multi-bulk-total-keyword-lines");

            const urlInputs = Array.from(document.querySelectorAll(".multi-bulk-url"));
            const keywordInputs = Array.from(document.querySelectorAll(".multi-bulk-keyword"));
            const urlCountNodes = Array.from(document.querySelectorAll(".multi-bulk-url-count"));
            const keywordCountNodes = Array.from(document.querySelectorAll(".multi-bulk-keyword-count"));

            let totalUrls = 0;
            let totalKeywords = 0;
            let filledPairs = 0;

            for (let i = 0; i < 5; i++) {
                const urlCount = countNonEmptyLines(urlInputs[i]?.value || "");
                const keywordCount = countNonEmptyLines(keywordInputs[i]?.value || "");

                if (urlCountNodes[i]) urlCountNodes[i].textContent = String(urlCount);
                if (keywordCountNodes[i]) keywordCountNodes[i].textContent = String(keywordCount);

                totalUrls += urlCount;
                totalKeywords += keywordCount;

                if (urlCount > 0 || keywordCount > 0) {
                    filledPairs++;
                }
            }

            if (postQtyNode) postQtyNode.textContent = String(postCount);
            if (filledPairsNode) filledPairsNode.textContent = String(filledPairs);
            if (totalUrlNode) totalUrlNode.textContent = String(totalUrls);
            if (totalKeywordNode) totalKeywordNode.textContent = String(totalKeywords);
        }

        document.querySelectorAll(".multi-bulk-url, .multi-bulk-keyword").forEach((el) => {
            el.addEventListener("input", updateMultiBulkProgress);
        });
        if (pqEl) {
            pqEl.addEventListener("input", updateMultiBulkProgress);
            pqEl.addEventListener("change", updateMultiBulkProgress);
        }
        updateMultiBulkProgress();

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
            updateNormalKeywordProgress();
        }

        function parseQtyLines(text) {
            return String(text || "")
                .split("\n")
                .map((v) => parseInt(v.trim(), 10))
                .filter((n) => Number.isFinite(n) && n > 0)
                .reduce((a, b) => a + b, 0);
        }

        function ensureOverallProgressNode() {
            const host = document.querySelector(".add-more-window.keyword-tab-sec > div");
            if (!host) return null;
            let node = document.getElementById("overall-keyword-progress");
            if (node) return node;
            node = document.createElement("p");
            node.id = "overall-keyword-progress";
            node.className = "!px-3 !py-2 rounded border-2 border-blue-300 bg-blue-50 text-blue-800 font-semibold text-sm shadow-sm";
            host.appendChild(node);
            return node;
        }

        function updateNormalKeywordProgress() {
            syncPostCountFromInput();
            const boxes = [...document.querySelectorAll(".keyword-url-box")];
            let usedUrlQty = 0;
            let usedKeywordQty = 0;

            boxes.forEach((box) => {
                const target = parseInt(box.querySelector(".client-url-quantity")?.value || "0", 10) || 0;
                const assigned = parseQtyLines(box.querySelector(".keywords-quantity-area")?.value || "");
                usedUrlQty += target;
                usedKeywordQty += assigned;
            });

            const overallNode = ensureOverallProgressNode();
            if (overallNode) {
                const remainingUrlQty = Math.max(postCount - usedUrlQty, 0);
                const remainingKeywordQty = Math.max(postCount - usedKeywordQty, 0);
                overallNode.textContent =
                    `URL ${usedUrlQty}/${postCount} | KW ${usedKeywordQty}/${postCount}`;
            }
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
                    updateNormalKeywordProgress();
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
                    updateNormalKeywordProgress();
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
                    updateNormalKeywordProgress();
                    return;
                }
            }
            const keywordQuantityArea = parentBox.querySelector(
                ".keywords-quantity-area"
            );
            if (keywordQuantityArea) keywordQuantityArea.value = val;
            updateNormalKeywordProgress();
        }

        // === Delete handler ===
        keywordUrlCon.addEventListener("click", (e) => {
            if (e.target.classList.contains("remove-keyword-box")) {
                e.preventDefault();
                e.stopPropagation();
                e.target.closest(".keyword-url-box").remove();
                updateBoxCount();
                updateBorders();
                updateNormalKeywordProgress();
                // redistributeQuantities();
            }
        });

        // Keep KW progress synced when user edits quantity textarea manually.
        keywordUrlCon.addEventListener("input", (e) => {
            if (e.target.classList.contains("keywords-quantity-area")) {
                updateNormalKeywordProgress();
            }
        });

        // === Add new keyword box ===
        AddMoreBtn.addEventListener("click", (e) => {
            e.preventDefault();

            // ✅ PATCH: sync right before checking keywordBoxCount >= postCount
            syncPostCountFromInput();

            let div = document.createElement("div");
            div.className =
                "flex w-full flex-col lg:flex-row bg-orange-100 keyword-url-box relative keyword-mobile-stack rounded border border-orange-200";
            div.innerHTML = `
        <div class="w-full lg:w-3/5 flex flex-col gap-3 !p-3 sm:!p-4 !pt-10 keyword-mobile-main">
            <div class="w-full flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2">
                <label class="text-sm font-medium flex items-center shrink-0 after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-full sm:w-1/5 keyword-mobile-label">Client Url</label>
                <div class="w-full sm:w-4/5 flex flex-col sm:flex-row gap-2 sm:items-center keyword-mobile-input-wrap">
                    <input type="text" placeholder="Enter Url" class="bg-white !p-2.5 text-sm outline-none border border-gray-300 w-full sm:w-[78%] client-url keyword-mobile-input-main rounded">
                    <input type="text" value="0" class="bg-white !p-2.5 text-sm outline-none text-center border border-gray-300 w-full sm:w-1/5 client-url-quantity num-inp keyword-mobile-qty rounded">
                </div>
            </div>
            <div class="w-full flex flex-col sm:flex-row sm:items-start gap-1.5 sm:gap-2">
                <label class="text-sm font-medium flex items-center shrink-0 after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-full sm:w-1/5 keyword-mobile-label">Media Link</label>
                <div class="w-full sm:w-4/5 keyword-mobile-input-wrap">
                    <textarea class="bg-white !p-2.5 text-sm outline-none border border-gray-300 w-full resize-y media-link rounded min-h-[72px]" rows="2" placeholder="Enter Media Link Here"></textarea>
                </div>
            </div>
            <div class="w-full flex items-center justify-end">
                <button type="button" class="!px-2.5 !py-1 bg-red-600 rounded text-xs sm:text-sm cursor-pointer text-white remove-keyword-box">Delete</button>
            </div>
        </div>
        <div class="w-full lg:w-2/5 flex flex-col gap-1.5 !p-3 sm:!p-4 border-t lg:border-t-0 lg:border-l border-orange-200 keyword-mobile-side">
            <label class="text-sm font-medium flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Client Keyword</label>
            <div class="w-full flex flex-col sm:flex-row gap-2 keywords-area-parent">
                <div class="w-full sm:w-[78%] keyword-mobile-input-main min-w-0">
                    <textarea rows="5" class="bg-white !p-2.5 text-sm outline-none border border-gray-300 w-full resize-y keywords-area rounded min-h-[100px]"></textarea>
                </div>
                <div class="w-full sm:w-[22%] keyword-mobile-qty min-w-0">
                    <textarea rows="5" class="bg-white !p-2.5 text-sm outline-none border border-gray-300 w-full resize-y text-center keywords-quantity-area rounded min-h-[60px] sm:min-h-[100px]" placeholder="Qty"></textarea>
                </div>
            </div>
        </div>
        <div class="box-count flex !px-2.5 !py-0.5 text-xs sm:text-sm font-semibold bg-[var(--primary-color)] text-white rounded absolute top-2.5 left-2.5">01</div>
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
            updateNormalKeywordProgress();
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
        updateNormalKeywordProgress();

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

        function ensureMultiOverallProgressNode() {
            const host = document.querySelector(".add-more-multi-window > div");
            if (!host) return null;
            let node = document.getElementById("overall-multi-keyword-progress");
            if (node) return node;
            node = document.createElement("p");
            node.id = "overall-multi-keyword-progress";
            node.className = "!px-3 !py-2 rounded border-2 border-blue-300 bg-blue-50 text-blue-800 font-semibold text-sm shadow-sm";
            host.appendChild(node);
            return node;
        }

        function updateMultiKeywordProgress() {
            syncPostCountFromInput();
            const qtyInputs = Array.from(document.querySelectorAll(".multi-keyword-url-box-quantity"));
            const usedQty = qtyInputs.reduce((sum, inp) => sum + (parseInt(inp.value || "0", 10) || 0), 0);
            const remainingAll = Math.max(postCount - usedQty, 0);
            const overallNode = ensureMultiOverallProgressNode();
            if (overallNode) {
                overallNode.textContent = `URL ${usedQty}/${postCount} | KW ${usedQty}/${postCount}`;
            }
        }

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
                updateMultiKeywordProgress();

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
                updateMultiKeywordProgress();

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
                updateMultiKeywordProgress();
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
                updateMultiKeywordProgress();
            }
        })

        updateMultiKeywordProgress();


        // Raw HTML line counter and post quantity display
        const rawHtmlTextarea = document.getElementById('raw-html-anchors');
        const rawHtmlLineCount = document.getElementById('raw-html-line-count');
        const rawHtmlPostQty = document.getElementById('raw-html-post-qty');

        if (rawHtmlTextarea && rawHtmlLineCount && rawHtmlPostQty) {
            // Update post quantity display
            rawHtmlPostQty.textContent = postCount;

            // Update line count on input
            rawHtmlTextarea.addEventListener('input', function() {
                const lines = this.value.split('\n').filter(line => line.trim() !== '').length;
                rawHtmlLineCount.textContent = lines;

                // Highlight if line count doesn't match post quantity
                if (lines !== postCount && lines > 0) {
                    rawHtmlLineCount.style.color = 'red';
                    rawHtmlLineCount.style.fontWeight = 'bold';
                } else {
                    rawHtmlLineCount.style.color = '';
                    rawHtmlLineCount.style.fontWeight = '';
                }
            });
        }


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
            const sponsored = document.getElementById("sponsored_link")?.checked
                ? document.getElementById("sponsored_link").value
                : "";
            const ugc = document.getElementById("ugc_link")?.checked
                ? document.getElementById("ugc_link").value
                : "";
            const noopener = document.getElementById("noopener_link")?.checked
                ? document.getElementById("noopener_link").value
                : "";
            const noreferrer = document.getElementById("noreferrer_link")?.checked
                ? document.getElementById("noreferrer_link").value
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
                            sponsored: sponsored,
                            ugc: ugc,
                            noopener: noopener,
                            noreferrer: noreferrer,
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
                        sponsored: sponsored,
                        ugc: ugc,
                        noopener: noopener,
                        noreferrer: noreferrer,
                    });
                }

                keywords_url_data = bulkData;
            }
            if (method == "multi_bulk") {
                const multiBulkUrlInputs = Array.from(
                    document.querySelectorAll(".multi-bulk-url")
                );
                const multiBulkKeywordInputs = Array.from(
                    document.querySelectorAll(".multi-bulk-keyword")
                );

                const usedPairs = [];
                const pairErrors = [];

                for (let i = 0; i < 5; i++) {
                    const urls = (multiBulkUrlInputs[i]?.value || "")
                        .split("\n")
                        .map((line) => line.trim())
                        .filter((line) => line !== "");
                    const keywords = (multiBulkKeywordInputs[i]?.value || "")
                        .split("\n")
                        .map((line) => line.trim())
                        .filter((line) => line !== "");

                    // Allow empty pair; validate only when user uses either side.
                    if (urls.length === 0 && keywords.length === 0) {
                        continue;
                    }

                    if (urls.length === 0 || keywords.length === 0) {
                        pairErrors.push(`Pair ${i + 1} needs both URL and keyword lines.`);
                        continue;
                    }

                    if (urls.length !== keywords.length) {
                        pairErrors.push(`Pair ${i + 1} URL/keyword line count must match.`);
                        continue;
                    }

                    if (urls.length > postCount) {
                        pairErrors.push(`Pair ${i + 1} cannot exceed post quantity (${postCount}).`);
                        continue;
                    }

                    usedPairs.push({ urls, keywords });
                }

                if (pairErrors.length > 0) {
                    alert(pairErrors.join("\n"));
                    return;
                }

                if (usedPairs.length < 1) {
                    alert("Please fill at least 1 URL/keyword pair.");
                    return;
                }
                if (usedPairs[0].urls.length !== postCount) {
                    alert(`Pair 1 must have exactly ${postCount} URL/keyword lines.`);
                    return;
                }

                let multiBulkData = Array.from({ length: postCount }, () => ({
                    url: [],
                    keyword: [],
                    media: "-",
                    nofollow: noFollow,
                    sponsored: sponsored,
                    ugc: ugc,
                    noopener: noopener,
                    noreferrer: noreferrer,
                }));

                usedPairs.forEach((pair) => {
                    for (let lineIndex = 0; lineIndex < pair.urls.length; lineIndex++) {
                        if (lineIndex >= postCount) break;
                        multiBulkData[lineIndex].url.push(pair.urls[lineIndex]);
                        multiBulkData[lineIndex].keyword.push(pair.keywords[lineIndex]);
                    }
                });

                // Safety: every website must have at least one link pair from pair 1.
                const hasEmptyRow = multiBulkData.some(
                    (row) => row.url.length === 0 || row.keyword.length === 0
                );
                if (hasEmptyRow) {
                    alert("Some websites still have no link data. Please complete Pair 1 correctly.");
                    return;
                }

                keywords_url_data = multiBulkData;
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
                    singleBoxData.sponsored = sponsored;
                    singleBoxData.ugc = ugc;
                    singleBoxData.noopener = noopener;
                    singleBoxData.noreferrer = noreferrer;
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

            // Raw HTML parsing method
            if (method == "raw_html") {
                let rawHtmlData = [];
                let rawHtmlTextarea = document.getElementById("raw-html-anchors");

                if (!rawHtmlTextarea || !rawHtmlTextarea.value.trim()) {
                    alert("Please paste anchor tags in the Raw HTML field");
                    return;
                }

                let lines = rawHtmlTextarea.value.split('\n').filter(line => line.trim() !== '');

                if (lines.length !== postCount) {
                    alert(`Number of lines (${lines.length}) must equal Post Quantity (${postCount})`);
                    return;
                }

                // Function to parse a single anchor tag
                function parseAnchorTag(anchorHtml) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(anchorHtml.trim(), 'text/html');
                    const anchor = doc.querySelector('a');

                    if (!anchor) {
                        return null;
                    }

                    const url = anchor.getAttribute('href') || '';
                    const keyword = anchor.textContent.trim() || '';
                    const relAttr = anchor.getAttribute('rel') || '';

                    // Parse all rel attributes dynamically
                    const relValues = relAttr.split(/\s+/).filter(v => v.trim() !== '');
                    const relObj = {};

                    // Check for common rel attributes (for backward compatibility with checkbox mode)
                    relObj.nofollow = relValues.includes('nofollow') ? '1' : '';
                    relObj.sponsored = relValues.includes('sponsored') ? '1' : '';
                    relObj.ugc = relValues.includes('ugc') ? '1' : '';
                    relObj.noopener = relValues.includes('noopener') ? '1' : '';
                    relObj.noreferrer = relValues.includes('noreferrer') ? '1' : '';

                    // ✅ PRESERVE FULL rel ATTRIBUTE STRING (supports custom values like "external", "bookmark", etc.)
                    relObj.raw_rel_attr = relAttr.trim();

                    return {
                        url: url,
                        keyword: keyword,
                        ...relObj
                    };
                }

                // Process each line
                for (let i = 0; i < lines.length; i++) {
                    const line = lines[i].trim();

                    // Split by comma to handle multiple anchors per line
                    const anchors = line.split(',').map(a => a.trim()).filter(a => a !== '');

                    if (anchors.length > 5) {
                        alert(`Line ${i + 1} has more than 5 anchors. Maximum is 5 per line.`);
                        return;
                    }

                    const urls = [];
                    const keywords = [];
                    let lineNofollow = '';
                    let lineSponsored = '';
                    let lineUgc = '';
                    let lineNoopener = '';
                    let lineNoreferrer = '';
                    let lineRawRelAttr = '';

                    for (let j = 0; j < anchors.length; j++) {
                        const parsed = parseAnchorTag(anchors[j]);

                        if (!parsed || !parsed.url || !parsed.keyword) {
                            alert(`Line ${i + 1}, anchor ${j + 1}: Invalid anchor tag or missing URL/keyword`);
                            return;
                        }

                        urls.push(parsed.url);
                        keywords.push(parsed.keyword);

                        // Use rel attributes from first anchor (supports custom rel values via raw_rel_attr)
                        if (j === 0) {
                            lineNofollow = parsed.nofollow;
                            lineSponsored = parsed.sponsored;
                            lineUgc = parsed.ugc;
                            lineNoopener = parsed.noopener;
                            lineNoreferrer = parsed.noreferrer;
                            lineRawRelAttr = parsed.raw_rel_attr || '';
                        }
                    }

                    rawHtmlData.push({
                        url: urls.length === 1 ? urls[0] : urls,
                        keyword: keywords.length === 1 ? keywords[0] : keywords,
                        media: '',
                        nofollow: lineNofollow,
                        sponsored: lineSponsored,
                        ugc: lineUgc,
                        noopener: lineNoopener,
                        noreferrer: lineNoreferrer,
                        raw_rel_attr: lineRawRelAttr,
                    });
                }

                keywords_url_data = rawHtmlData;
            }

            let keywordsDataTable = document.getElementById(
                "keywords-data-table"
            );
            keywordsDataHolder.value = JSON.stringify(keywords_url_data);
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

        // function buildDomainRows(domains) {
        //     return domains.map((domain, index) => {
        //         const checkbox = `
        //     <input
        //         type="checkbox"
        //         name="bulk_domain_select[]"
        //         class="domains"
        //         value="${domain.id}"
        //         id="sel-domain-check-box-${index + 1}"
        //     >
        // `;

        //         const sno = index + 1;

        //         const domainCol = `
        //     <label for="sel-domain-check-box-${index + 1}" class="cursor-pointer w-full">
        //         ${domain.name}
        //     </label>
        // `;

        //         return [
        //             checkbox,
        //             sno,
        //             domainCol,
        //             domain.da ?? '-',
        //             domain.tf ?? '-',
        //             domain.dr ?? '-',
        //             domain.ss ?? '-'
        //         ];
        //     });
        // }

        // window.__onEnterStep04 = async function () {

        //     const selected = document.querySelector('input[name="sel_domains"]:checked');
        //     if (!selected) return;

        //     const showDomainLoader = document.querySelector('.domain-loader-pop');

        //     try {
        //         showDomainLoader.classList.remove('hidden');
        //         setTimeout(() => showDomainLoader.classList.remove('opacity-0'), 200);

        //         const url = `/api/admin/domains/${domainId}`; // ✅ no localhost
        //         const api = await fetch(url, { headers: { Accept: "application/json" } });
        //         const res = await api.json();

        //         if (!res.status || !Array.isArray(res.data)) {
        //             alert(res.message || "Failed to fetch domains");
        //             return;
        //         }
        //         console.log(res.data.length);
        //         if (res.data.length < postCount) {
        //             alert("Domains quantity should be greater or equal to post quantity in order to run campaign");
        //             return;
        //         }

        //         // ✅ Build rows
        //         const rows = buildDomainRows(res.data);

        //         // ✅ Clear & append into DataTable


        //         // ✅ refresh checkbox manager if you have one
        //         if (window.domainSelectMgr) {
        //             window.domainSelectMgr.refresh();
        //         }

        //     } catch (e) {
        //         console.error(e);
        //         alert("Something went wrong while loading domains");
        //     } finally {
        //         showDomainLoader.classList.add('opacity-0');
        //         setTimeout(() => showDomainLoader.classList.add('hidden'), 300);
        //     }
        // };

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
                    per_page: 100,
                    isPagination: false,
                    autoSelect: true
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
                per_page: 100,
                isPagination: false,
                autoSelect: true
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


        // ********* fetching ends here ********* //

        const autoSelectRandomDomainsBtn = document.getElementById('autoSelectRandomDomainsBtn');
        if (autoSelectRandomDomainsBtn) {
            autoSelectRandomDomainsBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                syncPostCountFromInput();

                if (!document.querySelector('.domains')) {
                    alert('Load random domains first, then click Auto Select Required.');
                    return;
                }

                autoSelectRandomDomainsBtn.disabled = true;
                const progressEl = document.getElementById('autoSelectRandomDomainsProgress');
                if (progressEl) progressEl.textContent = 'Auto selecting...';

                try {
                    const result = await autoSelectAcrossPages({
                        localStorageKey: 'selectDomains',
                        checkboxSelector: '.domains',
                        containerSelector: '#domain-pagination',
                        progressSelector: '#autoSelectRandomDomainsProgress',
                        manager: window.domainSelectMgr
                    });
                    if (!result.done) {
                        alert(`Only ${result.selectedCount}/${postCount} domains are available.`);
                    }
                } catch (err) {
                    console.error(err);
                    alert('Auto select failed for random domains.');
                } finally {
                    autoSelectRandomDomainsBtn.disabled = false;
                }
            });
        }

        const autoSelectSetDomainsBtn = document.getElementById('autoSelectSetDomainsBtn');
        if (autoSelectSetDomainsBtn) {
            autoSelectSetDomainsBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                syncPostCountFromInput();

                if (!document.querySelector('.setdomains')) {
                    alert('Load domain set first, then click Auto Select Required.');
                    return;
                }

                autoSelectSetDomainsBtn.disabled = true;
                const progressEl = document.getElementById('autoSelectSetDomainsProgress');
                if (progressEl) progressEl.textContent = 'Auto selecting...';

                try {
                    const result = await autoSelectAcrossPages({
                        localStorageKey: 'selectSetDomains',
                        checkboxSelector: '.setdomains',
                        containerSelector: '#domain-set-pagination',
                        progressSelector: '#autoSelectSetDomainsProgress',
                        manager: window.domainSetSelectMgr
                    });
                    if (!result.done) {
                        alert(`Only ${result.selectedCount}/${postCount} domains are available in this set.`);
                    }
                } catch (err) {
                    console.error(err);
                    alert('Auto select failed for domain set.');
                } finally {
                    autoSelectSetDomainsBtn.disabled = false;
                }
            });
        }



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

        function getManualDomainAlertBox() {
            let box = document.getElementById('manual-domain-inline-alert');
            if (box) return box;
            box = document.createElement('div');
            box.id = 'manual-domain-inline-alert';
            box.className = 'hidden !p-4 text-sm rounded bg-red-100 text-red-700 w-full !mb-3';
            const host = document.getElementById('campaign-form');
            if (host) host.insertBefore(box, host.firstChild);
            return box;
        }

        function hideManualDomainAlert() {
            const box = document.getElementById('manual-domain-inline-alert');
            if (!box) return;
            box.classList.add('hidden');
            box.innerHTML = '';
        }

        function showManualDomainAlert(message, missingDomains = []) {
            const box = getManualDomainAlertBox();
            if (!box) return;
            const listHtml = Array.isArray(missingDomains) && missingDomains.length
                ? `<div class="!mt-2"><strong>Missing domains:</strong><br>${missingDomains.map((d) => String(d)).join('<br>')}</div>`
                : '';
            box.innerHTML = `<span class="font-medium">${message}</span>${listHtml}`;
            box.classList.remove('hidden');
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }


        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            syncPostCountFromInput();
            hideManualDomainAlert();

            const selectedArticlesInput = document.getElementById("selected_articles_val");
            const selectedArticles = (() => {
                try {
                    const raw = JSON.parse(localStorage.getItem("selectArticles") || "[]");
                    return Array.isArray(raw) ? raw.map((v) => String(v)).filter(Boolean) : [];
                } catch (_) {
                    return [];
                }
            })();

            if (selectedArticlesInput) {
                selectedArticlesInput.value = selectedArticles.join(",");
            }

            if (selectedArticles.length !== postCount) {
                alert(`Please select exactly ${postCount} articles before submit.`);
                return;
            }

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
                    showManualDomainAlert(`Manual domains must be equal to post count (${postCount}).`);
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
                        showManualDomainAlert(
                            res.message || 'Domain validation failed.',
                            Array.isArray(res?.data?.missing) ? res.data.missing : []
                        );
                        return;
                    }

                    campaignDomainHolder.value = JSON.stringify(res.data.domain_ids);

                } catch (error) {
                    console.error('Domain validation error:', error);
                    showManualDomainAlert('Something went wrong while validating domains.');

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
