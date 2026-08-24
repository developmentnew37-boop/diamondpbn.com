(function () {
    const panel = document.getElementById('local-client-billing-panel');
    if (!panel) return;

    const clientSelect = document.getElementById('local_client_id');
    const currencySelect = document.getElementById('billing_currency');
    const estimateBox = document.getElementById('local-client-billing-estimate');
    const totalEl = document.getElementById('local-client-billing-total');
    const linesEl = document.getElementById('local-client-billing-lines');
    const errorEl = document.getElementById('local-client-billing-error');
    const errorTextEl = document.getElementById('local-client-billing-error-text');
    const urlTemplate = panel.dataset.estimateUrlTemplate || '';
    const campaignType = panel.dataset.campaignType || 'post';
    const isSticky = panel.dataset.isSticky === '1';
    const manualDomainsArea = document.getElementById('manual-domains-area');

    let refreshTimer = null;
    let refreshRequestId = 0;
    let manualResolveRequest = 0;
    let lastEstimateKey = '';

    function getDomainHolder() {
        return document.getElementById('campaigns_domains_holder') || document.getElementById('campaigns_domains');
    }

    function getDomainSelectionStorageKeys() {
        if (campaignType === 'hidden_links') {
            return { random: 'selectHDomains', set: 'selectHSetDomains' };
        }
        if (campaignType === 'sidebar' || campaignType === 'schedule_sidebar') {
            return { random: 'selectSidebarDomains', set: 'selectSidebarSetDomains' };
        }

        return { random: 'selectDomains', set: 'selectSetDomains' };
    }

    function getExpectedTaskCount() {
        const quantityInputId = campaignType === 'hidden_links'
            ? 'hidden-quantity'
            : (campaignType === 'sidebar' || campaignType === 'schedule_sidebar'
                ? 'sidebar-quantity'
                : 'post-quantity');
        const value = parseInt((document.getElementById(quantityInputId)?.value || '').trim(), 10);

        return Number.isFinite(value) && value > 0 ? value : 0;
    }

    function parseStoredDomainIds(raw) {
        if (!raw) {
            return [];
        }

        try {
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed.map((id) => parseInt(id, 10)).filter(Boolean);
        } catch (e) {
            return [];
        }
    }

    function getSelectedDomainMethod() {
        return document.querySelector('input[name="sel_domains"]:checked')?.value ?? null;
    }

    function isManualDomainMethodSelected() {
        return getSelectedDomainMethod() === '2';
    }

    function parseManualDomainLines(raw) {
        return String(raw || '')
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean);
    }

    function manualDomainsFingerprint(domains) {
        return domains.map((d) => d.toLowerCase()).join('\n');
    }

    function readDomainIdsFromLocalStorage() {
        const method = getSelectedDomainMethod();
        if (method !== '0' && method !== '1') {
            return [];
        }

        const keys = getDomainSelectionStorageKeys();
        const storageKey = method === '1' ? keys.set : keys.random;

        return parseStoredDomainIds(localStorage.getItem(storageKey));
    }

    function setHolderValue(holder, value) {
        if (!holder || holder.value === value) {
            return;
        }

        holder.value = value;
    }

    function syncDomainsToHolder(domainIds) {
        const holder = getDomainHolder();
        if (!holder) {
            return domainIds;
        }

        const payload = JSON.stringify(domainIds);
        setHolderValue(holder, payload);

        return domainIds;
    }

    function rememberManualValidation(holder, domains, domainIds) {
        if (!holder) {
            return;
        }

        holder.dataset.lcbManualFp = manualDomainsFingerprint(domains);
        setHolderValue(holder, JSON.stringify(domainIds));
    }

    function clearManualValidationCache(holder) {
        if (!holder) {
            return;
        }

        delete holder.dataset.lcbManualFp;
        setHolderValue(holder, '');
    }

    function getSelectedDomainIds() {
        const holder = getDomainHolder();
        if (holder && holder.value) {
            const fromHolder = parseStoredDomainIds(holder.value);
            if (fromHolder.length) {
                return fromHolder;
            }
        }

        const fromStorage = readDomainIdsFromLocalStorage();
        if (fromStorage.length) {
            return syncDomainsToHolder(fromStorage);
        }

        const checked = Array.from(document.querySelectorAll(
            'input[name="domain_ids[]"]:checked, input.domain-checkbox:checked, .domains:checked, .sidebar_domains:checked, .setdomains:checked'
        ));
        if (checked.length) {
            const ids = checked.map((el) => parseInt(el.value, 10)).filter(Boolean);
            return syncDomainsToHolder(ids);
        }

        return [];
    }

    /**
     * @returns {Promise<{ ids: number[], error: string|null }>}
     */
    async function resolveCampaignDomainIds(forceValidate = false) {
        if (isManualDomainMethodSelected()) {
            return resolveManualDomainIds(forceValidate);
        }

        const fromStorage = readDomainIdsFromLocalStorage();
        if (fromStorage.length) {
            return { ids: syncDomainsToHolder(fromStorage), error: null };
        }

        const ids = getSelectedDomainIds();
        if (!ids.length && forceValidate) {
            throw new Error('Select domains (random, domain set, or manual) before submitting with a local client.');
        }

        if (!ids.length) {
            return {
                ids: [],
                error: 'Select domains (random, domain set, or manual) to see the billing estimate.',
            };
        }

        return { ids, error: null };
    }

    /**
     * @returns {Promise<{ ids: number[], error: string|null }>}
     */
    async function resolveManualDomainIds(forceValidate = false) {
        const holder = getDomainHolder();
        if (!manualDomainsArea || !holder || !isManualDomainMethodSelected()) {
            const ids = getSelectedDomainIds();
            return {
                ids,
                error: ids.length ? null : 'Select manual domains to see the billing estimate.',
            };
        }

        const domains = parseManualDomainLines(manualDomainsArea.value);

        if (!domains.length) {
            clearManualValidationCache(holder);
            return {
                ids: [],
                error: 'Paste manual domains to see the billing estimate.',
            };
        }

        const cachedFp = holder.dataset.lcbManualFp || '';
        const cachedIds = parseStoredDomainIds(holder.value);
        if (
            !forceValidate
            && cachedFp
            && cachedFp === manualDomainsFingerprint(domains)
            && cachedIds.length === domains.length
        ) {
            return { ids: cachedIds, error: null };
        }

        const requestId = ++manualResolveRequest;

        try {
            const response = await fetch('/api/admin/domains/validate', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ domains }),
            });

            const res = await response.json().catch(() => ({}));
            if (requestId !== manualResolveRequest) {
                return { ids: getSelectedDomainIds(), error: null };
            }

            if (res.status && Array.isArray(res.data?.domain_ids)) {
                const ids = res.data.domain_ids.map((id) => parseInt(id, 10)).filter(Boolean);
                rememberManualValidation(holder, domains, ids);
                return { ids, error: null };
            }

            const missing = Array.isArray(res?.data?.missing) ? res.data.missing : [];
            let message = res.message || 'Could not resolve manual domains for estimate.';
            if (missing.length) {
                const preview = missing.slice(0, 5).join(', ');
                const more = missing.length > 5 ? ` (+${missing.length - 5} more)` : '';
                message += ` Missing: ${preview}${more}.`;
            }

            if (forceValidate) {
                throw new Error(message);
            }

            clearManualValidationCache(holder);
            return { ids: [], error: message };
        } catch (e) {
            if (forceValidate) {
                throw e;
            }

            clearManualValidationCache(holder);
            return {
                ids: [],
                error: e?.message || 'Could not resolve manual domains for estimate.',
            };
        }
    }

    function extractApiErrorMessage(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }

        if (typeof data.message === 'string' && data.message.trim()) {
            return data.message.trim();
        }

        if (data.errors && typeof data.errors === 'object') {
            for (const key of Object.keys(data.errors)) {
                const val = data.errors[key];
                if (Array.isArray(val) && val[0]) {
                    return String(val[0]);
                }
                if (typeof val === 'string' && val.trim()) {
                    return val.trim();
                }
            }
        }

        return fallback;
    }

    async function fetchBillingEstimate(clientId, domainIds) {
        const url = urlTemplate.replace('__ID__', clientId);
        const params = new URLSearchParams();
        params.set('campaign_type', campaignType);
        params.set('is_sticky', isSticky ? '1' : '0');
        if (currencySelect?.value) {
            params.set('currency', currencySelect.value);
        }
        domainIds.forEach((id) => params.append('domain_ids[]', String(id)));

        const response = await fetch(`${url}?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            throw new Error('Could not calculate client billing.');
        }

        if (!response.ok || !data.success) {
            throw new Error(extractApiErrorMessage(data, 'Could not calculate client billing.'));
        }

        return data;
    }

    function hideEstimate() {
        lastEstimateKey = '';
        estimateBox.classList.add('hidden');
        errorEl.classList.add('hidden');
    }

    function showEstimateError(message) {
        lastEstimateKey = '';
        estimateBox.classList.add('hidden');
        const text = message || 'Estimate failed.';
        if (errorTextEl) {
            errorTextEl.textContent = text;
        } else {
            errorEl.textContent = text;
        }
        errorEl.classList.remove('hidden');
    }

    function renderEstimate(data) {
        errorEl.classList.add('hidden');
        totalEl.innerHTML = `
            <span class="lcb-estimate-label">Estimated total</span>
            <span class="lcb-estimate-amount">${data.formatted_total}</span>
        `;
        linesEl.innerHTML = (data.lines || []).map((line) =>
            `<li class="lcb-estimate-line">
                <span>
                    ${line.domain_name}
                    <span class="lcb-estimate-line-meta"> · ${line.category_name}</span>
                </span>
                <span class="lcb-estimate-line-price">${line.formatted_unit_price}</span>
            </li>`
        ).join('');
        estimateBox.classList.remove('hidden');
    }

    function buildEstimateKey(clientId, domainIds) {
        const sortedIds = domainIds.slice().sort((a, b) => a - b).join(',');
        return `${clientId}|${currencySelect?.value || ''}|${sortedIds}`;
    }

    async function refreshEstimate() {
        const clientId = clientSelect?.value;
        if (!clientId) {
            hideEstimate();
            return;
        }

        let resolved;
        try {
            resolved = await resolveCampaignDomainIds(false);
        } catch (err) {
            showEstimateError(err.message || 'Could not resolve domains for estimate.');
            return;
        }

        const domainIds = resolved.ids || [];
        if (!domainIds.length) {
            showEstimateError(
                resolved.error
                || 'Select domains first to see the billing estimate.'
            );
            return;
        }

        const estimateKey = buildEstimateKey(clientId, domainIds);
        if (estimateKey === lastEstimateKey) {
            return;
        }

        const requestId = ++refreshRequestId;

        try {
            const data = await fetchBillingEstimate(clientId, domainIds);
            if (requestId !== refreshRequestId) {
                return;
            }

            lastEstimateKey = estimateKey;
            renderEstimate(data);
        } catch (err) {
            if (requestId !== refreshRequestId) {
                return;
            }

            showEstimateError(err.message || 'Estimate failed.');
        }
    }

    function scheduleRefreshEstimate() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refreshEstimate, 400);
    }

    window.validateLocalClientBillingBeforeSubmit = async function () {
        const clientId = clientSelect?.value;
        if (!clientId) {
            return true;
        }

        let domainIds = [];
        try {
            const resolved = await resolveCampaignDomainIds(true);
            domainIds = resolved.ids || [];
            if (!domainIds.length && resolved.error) {
                alert(resolved.error);
                return false;
            }
        } catch (err) {
            alert(err.message || 'Could not resolve selected domains for client billing.');
            return false;
        }

        if (!domainIds.length) {
            alert('A client is selected but no billable domains were resolved. Select domains first (same count as campaign quantity).');
            return false;
        }

        const expectedCount = getExpectedTaskCount();
        if (expectedCount > 0 && domainIds.length !== expectedCount) {
            alert(`Domain count (${domainIds.length}) must match campaign quantity (${expectedCount}). Adjust your selection before submitting.`);
            return false;
        }

        try {
            await fetchBillingEstimate(clientId, domainIds);
        } catch (err) {
            alert(err.message || 'Client billing validation failed. Check the client price matrix for these domain categories.');
            return false;
        }

        return true;
    };

    window.syncCampaignDomainsToHolder = function () {
        return syncDomainsToHolder(getSelectedDomainIds());
    };

    clientSelect?.addEventListener('change', function () {
        const option = this.selectedOptions[0];
        if (option?.dataset.currency) {
            currencySelect.value = option.dataset.currency;
        }
        lastEstimateKey = '';
        scheduleRefreshEstimate();
    });

    currencySelect?.addEventListener('change', function () {
        lastEstimateKey = '';
        scheduleRefreshEstimate();
    });

    document.addEventListener('change', function (e) {
        if (e.target.matches(
            'input[name="domain_ids[]"], input.domain-checkbox, input[name="sel_domains"], .domains, .sidebar_domains, .setdomains, #hidden-quantity, #sidebar-quantity, #post-quantity'
        )) {
            lastEstimateKey = '';
            scheduleRefreshEstimate();
        }
    });

    manualDomainsArea?.addEventListener('input', function () {
        lastEstimateKey = '';
        const holder = getDomainHolder();
        if (holder) {
            delete holder.dataset.lcbManualFp;
        }
        scheduleRefreshEstimate();
    });
})();
