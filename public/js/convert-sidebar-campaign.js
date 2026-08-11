(function () {
    const cfg = window.convertSidebarWizard;
    if (cfg) {
        initStep1(cfg);
    }
    if (window.convertScheduleStep) {
        initScheduleStep(window.convertScheduleStep);
    }

    function initStep1(config) {
        const picker = document.getElementById('convert_campaign_picker');
        const campaignHidden = document.getElementById('selected_campaign_id');
        const campaignLabel = picker?.querySelector('[data-convert-campaign-label]');
        const campaignMenu = picker?.querySelector('[data-convert-campaign-menu]');
        const campaignList = picker?.querySelector('[data-convert-campaign-list]');
        const campaignSearch = picker?.querySelector('[data-convert-campaign-search]');
        const campaignLoader = picker?.querySelector('[data-convert-campaign-loader]');
        const campaignChevron = picker?.querySelector('[data-convert-campaign-chevron]');
        const campaignBtn = picker?.querySelector('[data-convert-campaign-btn]');
        const sentinel = picker?.querySelector('[data-convert-campaign-sentinel]');
        const nextBtn = document.getElementById('btn_next');
        const reportUrlInput = document.getElementById('convert_report_url');
        const reportLookupBtn = document.getElementById('btn_lookup_report_url');
        const reportResultEl = document.getElementById('convert_report_url_result');

        if (!picker || !campaignHidden) {
            return;
        }

        function clearReportResult() {
            if (!reportResultEl) {
                return;
            }
            reportResultEl.textContent = '';
            reportResultEl.className = 'convert-report-url-result hidden';
        }

        function showReportResult(message, tone) {
            if (!reportResultEl) {
                return;
            }
            reportResultEl.textContent = message;
            reportResultEl.className = 'convert-report-url-result is-' + tone;
        }

        function applyCampaignSelection(campaignNo, id, canContinue) {
            campaignHidden.value = id ? String(id) : '';
            campaignLabel.textContent = campaignNo || 'Select campaign ...';
            campaignLabel.className = campaignNo ? 'text-black' : 'text-gray-400';
            nextBtn.disabled = !canContinue;
        }

        function selectCampaign(item) {
            if (!item || item.disabled) {
                return;
            }
            clearReportResult();
            applyCampaignSelection(item.campaign_no, item.id, true);
            closeCampaignMenu();
        }

        let menuOpen = false;
        let page = 1;
        let lastPage = 1;
        let loading = false;
        let searchQ = '';
        let debounce = null;
        let abortCtrl = null;

        function openCampaignMenu() {
            menuOpen = true;
            campaignMenu.classList.remove('hidden');
            campaignChevron.style.transform = 'rotate(180deg)';
            campaignSearch.focus();
            page = 1;
            loadCampaigns(false);
        }

        function closeCampaignMenu() {
            menuOpen = false;
            campaignMenu.classList.add('hidden');
            campaignChevron.style.transform = 'rotate(0deg)';
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function renderCampaignOptions(items, append) {
            if (!append && (!items || items.length === 0)) {
                campaignList.innerHTML = '<div class="!px-4 !py-8 text-center text-gray-400 text-sm">No campaigns found. Try another search or create a live sidebar campaign first.</div>';
                return;
            }

            const html = items.map(item => {
                const dis = item.disabled ? 'is-disabled' : '';
                const meta = `${item.status} · convertible ${item.convertible} · failed ${item.failed}`;
                return `<button type="button" class="convert-campaign-option w-full !px-4 !py-3 text-left hover:bg-gray-100 transition-colors ${dis}"
                    data-id="${item.id}" data-disabled="${item.disabled ? '1' : '0'}" data-label="${escapeHtml(item.campaign_no)}">
                    <span class="text-sm font-medium">${escapeHtml(item.campaign_no)}</span>
                    <span class="convert-campaign-option-meta">${escapeHtml(meta)}</span>
                </button>`;
            }).join('');

            if (append) {
                campaignList.insertAdjacentHTML('beforeend', html);
            } else {
                campaignList.innerHTML = html;
            }

            campaignList.querySelectorAll('.convert-campaign-option').forEach(el => {
                el.addEventListener('click', () => {
                    if (el.dataset.disabled === '1') {
                        return;
                    }
                    selectCampaign({
                        id: el.dataset.id,
                        campaign_no: el.dataset.label,
                        disabled: false,
                    });
                });
            });
        }

        async function loadCampaigns(append) {
            if (loading) {
                return;
            }

            loading = true;
            campaignLoader.classList.remove('hidden');
            if (abortCtrl) {
                abortCtrl.abort();
            }
            abortCtrl = new AbortController();

            const params = new URLSearchParams({
                page: String(page),
                per_page: '15',
            });
            if (searchQ.length >= 2) {
                params.set('q', searchQ);
            }

            try {
                const r = await fetch(`${config.searchUrl}?${params}`, {
                    signal: abortCtrl.signal,
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!r.ok) {
                    if (!append) {
                        campaignList.innerHTML = '<div class="!px-4 !py-6 text-sm text-red-600">Could not load campaigns.</div>';
                    }
                    return;
                }
                const j = await r.json();
                lastPage = j.last_page || 1;
                renderCampaignOptions(j.data || [], append);
            } catch (e) {
                if (e.name !== 'AbortError') {
                    console.error(e);
                }
            } finally {
                loading = false;
                campaignLoader.classList.add('hidden');
            }
        }

        campaignBtn?.addEventListener('click', e => {
            e.stopPropagation();
            if (menuOpen) {
                closeCampaignMenu();
            } else {
                openCampaignMenu();
            }
        });

        document.addEventListener('click', e => {
            if (!picker.contains(e.target)) {
                closeCampaignMenu();
            }
        });

        campaignSearch?.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                searchQ = campaignSearch.value.trim();
                page = 1;
                loadCampaigns(false);
            }, 300);
        });

        const observer = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting && page < lastPage && !loading && menuOpen) {
                page += 1;
                loadCampaigns(true);
            }
        });
        if (sentinel) {
            observer.observe(sentinel);
        }

        nextBtn?.addEventListener('click', () => {
            const id = campaignHidden.value;
            if (!id) {
                return;
            }
            window.location.href = `${config.step2Url}/${id}`;
        });

        reportLookupBtn?.addEventListener('click', () => lookupByReportUrl());
        reportUrlInput?.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupByReportUrl();
            }
        });

        async function lookupByReportUrl() {
            if (!config.reportLookupUrl || !reportUrlInput) {
                return;
            }

            const reportUrl = reportUrlInput.value.trim();
            if (reportUrl === '') {
                showReportResult('Paste a campaign report URL first.', 'error');
                applyCampaignSelection('', '', false);
                return;
            }

            reportLookupBtn.disabled = true;
            clearReportResult();
            showReportResult('Looking up campaign…', 'warning');

            try {
                const r = await fetch(config.reportLookupUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ report_url: reportUrl }),
                });
                const j = await r.json().catch(() => ({}));

                if (!r.ok || !j.ok) {
                    applyCampaignSelection('', '', false);
                    showReportResult(j.message || 'Campaign not found.', 'error');
                    return;
                }

                const c = j.campaign;
                const meta = `${c.type} · convertible ${c.convertible} · failed ${c.failed} · in progress ${c.in_progress}`;
                const lines = [`Found: ${c.campaign_no}`, meta];
                if (c.message) {
                    lines.push(c.message);
                }

                if (c.disabled) {
                    applyCampaignSelection(c.campaign_no, c.id, false);
                    showReportResult(lines.join(' — '), 'error');
                    return;
                }

                applyCampaignSelection(c.campaign_no, c.id, true);
                showReportResult(lines.join(' — '), c.in_progress > 0 ? 'warning' : 'success');
                closeCampaignMenu();
            } catch (e) {
                console.error(e);
                applyCampaignSelection('', '', false);
                showReportResult('Could not look up that report URL.', 'error');
            } finally {
                reportLookupBtn.disabled = false;
            }
        }

        if (config.preselectedId && config.preselectedCampaignNo) {
            applyCampaignSelection(config.preselectedCampaignNo, config.preselectedId, true);
        }
    }

    function initScheduleStep(step) {
        const buildBtn = document.getElementById('btn_build_grid');
        const tbody = document.querySelector('#date_grid tbody');
        const fromInp = document.getElementById('schedule_from_date');
        const toInp = document.getElementById('schedule_to_date');
        const hidden = document.getElementById('date_quantities');
        const pastHint = document.getElementById('past_hint');
        const form = document.getElementById('schedule_form');

        function formatLocalDate(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        buildBtn?.addEventListener('click', () => {
            const from = fromInp.value;
            const to = toInp.value;
            if (!from || !to) return alert('Select from and to dates.');
            const start = new Date(from + 'T00:00:00');
            const end = new Date(to + 'T00:00:00');
            if (end < start) return alert('To date must be on or after from date.');
            const days = [];
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                days.push(formatLocalDate(d));
            }
            const per = Math.floor(step.convertible / days.length);
            let rem = step.convertible - per * days.length;
            tbody.innerHTML = '';
            let past = 0;
            days.forEach(dateStr => {
                let qty = per + (rem > 0 ? 1 : 0);
                if (rem > 0) rem--;
                if (dateStr < step.today) past += qty;
                const tr = document.createElement('tr');
                tr.innerHTML = `<td class="!p-2 border">${dateStr}</td>
                    <td class="!p-2 border"><input type="number" min="0" class="qty-input w-20 border rounded !p-1" data-date="${dateStr}" value="${qty}"></td>`;
                tbody.appendChild(tr);
            });
            pastHint.textContent = past > 0
                ? `${past} task(s) on dates before today will publish on ${step.today} (conversion day).`
                : 'All assigned dates are today or in the future.';
            syncHidden();
        });

        function syncHidden() {
            const rows = [];
            tbody.querySelectorAll('.qty-input').forEach(inp => {
                rows.push({ date: inp.dataset.date, quantity: parseInt(inp.value, 10) || 0 });
            });
            hidden.value = JSON.stringify(rows);
        }

        tbody?.addEventListener('input', syncHidden);

        form?.addEventListener('submit', e => {
            syncHidden();
            const rows = JSON.parse(hidden.value || '[]');
            const sum = rows.reduce((a, r) => a + (r.quantity || 0), 0);
            if (sum !== step.convertible) {
                e.preventDefault();
                alert(`Total quantity must be ${step.convertible} (currently ${sum}).`);
            }
        });
    }
})();
