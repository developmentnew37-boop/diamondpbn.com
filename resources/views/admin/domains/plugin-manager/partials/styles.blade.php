<style>
    .theme-info-box {
        background-color: rgba(255, 74, 23, 0.08);
        border: 1px solid rgba(255, 74, 23, 0.25);
        border-radius: 0.25rem;
    }
    .pm-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
        min-height: 42px; padding: 0.625rem 1.25rem; font-size: 0.875rem; color: #fff;
        border-radius: 0.25rem; background-color: var(--primary-color); border: none; cursor: pointer;
        text-decoration: none;
    }
    .pm-btn:hover:not(:disabled) { background-color: #e0410f; color: #fff; }
    .pm-btn:disabled { opacity: 0.7; cursor: not-allowed; }
    .pm-btn-muted { background: #fff; color: var(--primary-color); border: 1px solid var(--primary-color); }
    .pm-btn-muted:hover { background: var(--primary-color); color: #fff; }
    .pm-btn-muted.is-active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
    .pm-btn-danger {
        background: #fff; color: #dc2626; border: 1px solid #fca5a5;
    }
    .pm-btn-danger:hover:not(:disabled) { background: #dc2626; color: #fff; border-color: #dc2626; }
    .pm-btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }
    .source-tab {
        display: inline-flex; align-items: center; gap: 0.5rem; min-height: 42px;
        padding: 0.5rem 1rem; font-size: 0.875rem; border-radius: 0.25rem;
        border: 1px solid #e5e7eb; background: #fff; color: #374151; cursor: pointer;
    }
    .source-tab.active { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }
    .source-panel { display: none; }
    .source-panel.active { display: flex; flex-direction: column; gap: 1rem; }
    .progress-track { width: 100%; height: 10px; background: #e5e7eb; border-radius: 9999px; overflow: hidden; }
    .progress-fill { height: 100%; width: 0; background: linear-gradient(90deg, var(--primary-color), #fb923c); transition: width 0.35s ease; }
    .status-badge { display: inline-flex; padding: 0.25rem 0.625rem; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; }
    .status-pending { background: #f3f4f6; color: #6b7280; }
    .status-processing { background: #dbeafe; color: #1d4ed8; }
    .status-success { background: #dcfce7; color: #166534; }
    .status-failed { background: #fee2e2; color: #991b1b; }
    .status-skipped { background: #fef3c7; color: #b45309; }
    .summary-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem 1.25rem; background: #fff; }
    .summary-card .value { font-size: 1.5rem; font-weight: 700; }
    .btn-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: pm-spin 0.8s linear infinite; }
    @keyframes pm-spin { to { transform: rotate(360deg); } }

    /* Deployment history */
    .pm-stat-card {
        display: flex; align-items: center; gap: 0.875rem;
        padding: 1rem 1.125rem; background: #fff; border: 1px solid #e5e7eb;
        border-radius: 0.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .pm-stat-card-blue { border-color: #bfdbfe; background: linear-gradient(135deg, #fff 0%, #eff6ff 100%); }
    .pm-stat-card-green { border-color: #bbf7d0; background: linear-gradient(135deg, #fff 0%, #f0fdf4 100%); }
    .pm-stat-card-orange { border-color: #fed7aa; background: linear-gradient(135deg, #fff 0%, #fff7ed 100%); }
    .pm-stat-icon { font-size: 1.75rem; color: var(--primary-color); opacity: 0.85; }
    .pm-stat-label { font-size: 0.75rem; color: #6b7280; margin: 0; }
    .pm-stat-value { font-size: 1.375rem; font-weight: 700; color: #111827; line-height: 1.2; margin: 0; }

    .pm-history-card { padding: 1.25rem 1.5rem; }

    .pm-filter-bar {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        margin-bottom: 1rem;
        padding: 1rem 1.125rem;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
    }
    .pm-filter-grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 0.875rem;
    }
    @media (min-width: 640px) {
        .pm-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (min-width: 1024px) {
        .pm-filter-grid { grid-template-columns: 1fr 1fr 1fr 1.6fr; }
    }
    .pm-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }
    .pm-filter-label {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #374151;
        margin: 0;
    }
    .pm-filter-control {
        width: 100%;
        min-height: 42px;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        line-height: 1.25;
        color: #111827;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 0.25rem;
        outline: none;
        appearance: none;
        -webkit-appearance: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    select.pm-filter-control {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b7280' d='M1.4 0.6L6 5.2 10.6.6 12 2 6 8 0 2z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.875rem center;
        background-size: 12px 8px;
        padding-right: 2.25rem;
        cursor: pointer;
    }
    .pm-filter-control:focus {
        border-color: var(--primary-color);
        background-color: #fff;
        box-shadow: 0 0 0 3px rgba(255, 74, 23, 0.12);
    }
    .pm-filter-control::placeholder { color: #9ca3af; }
    .pm-filter-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    .results-status-filter.is-active {
        background: var(--primary-color) !important;
        color: #fff !important;
        border-color: var(--primary-color) !important;
    }

    .pm-history-table-wrap {
        overflow-x: auto;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
    }

    .pm-history-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .pm-col-select { width: 3%; }
    .pm-col-started { width: 8%; }
    .pm-col-package { width: 22%; }
    .pm-col-operation { width: 8%; }
    .pm-col-scope { width: 10%; }
    .pm-col-progress { width: 11%; }
    .pm-col-results { width: 10%; }
    .pm-col-status { width: 11%; }
    .pm-col-actions { width: 11%; }

    .pm-history-table thead tr { background: #1f2937; color: #fff; }
    .pm-history-table thead th {
        padding: 0.75rem 0.875rem;
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-align: left;
        white-space: nowrap;
    }
    .pm-history-table thead th.pm-th-actions { text-align: right; }
    .pm-history-table thead th.pm-th-select { text-align: center; width: 2.75rem; }

    .pm-cell-select {
        align-items: center;
        justify-content: center;
        min-height: 4.25rem;
        padding: 0.75rem 0.5rem;
    }

    .pm-history-checkbox {
        width: 1rem;
        height: 1rem;
        accent-color: var(--primary-color);
        cursor: pointer;
    }

    .pm-history-pagination {
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 1px solid #f3f4f6;
        width: 100%;
        overflow-x: auto;
    }

    .pm-history-pagination nav[role="navigation"] {
        width: 100%;
        min-width: min(100%, 36rem);
    }

    .pm-history-table tbody td {
        padding: 0;
        vertical-align: middle;
        border-bottom: 1px solid #f3f4f6;
        background: #fff;
    }

    .pm-history-row:hover td { background: #fafafa; }
    .pm-history-row:last-child td { border-bottom: none; }

    .pm-cell {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 0.2rem;
        min-height: 4.25rem;
        padding: 0.75rem 0.875rem;
    }

    .pm-cell-center {
        align-items: flex-start;
    }

    .pm-cell-date { gap: 0.125rem; }

    .pm-cell-primary {
        font-weight: 600;
        color: #111827;
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pm-cell-secondary {
        font-size: 0.75rem;
        color: #6b7280;
        line-height: 1.3;
        white-space: nowrap;
    }

    .pm-cell-muted {
        font-size: 0.8125rem;
        color: #9ca3af;
    }

    .pm-package-name {
        font-weight: 600;
        color: #111827;
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pm-package-meta {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        min-width: 0;
        font-size: 0.75rem;
        color: #6b7280;
        line-height: 1.3;
    }

    .pm-version-tag {
        flex-shrink: 0;
        font-weight: 600;
        color: #374151;
    }

    .pm-meta-dot { flex-shrink: 0; color: #d1d5db; }

    .pm-folder-tag {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }

    .pm-scope-name { max-width: 100%; display: block; }

    .pm-progress-cell { gap: 0.375rem; min-width: 0; }

    .pm-progress-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #374151;
        line-height: 1;
    }

    .pm-progress-bar { height: 6px; margin: 0; }

    .pm-results-cell {
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.375rem;
        min-height: 4.25rem;
    }

    .pm-status-cell { gap: 0.35rem; align-items: flex-start; }

    .pm-deploy-status {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.3rem 0.625rem;
        font-size: 0.6875rem;
        font-weight: 700;
        border-radius: 9999px;
        text-transform: capitalize;
        white-space: nowrap;
        line-height: 1.2;
    }

    .pm-status-icon { font-size: 0.875rem !important; line-height: 1; }

    .pm-deploy-status-completed { background: #dcfce7; color: #166534; }
    .pm-deploy-status-completed-warn { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .pm-deploy-status-running { background: #dbeafe; color: #1d4ed8; }
    .pm-deploy-status-queued { background: #f3f4f6; color: #4b5563; }
    .pm-deploy-status-cancelled { background: #fef3c7; color: #b45309; }

    .pm-status-note {
        font-size: 0.6875rem;
        font-weight: 600;
        line-height: 1.2;
        padding-left: 0.125rem;
    }

    .pm-status-note-active { color: #2563eb; }
    .pm-status-note-error { color: #dc2626; }

    .pm-op-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.35rem 0.625rem;
        font-size: 0.6875rem;
        font-weight: 700;
        border-radius: 0.375rem;
        white-space: nowrap;
        line-height: 1.2;
    }

    .pm-op-install { background: #ecfdf5; color: #047857; }
    .pm-op-update, .pm-op-update_if_older { background: #eff6ff; color: #1d4ed8; }
    .pm-op-delete { background: #fef2f2; color: #b91c1c; }
    .pm-op-activate { background: #f0fdf4; color: #15803d; }
    .pm-op-deactivate { background: #fff7ed; color: #c2410c; }

    .pm-result-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.2rem 0.5rem;
        font-size: 0.6875rem;
        font-weight: 700;
        border-radius: 9999px;
        white-space: nowrap;
        line-height: 1.2;
    }

    .pm-result-success { background: #dcfce7; color: #166534; }
    .pm-result-failed { background: #fee2e2; color: #991b1b; }
    .pm-result-skipped { background: #fef3c7; color: #b45309; }

    .pm-actions-cell {
        flex-direction: row;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
        min-height: 4.25rem;
        padding-right: 1rem;
    }

    .pm-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        padding: 0;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #6b7280;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
        flex-shrink: 0;
    }

    .pm-icon-btn .material-symbols-outlined { font-size: 1.125rem !important; line-height: 1; }

    .pm-icon-btn-view:hover {
        border-color: var(--primary-color);
        background: rgba(255, 74, 23, 0.06);
        color: var(--primary-color);
    }

    .pm-icon-btn-delete:hover {
        border-color: #fca5a5;
        background: #fef2f2;
        color: #dc2626;
    }

    .pm-empty-state {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        text-align: center; padding: 3rem 1.5rem;
    }
    .pm-empty-icon { font-size: 3rem; color: #d1d5db; margin-bottom: 0.75rem; }

    @media (max-width: 1280px) {
        .pm-history-table { min-width: 980px; }
    }
</style>
