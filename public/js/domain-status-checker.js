/**
 * Async domain status checker — background queue + live progress polling.
 */
(function () {
    const form = document.getElementById('statusCheckerForm');
    if (!form) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const startUrl = form.dataset.startUrl;
    const progressUrlTemplate = form.dataset.progressUrlTemplate;
    const pollIntervalMs = 800;

    let pollTimer = null;
    let activeCheckUuid = null;
    let resultsMap = new Map();
    let queuedWarningTimer = null;
    let lastProgressSince = null;

    const els = {
        alertBox: document.getElementById('statusCheckerAlert'),
        progressPanel: document.getElementById('statusCheckProgress'),
        progressBar: document.getElementById('statusCheckProgressBar'),
        progressText: document.getElementById('statusCheckProgressText'),
        phaseText: document.getElementById('statusCheckPhaseText'),
        summaryPanel: document.getElementById('statusCheckSummary'),
        resultsPanel: document.getElementById('statusCheckResults'),
        resultsBody: document.getElementById('statusCheckResultsBody'),
        submitBtn: document.getElementById('statusCheckSubmitBtn'),
        submitLabel: document.getElementById('statusCheckSubmitLabel'),
        submitSpinner: document.getElementById('statusCheckSubmitSpinner'),
        progressTitle: document.getElementById('statusCheckProgressTitle'),
        progressSpinner: document.getElementById('statusCheckProgressSpinner'),
    };

    const summaryFields = {
        total: document.getElementById('summaryTotal'),
        connected: document.getElementById('summaryConnected'),
        disconnected: document.getElementById('summaryDisconnected'),
        inInventory: document.getElementById('summaryInInventory'),
        notInInventory: document.getElementById('summaryNotInInventory'),
    };

    document.querySelectorAll('.source-tab').forEach((tab) => {
        tab.addEventListener('click', function () {
            const source = this.dataset.source;
            document.getElementById('sourceInput').value = source;
            document.querySelectorAll('.source-tab').forEach((t) => t.classList.remove('active'));
            document.querySelectorAll('.source-panel').forEach((p) => p.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('panel-' + source).classList.add('active');
        });
    });

    document.getElementById('clearManualBtn')?.addEventListener('click', () => {
        const textarea = document.getElementById('domains_list');
        if (textarea) textarea.value = '';
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (activeCheckUuid) return;

        clearAlert();
        setSubmitting(true);
        showProgressPanel('Starting background check...');

        const formData = new FormData(form);
        const source = formData.get('source');

        if (source === 'inventory') {
            formData.delete('domains_list');
        }

        try {
            const response = await fetch(startUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await parseJsonResponse(response);

            if (response.status === 429) {
                throw new Error(
                    data.message
                    || 'Too many status-check starts. Wait about 1 minute, then try again.'
                );
            }

            if (!response.ok || !data.success) {
                throw new Error(data.message || `Server error (${response.status}). Run: php artisan migrate --path=database/migrations/2026_06_19_100000_create_domain_status_checks_tables.php`);
            }

            if (data.truncated) {
                showAlert(
                    `Showing first 500 of ${data.total_in_scope} domains in inventory. Run again by category for the rest.`,
                    'warning'
                );
            }

            activeCheckUuid = data.check_uuid;
            resultsMap.clear();
            lastProgressSince = null;
            resetResultsTable(data.total);
            showSummaryPanel();
            updateSummary({
                total: data.total,
                connected: 0,
                disconnected: 0,
                in_inventory: 0,
                not_in_inventory: 0,
            });

            startPolling();

            queuedWarningTimer = window.setTimeout(() => {
                if (activeCheckUuid) {
                    showAlert(
                        'Check is still queued. Ensure a worker is listening on domainCheck: php artisan queue:work --queue=domainCheck,domainHealthSync. If domainHealthSync has a large backlog, the UI check should still run on domainCheck.',
                        'warning'
                    );
                }
            }, 15000);
        } catch (error) {
            showAlert(error.message || 'Unable to start status check.', 'error');
            hideProgressPanel();
            setSubmitting(false);
        }
    });

    function startPolling() {
        stopPolling();
        pollProgress();
    }

    function scheduleNextPoll(delayMs) {
        pollTimer = window.setTimeout(pollProgress, delayMs);
    }

    function stopPolling() {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
        if (queuedWarningTimer) {
            window.clearTimeout(queuedWarningTimer);
            queuedWarningTimer = null;
        }
    }

    async function pollProgress() {
        if (!activeCheckUuid) return;

        const url = new URL(progressUrlTemplate.replace('__UUID__', activeCheckUuid), window.location.origin);
        if (lastProgressSince) {
            url.searchParams.set('since', lastProgressSince);
        }

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await parseJsonResponse(response);

            if (!response.ok || !data.success) {
                throw new Error(data.message || `Progress check failed (${response.status}).`);
            }

            if (data.server_time) {
                lastProgressSince = data.server_time;
            }

            renderProgress(data.check);
            updateSummary(data.check);
            renderResults(data.results);

            if (data.check.status !== 'queued' && queuedWarningTimer) {
                window.clearTimeout(queuedWarningTimer);
                queuedWarningTimer = null;
            }

            const allItemsFinalized = data.check.total > 0
                && data.check.processed >= data.check.total
                && (data.check.pending ?? 0) === 0;

            const isDone = data.check.is_finished || allItemsFinalized;

            if (isDone) {
                stopPolling();
                activeCheckUuid = null;
                setSubmitting(false);
                setProgressComplete(true);

                if (data.check.status === 'completed' || allItemsFinalized) {
                    let message = `Check completed. ${data.check.connected} connected, ${data.check.disconnected} disconnected.`;
                    if (data.check.update_inventory && data.check.inventory_updated > 0) {
                        message += ` Updated ${data.check.inventory_updated} domain(s) in inventory.`;
                    }
                    showAlert(message, 'success');
                    updatePhaseText('Completed');
                } else {
                    showAlert(data.check.status_message || 'Status check failed.', 'error');
                    updatePhaseText('Failed');
                }
            } else {
                setProgressComplete(false);
                scheduleNextPoll(pollIntervalMs);
            }
        } catch (error) {
            stopPolling();
            activeCheckUuid = null;
            setSubmitting(false);
            showAlert(error.message || 'Lost connection while checking status.', 'error');
        }
    }

    function renderProgress(check) {
        const isComplete = check.is_finished
            || (check.total > 0 && check.processed >= check.total && (check.pending ?? 0) === 0);

        const percent = isComplete
            ? 100
            : (typeof check.progress_percent === 'number'
                ? check.progress_percent
                : (check.total > 0
                    ? Math.min(100, Math.round(((check.total - check.pending) / check.total) * 100))
                    : 0));

        els.progressBar.style.width = `${percent}%`;
        els.progressText.textContent = `${check.processed} / ${check.total} finalized · ${percent}% complete`;
        updatePhaseText(check.status_message || defaultPhaseMessage(check));
    }

    function defaultPhaseMessage(check) {
        if (check.phase === 'retry') {
            return 'Verifying disconnected domains (2nd pass)...';
        }
        if (check.status === 'queued') {
            return 'Queued — waiting for domainCheck queue worker...';
        }
        return 'Checking domains...';
    }

    function updatePhaseText(text) {
        if (els.phaseText) {
            els.phaseText.textContent = text;
        }
    }

    function updateSummary(check) {
        if (summaryFields.total) summaryFields.total.textContent = check.total ?? 0;
        if (summaryFields.connected) summaryFields.connected.textContent = check.connected ?? 0;
        if (summaryFields.disconnected) summaryFields.disconnected.textContent = check.disconnected ?? 0;
        if (summaryFields.inInventory) summaryFields.inInventory.textContent = check.in_inventory ?? 0;
        if (summaryFields.notInInventory) summaryFields.notInInventory.textContent = check.not_in_inventory ?? 0;
    }

    function resetResultsTable(total) {
        els.resultsBody.innerHTML = '';
        els.resultsPanel?.classList.remove('hidden');
        els.summaryPanel?.classList.remove('hidden');
    }

    function renderResults(results) {
        results.forEach((row) => {
            const existing = resultsMap.get(row.id);
            resultsMap.set(row.id, row);

            let tr = document.getElementById(`status-row-${row.id}`);

            if (!tr) {
                tr = document.createElement('tr');
                tr.id = `status-row-${row.id}`;
                tr.dataset.domain = row.domain;
                els.resultsBody.appendChild(tr);
            }

            tr.className = rowClass(row);
            tr.innerHTML = `
                <td class="!px-4 !py-3">${row.index}</td>
                <td class="!px-4 !py-3 font-medium break-all">${escapeHtml(row.domain)}</td>
                <td class="!px-4 !py-3">${connectionBadge(row)}</td>
                <td class="!px-4 !py-3 text-xs">${classificationDetails(row)}</td>
                <td class="!px-4 !py-3">${inventoryBadge(row.in_inventory)}</td>
                <td class="!px-4 !py-3 text-gray-600">${escapeHtml(row.category || '—')}</td>
                <td class="!px-4 !py-3 text-gray-600">${escapeHtml(row.message || '—')}</td>
                <td class="!px-4 !py-3 text-gray-500 text-xs whitespace-nowrap">${formatResponseTime(row)}</td>
            `;
        });
    }

    function rowClass(row) {
        if (row.check_status === 'connected') return 'border-b bg-green-50/40';
        if (row.check_status === 'disconnected') return 'border-b bg-red-50/30';
        if (row.check_status === 'retry_pending') return 'border-b bg-amber-50/50';
        if (row.check_status === 'checking') return 'border-b bg-blue-50/40';
        return 'border-b hover:bg-gray-50';
    }

    function connectionBadge(row) {
        const map = {
            pending: '<span class="status-badge status-pending"><span class="status-spinner"></span> Pending</span>',
            checking: '<span class="status-badge status-checking"><span class="status-spinner"></span> Checking</span>',
            retry_pending: '<span class="status-badge status-retry"><span class="status-spinner"></span> Verifying</span>',
            connected: '<span class="status-badge status-connected">Connected</span>',
            disconnected: '<span class="status-badge status-disconnected">Disconnected</span>',
        };

        return map[row.check_status] || map.pending;
    }

    function inventoryBadge(inInventory) {
        return inInventory
            ? '<span class="!px-2 !py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Yes</span>'
            : '<span class="!px-2 !py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">No</span>';
    }

    function classificationDetails(row) {
        if (!row.status_code) return '—';

        const code = escapeHtml(row.status_code.replace(/_/g, ' '));
        const details = [
            row.agent_version ? `v${escapeHtml(row.agent_version)}` : null,
            row.probe_method ? escapeHtml(row.probe_method) : null,
            row.http_status ? `HTTP ${escapeHtml(row.http_status)}` : null,
        ].filter(Boolean).join(' · ');

        return `<span class="font-semibold">${code}</span>${details ? `<br><span class="text-gray-500">${details}</span>` : ''}`;
    }

    function formatResponseTime(row) {
        if (row.response_time_ms) {
            return `${row.response_time_ms} ms`;
        }
        if (['pending', 'checking', 'retry_pending'].includes(row.check_status)) {
            return '—';
        }
        return '—';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    async function parseJsonResponse(response) {
        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch (error) {
            const snippet = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180);
            throw new Error(
                snippet
                    ? `Server error (${response.status}): ${snippet}`
                    : `Server error (${response.status}). Ensure migrations are run and the queue worker is active.`
            );
        }
    }

    function setSubmitting(isSubmitting) {
        if (!els.submitBtn) return;
        els.submitBtn.disabled = isSubmitting;
        els.submitSpinner?.classList.toggle('hidden', !isSubmitting);
        if (els.submitLabel) {
            els.submitLabel.textContent = isSubmitting ? 'Checking in background...' : 'Check Status';
        }
    }

    function showProgressPanel(message) {
        els.progressPanel?.classList.remove('hidden');
        setProgressComplete(false);
        updatePhaseText(message);
        els.progressBar.style.width = '0%';
        els.progressText.textContent = '0 / 0';
    }

    function hideProgressPanel() {
        els.progressPanel?.classList.add('hidden');
        setProgressComplete(false);
    }

    function setProgressComplete(isComplete) {
        els.progressPanel?.classList.toggle('progress-complete', isComplete);

        if (els.progressTitle) {
            els.progressTitle.textContent = isComplete
                ? 'Check Complete'
                : 'Background Check In Progress';
        }

        els.progressSpinner?.classList.toggle('hidden', isComplete);
    }

    function showSummaryPanel() {
        els.summaryPanel?.classList.remove('hidden');
        els.resultsPanel?.classList.remove('hidden');
    }

    function clearAlert() {
        if (!els.alertBox) return;
        els.alertBox.innerHTML = '';
        els.alertBox.classList.add('hidden');
    }

    function showAlert(message, type) {
        if (!els.alertBox) return;

        const classes = {
            success: 'bg-green-100 text-green-700',
            error: 'bg-red-100 text-red-700',
            warning: 'bg-yellow-100 text-yellow-800',
        };

        els.alertBox.className = `!p-4 text-sm rounded w-full ${classes[type] || classes.warning}`;
        els.alertBox.innerHTML = `<span class="font-medium">${escapeHtml(message)}</span>`;
        els.alertBox.classList.remove('hidden');
    }
})();
