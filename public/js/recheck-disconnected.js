/**
 * Recheck disconnected inventory domains — queue + live progress + CSV export.
 */
(function () {
    const form = document.getElementById('rdForm');
    if (!form) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const startUrl = form.dataset.startUrl;
    const progressUrlTemplate = form.dataset.progressUrlTemplate;
    const exportUrlTemplate = form.dataset.exportUrlTemplate;
    const cancelUrlTemplate = form.dataset.cancelUrlTemplate;
    const pollIntervalMs = 800;

    let pollTimer = null;
    let activeCheckUuid = null;
    let resultsMap = new Map();
    let queuedWarningTimer = null;
    let lastProgressSince = null;

    const els = {
        alertBox: document.getElementById('rdAlert'),
        progressPanel: document.getElementById('rdProgress'),
        progressBar: document.getElementById('rdProgressBar'),
        progressText: document.getElementById('rdProgressText'),
        phaseText: document.getElementById('rdPhaseText'),
        progressTitle: document.getElementById('rdProgressTitle'),
        resultsPanel: document.getElementById('rdResults'),
        resultsBody: document.getElementById('rdResultsBody'),
        categoryBreakdown: document.getElementById('rdCategoryBreakdown'),
        submitBtn: document.getElementById('rdSubmitBtn'),
        submitLabel: document.getElementById('rdSubmitLabel'),
        submitSpinner: document.getElementById('rdSubmitSpinner'),
        cancelBtn: document.getElementById('rdCancelBtn'),
        exportBtn: document.getElementById('rdExportBtn'),
        summaryTotal: document.getElementById('rdSummaryTotal'),
        summaryConnected: document.getElementById('rdSummaryConnected'),
        summaryStill: document.getElementById('rdSummaryStill'),
        summaryErrors: document.getElementById('rdSummaryErrors'),
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (activeCheckUuid) return;

        clearAlert();
        setExportEnabled(false);
        setSubmitting(true);
        showProgressPanel('Starting disconnected recheck...');

        const formData = new FormData(form);

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
                throw new Error(data.message || 'Too many starts. Wait about a minute, then try again.');
            }

            if (!response.ok || !data.success) {
                throw new Error(data.message || `Server error (${response.status}).`);
            }

            if (data.truncated) {
                showAlert(
                    `Checking first ${data.total} of ${data.total_in_scope} disconnected domains. Narrow by category for the rest.`,
                    'warning'
                );
            }

            activeCheckUuid = data.check_uuid;
            resultsMap.clear();
            lastProgressSince = null;
            resetResultsTable();
            setCancelEnabled(true);
            updateSummary({
                total: data.total || 0,
                started_disconnected: data.total || 0,
                now_connected: 0,
                still_disconnected: 0,
                errors: 0,
            });

            startPolling();

            queuedWarningTimer = window.setTimeout(() => {
                if (activeCheckUuid) {
                    showAlert(
                        'Still queued. Ensure a worker is listening: php artisan queue:work --queue=domainCheck',
                        'warning'
                    );
                }
            }, 15000);
        } catch (error) {
            showAlert(error.message || 'Unable to start recheck.', 'error');
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

            applyProgressPayload(data);
        } catch (error) {
            stopPolling();
            activeCheckUuid = null;
            setSubmitting(false);
            showAlert(error.message || 'Lost connection while checking progress.', 'error');
        }
    }

    function applyProgressPayload(data) {
        if (data.server_time) {
            lastProgressSince = data.server_time;
        }

        renderProgress(data.check);
        updateSummary(data.check);
        renderCategoryBreakdown(data.check.by_category || []);
        renderResults(data.results || []);

        if (data.check.status !== 'queued' && queuedWarningTimer) {
            window.clearTimeout(queuedWarningTimer);
            queuedWarningTimer = null;
        }

        const allItemsFinalized = data.check.total > 0
            && data.check.processed >= data.check.total
            && (data.check.pending ?? 0) === 0;

        const isDone = data.check.is_finished || allItemsFinalized;

        if (isDone) {
            const finishedUuid = activeCheckUuid || data.check.uuid;
            stopPolling();
            activeCheckUuid = null;
            setSubmitting(false);
            setCancelEnabled(false);
            setProgressComplete(true);

            if (data.check.status === 'cancelled') {
                showAlert('Recheck cancelled.', 'warning');
                updatePhaseText('Cancelled');
                setExportEnabled(true, finishedUuid);
            } else if (data.check.status === 'completed' || allItemsFinalized) {
                showAlert(
                    `Done. ${data.check.now_connected} connected again, ${data.check.still_disconnected} still disconnected` +
                    (data.check.errors ? `, ${data.check.errors} with errors` : '') + '.',
                    'success'
                );
                updatePhaseText('Completed');
                setExportEnabled(true, finishedUuid);
            } else {
                showAlert(data.check.status_message || 'Recheck failed.', 'error');
                updatePhaseText('Failed');
            }
        } else {
            setProgressComplete(false);
            setSubmitting(true);
            setCancelEnabled(true);
            scheduleNextPoll(pollIntervalMs);
        }
    }

    function resumeFromBootstrap() {
        const bootstrapEl = document.getElementById('rdResumeBootstrap');
        if (!bootstrapEl) return;

        let bootstrap;
        try {
            bootstrap = JSON.parse(bootstrapEl.textContent || '{}');
        } catch (error) {
            return;
        }

        if (!bootstrap?.check?.uuid) return;

        resultsMap.clear();
        lastProgressSince = null;
        resetResultsTable();
        els.progressPanel?.classList.remove('hidden');

        const payload = {
            success: true,
            server_time: bootstrap.server_time || null,
            check: bootstrap.check,
            results: bootstrap.results || [],
        };

        if (bootstrap.check.is_finished) {
            activeCheckUuid = bootstrap.check.uuid;
            applyProgressPayload(payload);
            return;
        }

        activeCheckUuid = bootstrap.check.uuid;
        setSubmitting(true);
        setCancelEnabled(true);
        applyProgressPayload(payload);
    }

    function renderProgress(check) {
        const isComplete = check.is_finished
            || (check.total > 0 && check.processed >= check.total && (check.pending ?? 0) === 0);

        const percent = isComplete
            ? 100
            : (typeof check.progress_percent === 'number' ? check.progress_percent : 0);

        els.progressBar.style.width = `${percent}%`;
        els.progressText.textContent = `${check.processed} / ${check.total} finalized · ${percent}%`;
        updatePhaseText(check.status_message || defaultPhaseMessage(check));
    }

    function defaultPhaseMessage(check) {
        if (check.phase === 'backoff') return 'Waiting for campaign-style retry backoff...';
        if (check.phase === 'retry') return 'Retrying failed domains...';
        if (check.status === 'queued') return 'Queued — waiting for domainCheck queue worker...';
        return 'Checking disconnected domains...';
    }

    function updatePhaseText(text) {
        if (els.phaseText) els.phaseText.textContent = text;
    }

    function updateSummary(check) {
        if (els.summaryTotal) {
            els.summaryTotal.textContent = check.total ?? check.started_disconnected ?? 0;
        }
        if (els.summaryConnected) els.summaryConnected.textContent = check.now_connected ?? check.connected ?? 0;
        if (els.summaryStill) els.summaryStill.textContent = check.still_disconnected ?? check.disconnected ?? 0;
        if (els.summaryErrors) els.summaryErrors.textContent = check.errors ?? 0;
    }

    function renderCategoryBreakdown(rows) {
        if (!els.categoryBreakdown) return;
        if (!rows.length) {
            els.categoryBreakdown.classList.add('hidden');
            els.categoryBreakdown.innerHTML = '';
            return;
        }

        els.categoryBreakdown.classList.remove('hidden');
        els.categoryBreakdown.innerHTML = '<div class="font-semibold text-gray-800 !mb-2">By category</div>' +
            rows.map((row) => (
                `<div class="flex flex-wrap gap-2 !mb-1">
                    <span class="rd-category-chip">${escapeHtml(row.category)}
                        <strong class="text-green-700">${row.connected} up</strong>
                        <strong class="text-red-700">${row.disconnected} down</strong>
                    </span>
                </div>`
            )).join('');
    }

    function resetResultsTable() {
        if (els.resultsBody) els.resultsBody.innerHTML = '';
        els.resultsPanel?.classList.remove('hidden');
    }

    function renderResults(results) {
        results.forEach((row) => {
            resultsMap.set(row.id, row);
            let tr = document.getElementById(`rd-row-${row.id}`);
            if (!tr) {
                tr = document.createElement('tr');
                tr.id = `rd-row-${row.id}`;
                els.resultsBody.appendChild(tr);
            }

            tr.className = rowClass(row);
            tr.innerHTML = `
                <td class="!px-4 !py-3">${row.index}</td>
                <td class="!px-4 !py-3 font-medium break-all">${escapeHtml(row.domain)}</td>
                <td class="!px-4 !py-3">${connectionBadge(row)}</td>
                <td class="!px-4 !py-3 text-xs">${classificationDetails(row)}</td>
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
            pending: '<span class="rd-status-badge rd-status-pending">Pending</span>',
            checking: '<span class="rd-status-badge rd-status-checking">Checking</span>',
            retry_pending: '<span class="rd-status-badge rd-status-retry">Verifying</span>',
            connected: '<span class="rd-status-badge rd-status-connected">Connected</span>',
            disconnected: '<span class="rd-status-badge rd-status-disconnected">Disconnected</span>',
        };
        return map[row.check_status] || map.pending;
    }

    function classificationDetails(row) {
        if (!row.status_code) return '—';
        const code = escapeHtml(String(row.status_code).replace(/_/g, ' '));
        const details = [
            row.agent_version ? `v${escapeHtml(row.agent_version)}` : null,
            row.probe_method ? escapeHtml(row.probe_method) : null,
            row.http_status ? `HTTP ${escapeHtml(row.http_status)}` : null,
        ].filter(Boolean).join(' · ');
        return `<span class="font-semibold">${code}</span>${details ? `<br><span class="text-gray-500">${details}</span>` : ''}`;
    }

    function formatResponseTime(row) {
        return row.response_time_ms ? `${row.response_time_ms} ms` : '—';
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
                    : `Server error (${response.status}).`
            );
        }
    }

    function setSubmitting(isSubmitting) {
        if (!els.submitBtn) return;
        els.submitBtn.disabled = isSubmitting;
        els.submitSpinner?.classList.toggle('hidden', !isSubmitting);
        if (els.submitLabel) {
            els.submitLabel.textContent = isSubmitting ? 'Rechecking...' : 'Recheck disconnected';
        }
    }

    function setCancelEnabled(enabled) {
        if (!els.cancelBtn) return;
        els.cancelBtn.disabled = !enabled;
    }

    function setExportEnabled(enabled, uuid) {
        if (!els.exportBtn) return;
        if (enabled && uuid) {
            els.exportBtn.href = exportUrlTemplate.replace('__UUID__', uuid);
            els.exportBtn.removeAttribute('aria-disabled');
            els.exportBtn.classList.remove('opacity-50');
        } else {
            els.exportBtn.href = '#';
            els.exportBtn.setAttribute('aria-disabled', 'true');
            els.exportBtn.classList.add('opacity-50');
        }
    }

    function showProgressPanel(message) {
        els.progressPanel?.classList.remove('hidden');
        setProgressComplete(false);
        updatePhaseText(message);
        if (els.progressBar) els.progressBar.style.width = '0%';
        if (els.progressText) els.progressText.textContent = '0 / 0';
    }

    function hideProgressPanel() {
        els.progressPanel?.classList.add('hidden');
    }

    function setProgressComplete(isComplete) {
        if (els.progressTitle) {
            els.progressTitle.textContent = isComplete
                ? 'Recheck complete'
                : 'Background check in progress';
        }
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

    els.cancelBtn?.addEventListener('click', async () => {
        if (!activeCheckUuid) return;
        if (!window.confirm('Cancel this recheck? Remaining queue jobs will be purged.')) return;

        const uuid = activeCheckUuid;
        try {
            const response = await fetch(cancelUrlTemplate.replace('__UUID__', uuid), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await parseJsonResponse(response);
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Could not cancel recheck.');
            }
            stopPolling();
            activeCheckUuid = null;
            setSubmitting(false);
            setCancelEnabled(false);
            setProgressComplete(true);
            updatePhaseText('Cancelled');
            showAlert(data.message || 'Recheck cancelled.', 'warning');
            setExportEnabled(true, uuid);
        } catch (error) {
            showAlert(error.message || 'Cancel failed.', 'error');
        }
    });

    els.exportBtn?.addEventListener('click', (event) => {
        if (els.exportBtn.getAttribute('aria-disabled') === 'true') {
            event.preventDefault();
        }
    });

    resumeFromBootstrap();
})();
