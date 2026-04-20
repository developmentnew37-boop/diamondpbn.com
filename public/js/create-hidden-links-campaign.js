// ✅ FULL CODE (with patches) — NO LINES REMOVED, ONLY ADDED/UPDATED INLINE


import { initDynamicRadioGroup, initPopup, nestedPop } from "./helper/popup.js";
import { initSelectManager } from "./helper/selectBox.js";
import { allowOnlyNumbers } from "./helper/utility.js";

window.addEventListener("DOMContentLoaded", () => {


    // 🔴 RESET selection state on full page reload

    localStorage.removeItem("selectHDomains");
    localStorage.removeItem("lastHiddenSetDomainSet");
    localStorage.removeItem("selectHSetDomains");

    // tabs things which we will handle
    // --- Step manager (drop-in) ---
    let sidebarCount = 10; // default or minimum
    let domainCategory;
    // sumbit button //
    let form = document.getElementById('sidebar-campaign');

    function autoSelectByQuantity({ checkboxSelector, localStorageKey, qty, manager = null }) {
        const limit = Math.max(0, parseInt(qty || 0, 10));
        if (limit <= 0) return;
        const existing = (() => {
            try {
                const raw = JSON.parse(localStorage.getItem(localStorageKey) || "[]");
                return Array.isArray(raw) ? [...new Set(raw.map((v) => String(v)).filter(Boolean))] : [];
            } catch (_) { return []; }
        })();
        const pageIds = Array.from(document.querySelectorAll(checkboxSelector)).map((cb) => String(cb.value)).filter(Boolean);
        const selected = [...existing];
        if (selected.length < limit) {
            for (const id of pageIds) {
                if (selected.length >= limit) break;
                if (!selected.includes(id)) selected.push(id);
            }
        }
        localStorage.setItem(localStorageKey, JSON.stringify(selected.slice(0, limit)));
        if (manager && typeof manager.refresh === "function") manager.refresh();
    }
    function getStoredSelectionCount(localStorageKey) {
        try {
            const raw = JSON.parse(localStorage.getItem(localStorageKey) || "[]");
            return Array.isArray(raw) ? raw.length : 0;
        } catch (_) { return 0; }
    }
    function sleep(ms) { return new Promise((resolve) => setTimeout(resolve, ms)); }
    function getCurrentPageSignature(containerSelector, checkboxSelector) {
        const container = document.querySelector(containerSelector);
        const active = container?.querySelector(".page-active")?.textContent?.trim() || "";
        const firstId = document.querySelector(checkboxSelector)?.value || "";
        return `${active}::${firstId}`;
    }
    async function waitForPageMutation(containerSelector, checkboxSelector, previousSignature, timeoutMs = 5000) {
        const startedAt = Date.now();
        while ((Date.now() - startedAt) < timeoutMs) {
            const container = document.querySelector(containerSelector);
            const active = container?.querySelector(".page-active")?.textContent?.trim() || "";
            const firstId = document.querySelector(checkboxSelector)?.value || "";
            if (`${active}::${firstId}` !== previousSignature) return true;
            await sleep(120);
        }
        return false;
    }
    function getNextPaginationButton(containerSelector) {
        const container = document.querySelector(containerSelector);
        if (!container) return null;
        const buttons = Array.from(container.querySelectorAll("button.page-btn, button"));
        const activeIndex = buttons.findIndex((btn) => btn.classList.contains("page-active"));
        if (activeIndex >= 0) {
            for (let i = activeIndex + 1; i < buttons.length; i++) if (!buttons[i].disabled) return buttons[i];
        }
        return buttons.find((btn) => !btn.disabled && ((btn.textContent || "").trim().toLowerCase().includes("next") || (btn.textContent || "").trim() === "»")) || null;
    }
    async function autoSelectAcrossPages({ localStorageKey, checkboxSelector, containerSelector, progressSelector, manager }) {
        const progressEl = document.querySelector(progressSelector);
        const updateProgress = () => {
            const selected = getStoredSelectionCount(localStorageKey);
            if (progressEl) progressEl.textContent = `${selected}/${sidebarCount} selected`;
            return selected;
        };
        let selectedCount = updateProgress();
        let guard = 0;
        while (selectedCount < sidebarCount && guard < 300) {
            guard += 1;
            autoSelectByQuantity({ checkboxSelector, localStorageKey, qty: sidebarCount, manager });
            selectedCount = updateProgress();
            if (selectedCount >= sidebarCount) break;
            const before = getCurrentPageSignature(containerSelector, checkboxSelector);
            const nextBtn = getNextPaginationButton(containerSelector);
            if (!nextBtn) break;
            nextBtn.click();
            await waitForPageMutation(containerSelector, checkboxSelector, before, 6000);
            await sleep(120);
            selectedCount = updateProgress();
        }
        return { done: selectedCount >= sidebarCount, selectedCount };
    }

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
            if (meta.total < sidebarCount) {
                alert("Domains quantity must be greater or equal to sidebar Links quantity");
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
                        class="sidebar_domains"
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
            if (window.HRandomDomainSelMgr) {
                window.HRandomDomainSelMgr.refresh();
            }
            if (autoSelect) {
                autoSelectByQuantity({
                    checkboxSelector: ".sidebar_domains",
                    localStorageKey: "selectHDomains",
                    qty: sidebarCount,
                    manager: window.HRandomDomainSelMgr
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
                    localStorageKey: "selectHSetDomains",
                    qty: sidebarCount,
                    manager: window.HDomainSetMgr
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
            const sideBarQuantity = document.getElementById("hidden-quantity");

            if (!campaignId || !selectDomain || !sideBarQuantity)
                return { ok: false, msg: "Missing fields on step 1" };

            if (campaignId.value.trim() === "" || selectDomain.value.trim() === "" || sideBarQuantity.value.trim() === "")
                return { ok: false, msg: "All fields should be filled" };

            const pq = parseInt(sideBarQuantity.value);
            if (isNaN(pq) || pq <= 0)
                return { ok: false, msg: "Enter a valid post quantity" };

            // ✅ PATCH: This now updates the ONE global sidebarCount (no shadowing)
            let postQtyshower = document.querySelector('.dy-post-count');
            postQtyshower.textContent = `(${pq})`;
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
                    const pqElem = document.getElementById("hidden-quantity");
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
            updateKeywordProgress();
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
            let node = document.getElementById("overall-hidden-keyword-progress");
            if (node) return node;
            node = document.createElement("p");
            node.id = "overall-hidden-keyword-progress";
            node.className = "!px-3 !py-2 rounded border-2 border-blue-300 bg-blue-50 text-blue-800 font-semibold text-sm shadow-sm";
            host.appendChild(node);
            return node;
        }
        function updateKeywordProgress() {
            const count = getSidebarCount();
            const boxes = [...document.querySelectorAll(".keyword-url-box")];
            let usedQty = 0;
            boxes.forEach((box) => {
                const target = parseInt(box.querySelector(".client-url-quantity")?.value || "0", 10) || 0;
                const assigned = parseQtyLines(box.querySelector(".keywords-quantity-area")?.value || "");
                usedQty += Math.max(target, assigned ? target : 0);
            });
            const node = ensureOverallProgressNode();
            if (node) node.textContent = `Quantity used ${usedQty}/${count} | Remaining ${Math.max(count - usedQty, 0)}`;
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
                    updateKeywordProgress();
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
                    updateKeywordProgress();
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
                    updateKeywordProgress();
                    return;
                }
            }

            const keywordQuantityArea = parentBox.querySelector(".keywords-quantity-area");
            if (keywordQuantityArea) keywordQuantityArea.value = val;
            updateKeywordProgress();
        }

        // === Delete handler ===
        keywordUrlCon.addEventListener("click", (e) => {
            if (e.target.classList.contains("remove-keyword-box")) {
                e.preventDefault();
                e.stopPropagation();
                e.target.closest(".keyword-url-box").remove();
                updateBoxCount();
                updateBorders();
                updateKeywordProgress();
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
            div.className = "flex w-full bg-orange-100 keyword-url-box relative keyword-mobile-stack";
            div.innerHTML = `
                <div class="w-3/5 flex flex-col gap-2 !p-4 !pt-[45px] keyword-mobile-main">
                    <div class="w-full flex items-center">
                        <label class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-1/5 keyword-mobile-label">Client Url</label>
                        <div class="w-4/5 flex items-center justify-between keyword-mobile-input-wrap">
                            <input type="text" placeholder="Enter Url" class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-[78%] client-url keyword-mobile-input-main">
                            <input type="text" value="0" class="bg-gray-50 !p-2 text-sm outline-none text-center border border-gray-300 w-1/5 client-url-quantity num-inp keyword-mobile-qty">
                        </div>
                    </div>
                    <div class="w-full flex items-center justify-end">
                        <button class="!p-1 bg-red-600 rounded text-sm cursor-pointer text-white remove-keyword-box">delete</button>
                    </div>
                </div>
                <div class="w-2/5 flex flex-col gap-1 !p-4 keyword-mobile-side">
                    <label class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Client Keyword</label>
                    <div class="w-full flex flex-wrap justify-between keywords-area-parent">
                        <div class="w-[78%] flex flex-wrap keyword-mobile-input-main">
                            <textarea rows="5" class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none keywords-area"></textarea>
                        </div>
                        <div class="w-1/5 flex flex-wrap keyword-mobile-qty">
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
        updateKeywordProgress();

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
            const paginationLoader = document.querySelector('.domain-pagination-loader');
            const tableBody = document.querySelector('#RandomDomainsTable tbody');

            try {
                showDomainLoader.classList.remove('hidden');
                setTimeout(() => showDomainLoader.classList.remove('opacity-0'), 200);

                // ✅ Load FIRST page of paginated domains
                await renderDomains({
                    domainCategoryId: domainCategory,
                    tableBody: tableBody,
                    loader: showDomainLoader,
                    paginationLoader: paginationLoader,
                    per_page: 100,
                    isPagination: false,
                    autoSelect: true
                });

                // ✅ INIT domain selection manager ONCE
                if (!window.HRandomDomainSelMgr) {

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


                } else {
                    // ✅ Restore highlight when re-entering step
                    window.HRandomDomainSelMgr.refresh();
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

        let domainSetMgrInitialized = false;
        const LAST_DOMAIN_SET_KEY = 'lastHiddenSetDomainSet';

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
                localStorage.removeItem('selectHSetDomains');
                if (window.HDomainSetMgr) {
                    window.HDomainSetMgr.clear();
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

                domainSetMgrInitialized = true;
            } else {
                window.HDomainSetMgr.refresh();
            }
        });

        const autoSelectHiddenDomainsBtn = document.getElementById('autoSelectHiddenDomainsBtn');
        if (autoSelectHiddenDomainsBtn) {
            autoSelectHiddenDomainsBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                if (!document.querySelector('.sidebar_domains')) {
                    alert('Load random domains first, then click Auto Select Required.');
                    return;
                }
                autoSelectHiddenDomainsBtn.disabled = true;
                const progressEl = document.getElementById('autoSelectHiddenDomainsProgress');
                if (progressEl) progressEl.textContent = 'Auto selecting...';
                try {
                    const result = await autoSelectAcrossPages({
                        localStorageKey: 'selectHDomains',
                        checkboxSelector: '.sidebar_domains',
                        containerSelector: '#domain-pagination',
                        progressSelector: '#autoSelectHiddenDomainsProgress',
                        manager: window.HRandomDomainSelMgr
                    });
                    if (!result.done) alert(`Only ${result.selectedCount}/${sidebarCount} domains are available.`);
                } finally {
                    autoSelectHiddenDomainsBtn.disabled = false;
                }
            });
        }

        const autoSelectHiddenSetDomainsBtn = document.getElementById('autoSelectHiddenSetDomainsBtn');
        if (autoSelectHiddenSetDomainsBtn) {
            autoSelectHiddenSetDomainsBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                if (!document.querySelector('.setdomains')) {
                    alert('Load domain set first, then click Auto Select Required.');
                    return;
                }
                autoSelectHiddenSetDomainsBtn.disabled = true;
                const progressEl = document.getElementById('autoSelectHiddenSetDomainsProgress');
                if (progressEl) progressEl.textContent = 'Auto selecting...';
                try {
                    const result = await autoSelectAcrossPages({
                        localStorageKey: 'selectHSetDomains',
                        checkboxSelector: '.setdomains',
                        containerSelector: '#domain-set-pagination',
                        progressSelector: '#autoSelectHiddenSetDomainsProgress',
                        manager: window.HDomainSetMgr
                    });
                    if (!result.done) alert(`Only ${result.selectedCount}/${sidebarCount} domains are available in this set.`);
                } finally {
                    autoSelectHiddenSetDomainsBtn.disabled = false;
                }
            });
        }

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
                const domains = localStorage.getItem('selectHDomains');
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
                const domains = localStorage.getItem('selectHSetDomains');
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
            e.target.submit();
        })

    }
});
