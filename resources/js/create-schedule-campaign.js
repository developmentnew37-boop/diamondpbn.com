import { initDynamicRadioGroup, initPopup, nestedPop } from "./general";
import { initSelectManager } from "./selectBox";
export function allowOnlyNumbers(numInp) {
    if (numInp.length) {
        Array.from(numInp).forEach((i) => {
            i.addEventListener("input", function () {
                this.value = this.value.replace(/[^0-9]/g, "");
            });
        });
    }
}

// *************** avoiding default input **********************

document.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && e.target.tagName === "INPUT") {
        e.preventDefault();
    }
});


// ************************************************************************************************************************************

// ✅ FULL CODE (with patches) — NO LINES REMOVED, ONLY ADDED/UPDATED INLINE

window.addEventListener("DOMContentLoaded", () => {

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
        const v = parseInt((el?.value || "").trim(), 10);
        if (!isNaN(v) && v > 0) {
            postCount = v;
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

            // ✅ Single reusable function
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

            // ✅ Attach listener only if element exists
            if (addOwnArticles) {
                let ownArticleInp = document.getElementById("own-articles"); // contains what want to choose your article set or all articles

                addOwnArticles.addEventListener("click", async (e) => {
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    localStorage.removeItem('selectArticles');
                    if (!ownArticleInp || ownArticleInp.value === "") {
                        alert("please select your articles or set");
                        return;
                    }

                    // ✅ support clicking on inner elements (icon/span) by using currentTarget
                    const btn = e.currentTarget;
                    const loader = btn.querySelector(".article-set-loader");

                    const id = ownArticleInp.value;

                    // ✅ Use global DataTable instance initialized in Blade: window.myDT
                    if (!window.myDT) {
                        alert("DataTable is not initialized. Please reload the page.");
                        return;
                    }

                    if (loader) loader.classList.remove("hidden");

                    try {

                        const url = `/api/admin/set/articles/${id}`; // ✅ avoid hardcoded host
                        const response = await fetch(url, {
                            headers: { Accept: "application/json" },
                        });

                        // ✅ handle non-200 responses safely
                        const result = await response.json().catch(() => null);

                        if (loader) loader.classList.add("hidden");

                        if (!result || result.status !== true) {
                            alert(result?.message || "Something went wrong.");
                            return;
                        }

                        // based on your API response:
                        // result.data.article_set.count
                        // result.data.articles
                        const setCount = result.data?.article_set?.count ?? 0;

                        if (setCount < 1) {
                            alert("there is no article in the set");
                            return;
                        }

                        if (setCount < postCount) {
                            alert("the selective set doesnot have enough articles according to post count");
                            return;
                        }

                        const articles = Array.isArray(result.data?.articles) ? result.data.articles : [];

                        // ✅ Clear and add rows using DataTables API (pagination works)
                        const dt = window.myDT;
                        window.articleSelectMgr.clear();
                        dt.clear();

                        const rows = articles.map((element, index) => {
                            const checkboxCol = `
                                 <input type="checkbox" name="bulk_category_select[]"
                                   id="sel-check-box-${index + 1}"
                                   class="articles"
                                   value="${element.id}">
                               `;

                            const snoCol = index + 1;

                            const nameCol = `
                                   <label for="sel-check-box-${index + 1}" class="cursor-pointer">
                                     ${element.name ?? ""}
                                   </label>
                                 `;

                            const actionCol = `
                                     <div class="flex flex-wrap gap-2 justify-center">
                                       <a href="javascript:void(0)" data-id="${element.id}"
                                         class="bg-green-600 flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-green-800 relative overflow dy-nested-pop-btn">
                                         <span class="material-symbols-outlined !text-sm text-white">visibility</span>
                                       </a>
                                     </div>
                                   `;

                            return [checkboxCol, snoCol, nameCol, actionCol];
                        });

                        dt.rows.add(rows).draw();
                        if (window.articleSelectMgr) {
                            window.articleSelectMgr.refresh();
                        }

                        // ****** we init the table so it doesnot get all value ***

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

                        // ***** ends here *****


                        // ✅ open popup after data is ready
                        openPopup();

                        // ✅ if table is inside popup/modal, adjust columns
                        setTimeout(() => {
                            dt.columns.adjust().responsive?.recalc?.();
                        }, 80);


                    } catch (error) {
                        if (loader) loader.classList.add("hidden");
                        console.error(error);
                        alert("something went wrong");
                    }
                });
            }

            if (addSearchArticle) {
                addSearchArticle.addEventListener("click", async (e) => {
                    e.preventDefault();
                    // select searchBox Inputs,radio,select value
                    // initialize the variable
                    localStorage.removeItem('selectArticles');
                    let _continue_ = false;
                    let select_search_item;
                    let appendDiv =
                        document.getElementById("append_items_here");
                    let searchInp =
                        document.getElementById("search-article-inp"); // ** inp here
                    let search_items_opt = document.querySelectorAll(
                        ".article-search-opts"
                    ); /* articles search opts where to search in title or input */
                    let searchType = document.getElementById('searching-type'); /* 1 means similar | 2 means exact */
                    // checking radio values if checked or not?
                    if (searchInp.value == "") {
                        let p = document.createElement("p");
                        p.textContent = "Enter search value for filter content";
                        p.className = "text-red-400 text-sm";
                        appendDiv.append(p);
                        searchInp.focus();
                        searchInp.addEventListener("input", (e) =>
                            appendDiv.querySelector("p")
                                ? appendDiv.querySelector("p").remove()
                                : ""
                        );
                        return;
                    }

                    search_items_opt.forEach((itm, indx) => {
                        if (itm.checked) {
                            _continue_ = true;
                            select_search_item = itm.value;
                        }
                    });

                    if (!_continue_) {
                        alert(
                            "select the search item 'In Title' or 'In Articles'"
                        );
                        return;
                    }
                    /* collecting the value of search api body data */

                    let searchLoader = e.target.querySelector('.search-articles-loader');

                    try {

                        let search__data = {
                            search: searchInp.value,
                            searchItem: select_search_item,
                            searchType: searchType.value
                        }
                        // if user selected the category
                        if (articleNiche.value != '') {
                            search__data.article_category_id = articleNiche.value;
                        }

                        searchLoader.classList.remove('hidden');


                        let url = `http://127.0.0.1:8000/api/admin/article/search`;
                        let api = await fetch(url, {
                            method: 'post',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(search__data)
                        });
                        let res = await api.json();
                        console.log(res);
                        searchLoader.classList.add('hidden');
                        if (!res.status) {
                            alert(res.message);
                            return;
                        }

                        let fetchArticles = Array.isArray(res.data?.articles) ? res.data.articles : []; // this is articles

                        if (fetchArticles.length < 1 || fetchArticles.length < postCount) {
                            alert("the articles are not enough or equal to post count");
                            return;
                        }
                        // ✅ Clear and add rows using DataTables API (pagination works)
                        const dt = window.myDT;
                        window.articleSelectMgr.clear();
                        dt.clear();

                        const rows = fetchArticles.map((element, index) => {
                            const checkboxCol = `
                                 <input type="checkbox" name="bulk_category_select[]"
                                   id="sel-check-box-${index + 1}"
                                   class="articles"
                                   value="${element.id}"> 
                               `;

                            const snoCol = index + 1;

                            const nameCol = `
                                   <label for="sel-check-box-${index + 1}" class="cursor-pointer">
                                     ${element.name ?? ""}
                                   </label>
                                 `;

                            const actionCol = `
                                     <div class="flex flex-wrap gap-2 justify-center">
                                       <a href="javascript:void(0)" data-id="${element.id}"
                                         class="bg-green-600 flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-green-800 relative overflow dy-nested-pop-btn">
                                         <span class="material-symbols-outlined !text-sm text-white">visibility</span>
                                       </a>
                                     </div>
                                   `;

                            return [checkboxCol, snoCol, nameCol, actionCol];
                        });

                        dt.rows.add(rows).draw();
                        if (window.articleSelectMgr) {
                            window.articleSelectMgr.refresh();
                        }
                        // setTimeout(() => {
                        //     if (document.querySelector('.articles')) {
                        //         // 3️⃣ reset select-all
                        //         const selectAll = document.querySelector('#selectAll');
                        //         if (selectAll) selectAll.checked = false;
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
                        //     }
                        // }, 0);


                        // ✅ open popup after data is ready
                        openPopup();

                        // ✅ if table is inside popup/modal, adjust columns
                        setTimeout(() => {
                            dt.columns.adjust().responsive?.recalc?.();
                        }, 80);


                    } catch (error) {
                        throw new Error("something went wrong", error);

                    }



                });
            }
            // popup btn for articles selecting and making the ids string
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

                if (postCount != selectArticles.length) {
                    alert(`you must select articles equal to ${postCount} quantity`);
                    return;
                }
                // store values in a hidden input
                if (selectArticleValHolder) {
                    selectArticleValHolder.value = selectArticles;
                    document.getElementById("dy-close-btn").click(); // we are transfering click so it closes the popup
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

            try {
                showDomainLoader.classList.remove('hidden');
                setTimeout(() => showDomainLoader.classList.remove('opacity-0'), 200);

                const url = `/api/admin/domains/${domainId}`; // ✅ no localhost
                const api = await fetch(url, { headers: { Accept: "application/json" } });
                const res = await api.json();

                if (!res.status || !Array.isArray(res.data)) {
                    alert(res.message || "Failed to fetch domains");
                    return;
                }
                console.log(res.data.length);
                if (res.data.length < postCount) {
                    alert("Domains quantity should be greater or equal to post quantity in order to run campaign");
                    return;
                }

                // ✅ Build rows
                const rows = buildDomainRows(res.data);

                // ✅ Clear & append into DataTable
                const randomDt = window.randomDT;
                randomDt.clear();
                randomDt.rows.add(rows).draw(false);

                // ✅ refresh checkbox manager if you have one
                if (window.domainSelectMgr) {
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

        fetchDomainSet.addEventListener('click', async (e) => {
            e.preventDefault();

            // ✅ use currentTarget so it works even if you click icon/span inside button
            const btn = e.currentTarget;
            const loader = btn.querySelector('.loader');

            const setId = parseInt((domainSetId?.value || '').trim(), 10);
            if (!Number.isInteger(setId) || setId <= 0) return;

            if (!window.domainSetDT) {
                alert('domainSetDT not initialized');
                return;
            }

            try {
                loader?.classList.remove('hidden');

                const api = await fetch(`/api/admin/domain/set/fetch/${setId}`, {
                    method: 'GET',
                    headers: { Accept: 'application/json' }
                });

                const response = await api.json().catch(() => null);

                if (!response || response.status !== true || !Array.isArray(response.data)) {
                    alert(response?.message || 'Failed to fetch domains');
                    return;
                }

                // ✅ build rows for DataTable
                const rows = response.data.map((d, index) => {
                    const cbId = `sel-set-domain-check-box-${index + 1}`;
                    const checkboxCol = `
                                         <input type="checkbox"
                                           name="bulk_domain_select[]"
                                           id="${cbId}"
                                           class="setdomains"
                                           value="${d.id}">
                                       `;

                    const snoCol = index + 1;

                    const domainCol = `
                                    <label for="${cbId}" class="cursor-pointer w-full">
                                      ${d.name ?? ''}
                                    </label>
                                  `;

                    return [
                        checkboxCol,
                        snoCol,
                        domainCol,
                        d.da ?? '-',
                        d.tf ?? '-',
                        d.dr ?? '-',
                        d.ss ?? '-'
                    ];
                });

                // ✅ put into datatable
                const dt = window.domainSetDT;
                dt.clear();
                dt.rows.add(rows).draw(false);

                // ✅ if you have selection manager for setdomains, refresh it
                if (window.domainSetSelectMgr) {
                    window.domainSetSelectMgr.refresh();
                }

            } catch (err) {
                console.error(err);
                alert('Something went wrong');
            } finally {
                loader?.classList.add('hidden');
            }
        });

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
