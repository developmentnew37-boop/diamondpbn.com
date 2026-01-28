// ✅ FULL CODE (with patches) — NO LINES REMOVED, ONLY ADDED/UPDATED INLINE

import { initDynamicRadioGroup, initPopup, nestedPop } from "./helper/popup";

window.addEventListener("DOMContentLoaded", () => {
    // tabs things which we will handle
    // --- Step manager (drop-in) ---
    let sidebarCount = 0; // default or minimum
    let domainCategory;
    // sumbit button //
    let form = document.getElementById('sidebar-campaign');
    // **** //
    (function () {
        // Tab buttons and sections
        const tabBtnArr = Array.from(document.getElementsByClassName("tab-switcher") || []);
        const campaignsSection = Array.from(document.querySelectorAll(".campaigns-section") || []);
        let currentStep = 0;

        // ❌ PATCH: REMOVE shadowed sidebarCount (this was causing sidebarCount to stay 10)
        // let sidebarCount = 10;

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
            // for specific run thing
            let __step03Ran = false;

            if (index === 2 && typeof window.__onEnterStep03 === "function" && !__step03Ran) {
                __step03Ran = true;
                window.__onEnterStep03();
            }
        }
        const DEV_MODE = false; // <- change to false later

        // prevent clicking locked tabs
        function tabClickHandlerBound(e) {
            const idx = tabBtnArr.indexOf(this);
            const locked = this.dataset.locked === "true";
            if (DEV_MODE) {
                // unlock all tabs up to clicked one
                for (let k = 0; k <= idx; k++) setTabEnabled(k, true);
                showSection(idx);
                currentStep = idx;
                return;
            }
            if (locked) {
                e.preventDefault();
                alert("Complete previous step first.");
                return false;
            }
            showSection(idx);
            currentStep = idx;
        }

        // Step validators
        function validateStep01() {
            const campaignId = document.getElementById("campaign-no");
            const selectDomain = document.getElementById("campaign-domain");
            const sideBarQuantity = document.getElementById("sidebar-quantity");

            if (!campaignId || !selectDomain || !sideBarQuantity)
                return { ok: false, msg: "Missing fields on step 1" };

            if (campaignId.value.trim() === "" || selectDomain.value.trim() === "" || sideBarQuantity.value.trim() === "")
                return { ok: false, msg: "All fields should be filled" };

            const pq = parseInt(sideBarQuantity.value);
            if (isNaN(pq) || pq <= 0)
                return { ok: false, msg: "Enter a valid post quantity" };

            // ✅ PATCH: This now updates the ONE global sidebarCount (no shadowing)
            sidebarCount = pq;
            domainCategory = selectDomain.value;
            return { ok: true };
        }

        function validateStep02() {
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

            // ✅ DEV MODE: no validation
            if (DEV_MODE) {
                if (currentStep + 1 < tabBtnArr.length) {
                    // unlock all
                    tabBtnArr.forEach((_, i) => setTabEnabled(i, true));
                    showSection(currentStep + 1);
                    currentStep++;
                }
                return;
            }


            const validators = [validateStep01, validateStep02];
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
        ];

        continueMap.forEach((btn, i) => {
            if (!btn) return;
            btn.addEventListener("click", function (ev) {
                ev.preventDefault();
                ev.stopPropagation();

                // ✅ PATCH: ensure sidebarCount updated at click time too
                if (i === 0) {
                    const pqElem = document.getElementById("sidebar-quantity");
                    if (pqElem && pqElem.value.trim() !== "")
                        sidebarCount = parseInt(pqElem.value) || sidebarCount;
                }

                goToNextStep();
            });
        });

        // expose globally
        window.__campaign_step_manager = {
            goToStep: function (idx) {
                if (typeof idx !== "number") return;
                if (idx < 0 || idx >= tabBtnArr.length) return;

                // ***********************************
                // ✅ DEV MODE: jump freely
                if (DEV_MODE) {
                    tabBtnArr.forEach((_, i) => setTabEnabled(i, true));
                    showSection(idx);
                    currentStep = idx;
                    return { ok: true, dev: true };
                }

                // ***********************************
                for (let s = 0; s < idx; s++) {
                    const validators = [validateStep01, validateStep02];
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
            getsidebarCount: () => sidebarCount
        };

        initTabsLocking();
    })();

    // tab ends here
    let numInp = document.getElementsByClassName("num-inp");
    allowOnlyNumbers(numInp);

    //create campiagns
    let step__01 = document.getElementById("continue_campaign_step_01");
    let step__02 = document.getElementById("continue_campaign_step_02");
    let step__03 = document.getElementById("continue_campaign_step_03");

    if (step__02) {
        // this is keyword pop showing
        let addKeywordsUrl = document.getElementById("add-keywords-url-btn");
        const dyKeywordParent = document.querySelector(".dy-key-parent-div");
        const dyKeywordOverlay = document.querySelector(".dy-key-set-overlay");
        const dyKeywordCloseBtn = document.getElementById("dy-key-close-btn");
        const selectiveKeywordBox = document.getElementById("dy-key-article-box");

        addKeywordsUrl.addEventListener("click", (e) => {
            e.stopImmediatePropagation();
            initPopup(dyKeywordParent, dyKeywordOverlay, selectiveKeywordBox, dyKeywordCloseBtn, 600);
        });

        // selecting hidden input which will hold the keyword-json data
        let keywordsDataHolder = document.getElementById("keywordsDataHolder");

        function checkNormal(target) {
            if (target.value == "normal") {
                document.querySelector(".add-more-window").classList.remove("hidden");
            }
        }

        function tabStyleSwitcher(btns, section, func = false) {
            Array.from(btns).forEach((i) => {
                i.addEventListener("change", (e) => {
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    Array.from(btns).forEach((k) => {
                        k.classList.remove("border-[var(--primary-color)]", "bg-[var(--primary-color)]", "text-white");
                        k.classList.add("border-gray-300", "bg-gray-50");
                    });

                    // hidding all tab
                    Array.from(section).forEach((item) => item.classList.add("hidden"));

                    document.getElementById(`${e.target.getAttribute("data-id")}`).classList.remove("hidden");

                    if (func instanceof Function) func(e.target);

                    i.classList.remove("border-gray-300", "bg-gray-50");
                    i.classList.add("border-[var(--primary-color)]", "bg-[var(--primary-color)]", "text-white");
                });
            });
        }

        // ===============================
        let keywordTabBtn = document.querySelectorAll(".keyword-tab-btn");
        let keywordTab = document.getElementsByClassName("keyword-tab-sec");
        tabStyleSwitcher(keywordTabBtn, keywordTab, checkNormal);

        // === Global references ===
        let AddMoreBtn = document.getElementById("add-more-keyword-URL");
        let keywordUrlCon = document.getElementById("keyword-url-container");
        let totalBoxCount = document.querySelector(".total-box-count");

        // ✅ PATCH: helper to always fetch latest sidebarCount (no frozen 10)
        function getSidebarCount() {
            const sc = window.__campaign_step_manager?.getsidebarCount?.();
            if (Number.isFinite(sc) && sc > 0) return sc;
            const local = parseInt(sidebarCount);
            return Number.isFinite(local) && local > 0 ? local : 10;
        }

        // === Utility: Update borders dynamically ===
        function updateBorders() {
            const boxes = document.querySelectorAll(".keyword-url-box");
            boxes.forEach((box, i) => {
                box.classList.remove("border-b", "border-b-[var(--primary-color)]");
                if (i < boxes.length - 1) {
                    box.classList.add("border-b", "border-b-[var(--primary-color)]");
                }
            });
        }

        // === Utility: Update count labels ===
        function updateBoxCount() {
            const boxes = [...document.querySelectorAll(".keyword-url-box")];
            boxes.forEach((box, i) => {
                const boxCounter = box.querySelector(".box-count");
                if (boxCounter) boxCounter.textContent = String(i + 1).padStart(2, "0");
            });
            if (totalBoxCount) totalBoxCount.textContent = boxes.length;
        }

        // ✅ PATCH: do NOT pass sidebarCount into listeners (it freezes at 10). Read fresh count inside.
        function checkExceedsidebarCount(inp) {
            Array.from(inp).forEach((i, currentIndex) => {
                i.addEventListener("input", function (e) {
                    e.stopImmediatePropagation();

                    const count = getSidebarCount(); // ✅ always latest
                    let usedPost = [...inp].reduce((sum, num, idx) => {
                        if (idx !== currentIndex) return sum + (parseInt(num.value) || 0);
                        return sum;
                    }, 0);

                    let val = parseInt(this.value || 0);
                    let maxAllowed = count - usedPost;

                    if (val > maxAllowed) {
                        alert("URL quantity cannot exceed total sidebar count: " + maxAllowed);
                        this.value = maxAllowed;
                        val = maxAllowed;
                    }
                    updateKeywordQuantity(this, val);
                });
            });
        }

        // utility function to distribute number based on input value
        function keywordNumDistribute(keyArea) {
            Array.from(keyArea).forEach((keyAr) => {
                keyAr.addEventListener("input", (e) => {
                    const text = e.target.value;
                    const lines = text.split("\n").filter((line) => line.trim() !== "");
                    const keywordCount = lines.length;
                    if (keywordCount === 0) return;

                    const inputNum =
                        parseInt(
                            e.target.closest(".keyword-url-box")
                                .querySelector(".client-url-quantity").value
                        ) || 0;

                    let keyArr = [];

                    if (inputNum > keywordCount) {
                        let base = Math.floor(inputNum / keywordCount);
                        let remainder = inputNum % keywordCount;

                        for (let i = 0; i < keywordCount; i++) {
                            keyArr[i] = base + (i < remainder ? 1 : 0);
                        }
                    } else {
                        for (let i = 0; i < keywordCount; i++) {
                            keyArr[i] = i < inputNum ? 1 : 0;
                        }
                    }

                    const boxContent = e.target
                        .closest(".keyword-url-box")
                        .querySelector(".keywords-quantity-area");
                    if (boxContent) boxContent.value = keyArr.join("\n");
                });
            });
        }

        // === Utility: Reflect quantity into keyword area ===
        function updateKeywordQuantity(input, val) {
            const parentBox = input.closest(".keyword-url-box");
            if (!parentBox) return;

            let keywordsArea = parentBox.querySelector(".keywords-area");
            let kQuantityArea = parentBox.querySelector(".keywords-quantity-area");

            if (keywordsArea) {
                let keywordCount = keywordsArea.value.split("\n").filter((line) => line.trim() !== "").length;
                if (keywordCount > 0) {
                    let inputVal = parseInt(input.value || 0);
                    let keyQuantityArr = [];

                    if (inputVal > keywordCount) {
                        let base = Math.floor(inputVal / keywordCount);
                        let remainder = inputVal % keywordCount;

                        for (let i = 0; i < keywordCount; i++) {
                            keyQuantityArr[i] = base + (i < remainder ? 1 : 0);
                        }
                    } else {
                        for (let i = 0; i < keywordCount; i++) {
                            keyQuantityArr[i] = i < inputVal ? 1 : 0;
                        }
                    }
                    kQuantityArea.value = keyQuantityArr.join("\n");
                    return;
                }
            }

            const keywordQuantityArea = parentBox.querySelector(".keywords-quantity-area");
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
            }
        });

        // === Add new keyword box ===
        AddMoreBtn.addEventListener("click", (e) => {
            e.preventDefault();

            // ✅ PATCH: always use latest sidebar count
            const currentSidebarCount = getSidebarCount();

            let keywordBoxCount = document.getElementsByClassName("keyword-url-box").length;
            if (keywordBoxCount >= currentSidebarCount) {
                alert("you cannot add boxes more than sidebar count");
                return;
            }

            let div = document.createElement("div");
            div.className = "flex w-full bg-orange-100 keyword-url-box relative";
            div.innerHTML = `
                <div class="w-3/5 flex flex-col gap-2 !p-4 !pt-[45px]">
                    <div class="w-full flex items-center">
                        <label class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-1/5">Client Url</label>
                        <div class="w-4/5 flex items-center justify-between">
                            <input type="text" placeholder="Enter Url" class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-[78%] client-url">
                            <input type="text" value="0" class="bg-gray-50 !p-2 text-sm outline-none text-center border border-gray-300 w-1/5 client-url-quantity num-inp">
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

            keywordUrlCon.appendChild(div);
            updateBoxCount();
            updateBorders();

            const newInput = document.querySelectorAll(".client-url-quantity");
            allowOnlyNumbers(newInput);

            // ✅ PATCH: now uses dynamic sidebar count
            checkExceedsidebarCount(newInput);

            let keywordsArea = document.getElementsByClassName("keywords-area");
            keywordNumDistribute(keywordsArea);
        });

        // === Initialize for existing boxes ===
        updateBoxCount();
        updateBorders();

        let clientUrlQuantity = document.getElementsByClassName("client-url-quantity");

        // ✅ PATCH: now uses dynamic sidebar count
        checkExceedsidebarCount(clientUrlQuantity);

        let keywordsArea = document.getElementsByClassName("keywords-area");
        keywordNumDistribute(keywordsArea);

        // xxxxxxxxxxxxxxxxxxxxxxx bulk input data  xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
        let bulkInput = document.getElementsByClassName("bulk-input");
        Array.from(bulkInput).forEach((item) => {
            item.addEventListener("input", (e) => {
                let splitVal = e.target.value.split("\n").filter((line) => line.trim() !== "");
                let parent = e.target.closest("div");
                parent.querySelector(".bulk-count").textContent = `(${splitVal.length})`;
            });
        });

        //===============================================================================================================

        let addKeywordBtn = document.getElementById("add-keywords-links");
        addKeywordBtn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopImmediatePropagation();

            // ✅ PATCH: always use latest sidebar count before validation
            const currentSidebarCount = getSidebarCount();
            sidebarCount = currentSidebarCount;

            const method = document.querySelector(".keyword-method-inp:checked")?.value || "";
            const noFollow = document.getElementById("no_follow")?.checked
                ? document.getElementById("no_follow").value
                : "";

            let keyWordBox = document.querySelectorAll(".keyword-url-box");
            let keywords_url_data = [];

            if (method == "normal") {
                if (keyWordBox.length === 0) {
                    alert("At least 1 box data is required");
                    return;
                }
                let emptyUrl = false;
                let emptyKeywordArea = false;

                Array.from(keywordUrlCon.querySelectorAll(".client-url")).forEach((i) => {
                    if (i.value == "") emptyUrl = true;
                });
                Array.from(keywordUrlCon.querySelectorAll(".keywords-area")).forEach((i) => {
                    if (i.value == "") emptyKeywordArea = true;
                });

                if (emptyUrl) {
                    alert("client url field should not be left empty");
                    return;
                }
                if (emptyKeywordArea) {
                    alert("client keyword area should not be left empty");
                    return;
                }

                let clientUrlQuantityInp = document.querySelectorAll(".client-url-quantity");
                let totalLinks = [...clientUrlQuantityInp].reduce((sum, num) => sum + parseInt(num.value || 0), 0);

                // ✅ PATCH: compare with latest sidebarCount (not frozen 10)
                if (totalLinks != currentSidebarCount) {
                    alert("client url quantity should match the sidebar count");
                    return;
                }

                let boxData = [];
                let right = true;

                keyWordBox.forEach((box) => {
                    var urlQuantity = parseInt(box.querySelector(".client-url-quantity").value || 0);
                    var url = box.querySelector(".client-url").value;

                    var keywords = box.querySelector(".keywords-area").value.split("\n").filter((line) => line.trim() !== "");
                    var keywordsNum = box.querySelector(".keywords-quantity-area").value.split("\n").filter((line) => line.trim() !== "");

                    var keywordQTotal = [...keywordsNum].reduce((sum, num) => sum + (parseInt(num) || 0), 0);

                    if (keywordQTotal != urlQuantity) {
                        right = false;
                        alert("keywords quantity should be equal to url quanity");
                        box.scrollIntoView({ behavior: "smooth", block: "center" });
                        box.querySelector(".keywords-quantity-area").focus();
                        return;
                    }

                    let keywordIndex = 0;
                    let repeatCount = parseInt(keywordsNum[keywordIndex] || 0);

                    for (let i = 0; i < urlQuantity; i++) {
                        boxData.push({
                            url: url,
                            keyword: keywords[keywordIndex],
                            nofollow: noFollow,
                        });

                        repeatCount--;
                        if (repeatCount === 0) {
                            keywordIndex++;
                            repeatCount = parseInt(keywordsNum[keywordIndex] || 0);
                        }
                    }
                });

                boxData = !right ? "" : boxData;
                keywords_url_data = boxData;
            }

            if (method == "bulk") {
                let bulkData = [];
                let bulkUrlsVal = document.getElementById("bulk-client-urls").value.split("\n").filter((line) => line.trim() !== "");
                let bulkKeywordsVal = document.getElementById("bulk-client-keywords").value.split("\n").filter((line) => line.trim() !== "");

                if (bulkUrlsVal.length < 1 || bulkKeywordsVal.length < 1) {
                    alert("urls | keywords quantity should be greater than 0");
                    return;
                }
                if (bulkUrlsVal.length != bulkKeywordsVal.length) {
                    alert("urls & keywords quantity should match");
                    return;
                }

                // ✅ PATCH: compare with latest sidebar count
                if (bulkUrlsVal.length != currentSidebarCount || bulkKeywordsVal.length != currentSidebarCount) {
                    alert("urls & keywords quantity should match to the post quantity also");
                    return;
                }

                for (let x = 0; x < bulkUrlsVal.length; x++) {
                    bulkData.push({
                        url: bulkUrlsVal[x],
                        keyword: bulkKeywordsVal[x],
                        nofollow: noFollow,
                    });
                }

                keywords_url_data = bulkData;
            }

            let keywordsDataTable = document.getElementById("keywords-data-table");
            keywordsDataHolder.value = JSON.stringify(keywords_url_data);
            keywordsDataTable.querySelector("tbody").innerHTML = "";

            keywords_url_data.forEach((item) => {
                let tr = document.createElement("tr");
                tr.className = "hover:bg-gray-50";
                tr.innerHTML = `
                    <td class="border border-gray-200 font-sans !p-2">${item.url}</td>
                    <td class="border border-gray-200 font-sans !p-2">${item.keyword}</td>
                `;
                keywordsDataTable.querySelector("tbody").append(tr);
            });

            dyKeywordCloseBtn.click();
        });
        // ==========================================================================================================================
        // ** Domain select portion starts here

        let domainTabBtns = document.getElementsByClassName("domain-tab-btn");
        let domainSections = document.getElementsByClassName("domains-sections");
        tabStyleSwitcher(domainTabBtns, domainSections);

    }

    if (step__03) {




        window.__onEnterStep03 = async function () {

            const selected = document.querySelector('input[name="sel_domains"]:checked');
            if (!selected) return;

            const showDomainLoader = document.querySelector('.domain-loader-pop');

            try {
                showDomainLoader.classList.remove('hidden');
                setTimeout(() => showDomainLoader.classList.remove('opacity-0'), 200);

                const url = `/api/admin/domains/${domainCategory}`; // ✅ no localhost
                const api = await fetch(url, { headers: { Accept: "application/json" } });
                const res = await api.json();

                if (!res.status || !Array.isArray(res.data)) {
                    alert(res.message || "Failed to fetch domains");
                    return;
                }
                if (res.data.length < sidebarCount) {
                    alert("Domains quantity should be greater or equal to sidebar quantity in order to run campaign");
                    return;
                }
                // ✅ Build rows
                const rows = buildDomainRows(res.data);

                // ✅ Clear & append into DataTable
                const sidebarRandomDt = window.sidebarRandomDt;
                sidebarRandomDt.clear();
                sidebarRandomDt.rows.add(rows).draw(false);

                // ✅ refresh checkbox manager if you have one
                if (window.sideBarRandomDomainSelMgr) {
                    window.sideBarRandomDomainSelMgr.refresh();
                }

            } catch (e) {
                console.error(e);
                alert("Something went wrong while loading domains");
            } finally {
                showDomainLoader.classList.add('opacity-0');
                setTimeout(() => showDomainLoader.classList.add('hidden'), 300);
            }
        };

        // domain-set


        // ********* fetching domains base on the domains set ********* //

        let fetchDomainSet = document.getElementById('fetch-domains-set');
        let domainSetId = document.getElementById('domain-set'); // there are domain-sets also available in code

        fetchDomainSet.addEventListener('click', async (e) => {
            e.preventDefault();

            // ✅ use currentTarget so it works even if you click icon/span inside button
            const btn = e.currentTarget;
            const loader = btn.querySelector('.loader');

            const setId = parseInt((domainSetId?.value || '').trim(), 10);
            if (!Number.isInteger(setId) || setId <= 0) return;

            if (!window.sidebarDomainSetDt) {
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
                const dt = window.sidebarDomainSetDt;
                dt.clear();
                dt.rows.add(rows).draw(false);

                // ✅ if you have selection manager for setdomains, refresh it
                if (window.sideBarDomainSetMgr) {
                    window.sideBarDomainSetMgr.refresh();
                }
                // we left on domain set

            } catch (err) {
                console.error(err);
                alert('Something went wrong');
            } finally {
                loader?.classList.add('hidden');
            }
        });

        // ********* manual domains script here ********* //

        let manualDomainsArea = document.getElementById("manual-domains-area");
        let manualDomainCount = document.getElementById("manualDomainsCount");

        if (manualDomainsArea) {
            manualDomainsArea.addEventListener("input", (e) => {
                e.stopImmediatePropagation();
                e.preventDefault();

                let count = e.target.value.split("\n").filter((line) => line.trim() !== "").length;
                manualDomainCount.textContent = `${count}`;
            });
        }

        // ***** manual domain script here ******//

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            e.preventDefault();

            const selectedRadio = document.querySelector('input[name="sel_domains"]:checked'); // 
            const campaignDomainHolder = document.getElementById('campaigns_domains_holder');

            if (!selectedRadio || !campaignDomainHolder) {
                alert('Required form fields are missing.');
                return;
            }

            const selectedDomainMethod = selectedRadio.value;
            console.log("selected method", selectedDomainMethod);
            /* -----------------------------
               RANDOM / SET DOMAINS
            ------------------------------*/
            if (selectedDomainMethod === '0') {
                const domains = localStorage.getItem('selectSidebarDomains');
                console.log(domains)
                if (!domains) {
                    alert('No domains found in local storage.');
                    return;
                }
                let domainsLength = JSON.parse(domains).length;
                if (domainsLength != sidebarCount) {
                    alert('domains should be selected equal to sidebar count');
                    return;
                }
                campaignDomainHolder.value = domains;


            } else if (selectedDomainMethod === '1') {
                const domains = localStorage.getItem('selectSidebarSetDomains');
                console.log(domains)
                if (!domains) {
                    alert('No domain set found in local storage.');
                    return;
                }
                let domainsLength = JSON.parse(domains).length;
                if (domainsLength != sidebarCount) {
                    alert('domains should be selected equal to sidebar count');
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
                if (domains.length !== sidebarCount) {
                    alert(`Manual domains must be equal to sidebar count (${sidebarCount})`);
                    return;
                }

                const icon = step__03?.querySelector('.check-icon');
                const loader = step__03?.querySelector('.loader');

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
                    console.log(res)

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

            // console.log('// ********** All camppaign data here ************** //');

            // console.log("campaigns_id", document.getElementById('campaign-no').value);
            // console.log("campaigns_id", document.getElementById('campaign-domain').value);
            // console.log("sidebarcount", sidebarCount);
            // console.log("keywords data holder", document.getElementById('keywordsDataHolder').value);
            // console.log("campaigns value", campaignDomainHolder.value);
            // console.log('// ************************ //');
            // submit the form programmatically
            e.target.submit();
        })

    }
});
