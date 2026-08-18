/**
 * Plugin Manager — upload library, deploy wizard, deployment progress.
 */
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function showAlert(message, type) {
        const box = document.getElementById('pluginManagerAlert');
        if (!box) return;
        const colors = {
            error: 'bg-red-50 border-red-200 text-red-800',
            warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
            success: 'bg-green-50 border-green-200 text-green-800',
        };
        box.className = 'w-full !mt-2 !p-4 rounded border text-sm ' + (colors[type] || colors.success);
        box.textContent = message;
        box.classList.remove('hidden');
    }

    function clearAlert() {
        const box = document.getElementById('pluginManagerAlert');
        if (box) box.classList.add('hidden');
    }

    async function parseJsonResponse(response) {
        const text = await response.text();
        if (response.status === 413) {
            let message = 'Upload rejected (413): the live web-server body limit is lower than this file size. Increase nginx and PHP upload limits, then reload nginx/PHP-FPM.';
            if (text && text.trim().startsWith('{')) {
                try {
                    const payload = JSON.parse(text);
                    if (payload.message) message = payload.message;
                } catch {
                    /* keep default */
                }
            }
            throw new Error(message);
        }
        try {
            return JSON.parse(text);
        } catch {
            throw new Error(text.slice(0, 300) || `Server error (${response.status})`);
        }
    }

    // --- Upload form (library page) ---
    const uploadForm = document.getElementById('pluginUploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearAlert();
            const btn = document.getElementById('pluginUploadBtn');
            const spinner = document.getElementById('pluginUploadSpinner');
            btn.disabled = true;
            spinner?.classList.remove('hidden');

            try {
                const maxBytes = parseInt(uploadForm.dataset.maxBytes, 10) || 0;
                const fileInput = uploadForm.querySelector('#plugin_zip');
                const file = fileInput?.files?.[0];
                if (file && maxBytes > 0 && file.size > maxBytes) {
                    const fileMb = (file.size / 1024 / 1024).toFixed(2);
                    const limitMb = uploadForm.dataset.maxMb || (maxBytes / 1024 / 1024).toFixed(2);
                    throw new Error(`File is ${fileMb} MB but the server allows ${limitMb} MB. Increase nginx/PHP upload limits on the VPS.`);
                }

                const res = await fetch(uploadForm.dataset.uploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(uploadForm),
                });
                const data = await parseJsonResponse(res);
                if (!res.ok || !data.success) throw new Error(data.message || 'Upload failed');
                showAlert(data.message, 'success');
                window.setTimeout(() => window.location.reload(), 800);
            } catch (err) {
                showAlert(err.message || 'Upload failed.', 'error');
            } finally {
                btn.disabled = false;
                spinner?.classList.add('hidden');
            }
        });

        document.querySelectorAll('.package-delete-btn').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!confirm('Remove this package from the library?')) return;
                clearAlert();
                try {
                    const res = await fetch(btn.dataset.deleteUrl, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await parseJsonResponse(res);
                    if (!res.ok || !data.success) throw new Error(data.message || 'Delete failed');
                    btn.closest('tr')?.remove();
                    showAlert(data.message, 'success');
                } catch (err) {
                    showAlert(err.message || 'Delete failed.', 'error');
                }
            });
        });
    }

    // --- Source tabs (deploy page) ---
    document.querySelectorAll('.source-tab').forEach((tab) => {
        tab.addEventListener('click', function () {
            const source = this.dataset.source;
            const input = document.getElementById('sourceInput');
            if (input) input.value = source;
            document.querySelectorAll('.source-tab').forEach((t) => t.classList.remove('active'));
            document.querySelectorAll('.source-panel').forEach((p) => p.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('panel-' + source)?.classList.add('active');
        });
    });

    // --- Package metadata (deploy page) ---
    const packageSelect = document.getElementById('plugin_package_uuid');
    const packageMetaPanel = document.getElementById('packageMetaPanel');
    function refreshPackageMeta() {
        if (!packageSelect || !packageMetaPanel) return;
        const option = packageSelect.selectedOptions[0];
        if (!option) return;
        document.getElementById('metaExpectedSlug').textContent = option.dataset.expectedSlug || '—';
        document.getElementById('metaLibrarySlug').textContent = option.dataset.librarySlug || '—';
        document.getElementById('metaVersion').textContent = option.dataset.version || '—';
        packageMetaPanel.classList.remove('hidden');
    }
    packageSelect?.addEventListener('change', refreshPackageMeta);
    refreshPackageMeta();

    // --- Deploy form ---
    const deployForm = document.getElementById('pluginDeployForm');
    if (deployForm) {
        deployForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearAlert();
            const btn = document.getElementById('deploySubmitBtn');
            const spinner = document.getElementById('deploySubmitSpinner');
            btn.disabled = true;
            spinner?.classList.remove('hidden');

            const formData = new FormData(deployForm);
            const source = formData.get('source');
            if (source === 'inventory') formData.delete('domains_list');
            if (!formData.get('activate_after')) formData.delete('activate_after');
            if (!formData.get('skip_if_same_version')) formData.delete('skip_if_same_version');

            try {
                const res = await fetch(deployForm.dataset.startUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });
                const data = await parseJsonResponse(res);
                if (!res.ok || !data.success) throw new Error(data.message || 'Deploy failed');
                if (data.truncated) {
                    showAlert(`First ${data.total} of ${data.total_in_scope} domains queued. Run again for the rest.`, 'warning');
                }
                window.location.href = data.redirect_url;
            } catch (err) {
                showAlert(err.message || 'Could not start deployment.', 'error');
                btn.disabled = false;
                spinner?.classList.add('hidden');
            }
        });
    }

    // --- Deployment progress page ---
    const progressPanel = document.getElementById('deploymentProgressPanel');
    if (progressPanel) {
        const progressUrl = progressPanel.dataset.progressUrl;
        const retryUrl = progressPanel.dataset.retryUrl;
        const cancelUrl = progressPanel.dataset.cancelUrl;
        let pollTimer = null;
        let lastProgressSince = null;
        const resultsMap = new Map();
        let resultsStatusFilter = 'all';

        const els = {
            bar: document.getElementById('deploymentProgressBar'),
            text: document.getElementById('deploymentProgressText'),
            phase: document.getElementById('deploymentPhaseText'),
            title: document.getElementById('deploymentProgressTitle'),
            body: document.getElementById('deploymentResultsBody'),
            sumTotal: document.getElementById('sumTotal'),
            sumSuccess: document.getElementById('sumSuccess'),
            sumFailed: document.getElementById('sumFailed'),
            sumSkipped: document.getElementById('sumSkipped'),
            sumPending: document.getElementById('sumPending'),
            retryBtn: document.getElementById('retryFailedBtn'),
            cancelBtn: document.getElementById('cancelDeployBtn'),
            exportGroup: document.getElementById('exportResultsGroup'),
        };

        function statusBadge(status) {
            const map = { pending: 'status-pending', processing: 'status-processing', success: 'status-success', failed: 'status-failed', skipped: 'status-skipped' };
            return `<span class="status-badge ${map[status] || 'status-pending'}">${status}</span>`;
        }

        function matchesResultsFilter(row) {
            if (resultsStatusFilter === 'all') return true;
            if (resultsStatusFilter === 'pending') {
                return row.item_status === 'pending' || row.item_status === 'processing';
            }
            return row.item_status === resultsStatusFilter;
        }

        function renderResults(results) {
            if (!els.body) return;

            results.forEach((row) => {
                resultsMap.set(row.id, row);
            });

            const sorted = Array.from(resultsMap.values())
                .filter(matchesResultsFilter)
                .sort((a, b) => a.index - b.index);

            if (sorted.length === 0) {
                els.body.innerHTML = `<tr><td colspan="11" class="!px-4 !py-6 text-sm text-gray-500 text-center">No rows for this filter.</td></tr>`;
                return;
            }

            els.body.innerHTML = sorted.map((r) => {
                const message = r.message || r.error_code || '';
                const audit = [
                    r.probe_method || null,
                    r.http_status ? `HTTP ${r.http_status}` : null,
                    r.retry_count ? `${r.retry_count} retries` : null,
                    r.audit_attempts ? `${r.audit_attempts} attempts` : null,
                ].filter(Boolean).join(' · ');
                return `
                <tr class="border-b border-gray-100">
                    <td class="!px-4 !py-2">${r.index}</td>
                    <td class="!px-4 !py-2 font-mono text-xs">${escapeHtml(r.domain)}</td>
                    <td class="!px-4 !py-2">${escapeHtml(r.category || '—')}</td>
                    <td class="!px-4 !py-2">${escapeHtml(r.version_before || '—')}</td>
                    <td class="!px-4 !py-2">${escapeHtml(r.version_after || '—')}</td>
                    <td class="!px-4 !py-2 text-xs">${escapeHtml(r.operation_result || '—')}</td>
                    <td class="!px-4 !py-2">${statusBadge(r.item_status)}</td>
                    <td class="!px-4 !py-2 font-mono text-xs text-gray-600">${escapeHtml(r.plugin_file || '—')}</td>
                    <td class="!px-4 !py-2 text-xs text-gray-600">${escapeHtml(r.resolved_via || '—')}</td>
                    <td class="!px-4 !py-2 text-xs text-gray-600">${escapeHtml(audit || '—')}</td>
                    <td class="!px-4 !py-2 text-xs text-gray-600">${escapeHtml(message)}</td>
                </tr>
            `;
            }).join('');
        }

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function updateUI(data) {
            const d = data.deployment;
            if (els.bar) els.bar.style.width = d.progress_percent + '%';
            if (els.text) els.text.textContent = `${d.processed} / ${d.total} processed`;
            if (els.phase) els.phase.textContent = d.status_message || d.status;
            if (els.sumSuccess) els.sumSuccess.textContent = d.success;
            if (els.sumFailed) els.sumFailed.textContent = d.failed;
            if (els.sumSkipped) els.sumSkipped.textContent = d.skipped;
            if (els.sumPending) els.sumPending.textContent = d.pending;
            renderResults(data.results);

            if (d.is_finished) {
                if (els.title) els.title.textContent = 'Deployment complete';
                stopPolling();
                if ((d.failed > 0 || d.skipped > 0) && els.retryBtn) {
                    els.retryBtn.classList.remove('hidden');
                }
                if (els.exportGroup && resultsMap.size > 0) {
                    els.exportGroup.classList.remove('hidden');
                }
                if (els.cancelBtn) els.cancelBtn.classList.add('hidden');
            } else if (els.cancelBtn) {
                els.cancelBtn.classList.remove('hidden');
            }
        }

        async function poll() {
            try {
                const url = new URL(progressUrl, window.location.origin);
                if (lastProgressSince) {
                    url.searchParams.set('since', lastProgressSince);
                }

                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await parseJsonResponse(res);
                if (data.success) {
                    if (data.server_time) {
                        lastProgressSince = data.server_time;
                    }
                    updateUI(data);
                }
            } catch (err) {
                console.error(err);
            }
        }

        function startPolling() {
            poll();
            pollTimer = window.setInterval(poll, 1500);
        }

        function stopPolling() {
            if (pollTimer) window.clearInterval(pollTimer);
        }

        document.querySelectorAll('.results-status-filter').forEach((btn) => {
            btn.addEventListener('click', () => {
                resultsStatusFilter = btn.dataset.status || 'all';
                document.querySelectorAll('.results-status-filter').forEach((el) => {
                    el.classList.toggle('is-active', el === btn);
                });
                renderResults([]);
            });
        });

        els.retryBtn?.addEventListener('click', async () => {
            try {
                const url = new URL(retryUrl, window.location.origin);
                url.searchParams.set('scope', 'both');
                const res = await fetch(url.toString(), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ scope: 'both' }),
                });
                const data = await parseJsonResponse(res);
                if (!res.ok || !data.success) throw new Error(data.message);
                window.location.href = data.redirect_url;
            } catch (err) {
                showAlert(err.message || 'Retry failed.', 'error');
            }
        });

        els.cancelBtn?.addEventListener('click', async () => {
            if (!confirm('Cancel pending items in this deployment?')) return;
            try {
                const res = await fetch(cancelUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await parseJsonResponse(res);
                if (!res.ok || !data.success) throw new Error(data.message);
                poll();
            } catch (err) {
                showAlert(err.message || 'Cancel failed.', 'error');
            }
        });

        startPolling();
    }

    // --- Deployment history delete ---
    document.querySelectorAll('.deployment-delete-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const isActive = btn.dataset.active === '1';
            const message = isActive
                ? 'This deployment is still running. It will be cancelled and removed from history. Continue?'
                : 'Remove this deployment from history? Domain results will be deleted.';
            if (!confirm(message)) return;

            clearAlert();
            btn.disabled = true;

            try {
                const res = await fetch(btn.dataset.deleteUrl, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await parseJsonResponse(res);
                if (!res.ok || !data.success) throw new Error(data.message || 'Delete failed');

                const row = btn.closest('.pm-history-row');
                row?.remove();

                const tbody = document.querySelector('.pm-history-table tbody');
                if (tbody && tbody.querySelectorAll('.pm-history-row').length === 0) {
                    window.location.reload();
                }

                showAlert(data.message, 'success');
                refreshDeploymentSelectionUi();
            } catch (err) {
                showAlert(err.message || 'Delete failed.', 'error');
                btn.disabled = false;
            }
        });
    });

    // --- Deployment history bulk actions ---
    const historyPanel = document.getElementById('deploymentHistoryPanel');
    if (historyPanel) {
        const selectAll = document.getElementById('deploymentSelectAll');
        const bulkDeleteBtn = document.getElementById('deploymentBulkDeleteBtn');
        const clearHistoryBtn = document.getElementById('deploymentClearHistoryBtn');
        const selectedCountEl = document.getElementById('deploymentSelectedCount');
        const rowCheckboxes = () => Array.from(document.querySelectorAll('.deployment-row-checkbox'));

        function refreshDeploymentSelectionUi() {
            const boxes = rowCheckboxes();
            const checked = boxes.filter((cb) => cb.checked);
            const count = checked.length;

            if (selectedCountEl) {
                if (count > 0) {
                    selectedCountEl.textContent = count + ' selected';
                    selectedCountEl.classList.remove('hidden');
                } else {
                    selectedCountEl.classList.add('hidden');
                }
            }

            if (bulkDeleteBtn) {
                bulkDeleteBtn.disabled = count === 0;
            }

            if (selectAll && boxes.length > 0) {
                selectAll.checked = count === boxes.length;
                selectAll.indeterminate = count > 0 && count < boxes.length;
            }
        }

        selectAll?.addEventListener('change', () => {
            rowCheckboxes().forEach((cb) => {
                cb.checked = selectAll.checked;
            });
            refreshDeploymentSelectionUi();
        });

        rowCheckboxes().forEach((cb) => {
            cb.addEventListener('change', refreshDeploymentSelectionUi);
        });

        bulkDeleteBtn?.addEventListener('click', async () => {
            const uuids = rowCheckboxes().filter((cb) => cb.checked).map((cb) => cb.value);
            if (uuids.length === 0) return;

            if (!confirm('Delete ' + uuids.length + ' selected deployment record(s) from history?')) return;

            clearAlert();
            bulkDeleteBtn.disabled = true;

            try {
                const res = await fetch(historyPanel.dataset.bulkDeleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ uuids }),
                });
                const data = await parseJsonResponse(res);
                if (!res.ok || !data.success) throw new Error(data.message || 'Bulk delete failed');

                showAlert(data.message, 'success');
                window.setTimeout(() => window.location.reload(), 600);
            } catch (err) {
                showAlert(err.message || 'Bulk delete failed.', 'error');
                refreshDeploymentSelectionUi();
            }
        });

        clearHistoryBtn?.addEventListener('click', async () => {
            if (!confirm('Clear ALL deployment history? This removes every record and cancels any active runs. This cannot be undone.')) return;

            clearAlert();
            clearHistoryBtn.disabled = true;

            try {
                const res = await fetch(historyPanel.dataset.clearUrl, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await parseJsonResponse(res);
                if (!res.ok || !data.success) throw new Error(data.message || 'Clear history failed');

                showAlert(data.message, 'success');
                window.setTimeout(() => window.location.reload(), 600);
            } catch (err) {
                showAlert(err.message || 'Clear history failed.', 'error');
                clearHistoryBtn.disabled = false;
            }
        });

        refreshDeploymentSelectionUi();
    }
})();
