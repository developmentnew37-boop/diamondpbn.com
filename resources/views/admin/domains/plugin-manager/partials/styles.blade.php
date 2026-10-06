<style>
    .theme-info-box {
        background-color: rgba(255, 74, 23, 0.08);
        border: 1px solid rgba(255, 74, 23, 0.25);
        border-radius: 0.25rem;
    }
    .pm-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        width: 100%;
        margin-bottom: 1.25rem;
    }
    .pm-page-heading { min-width: 0; }
    .pm-page-header .page-title { margin-bottom: 0.25rem; }
    .pm-page-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-shrink: 0;
        margin-left: auto;
        gap: 0.5rem;
    }
    .pm-page-actions .pm-btn {
        min-height: 40px;
        padding: 0 1rem;
        white-space: nowrap;
    }
    @media (max-width: 720px) {
        .pm-page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .pm-page-actions {
            margin-left: 0;
            justify-content: flex-start;
        }
    }
    .pm-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 0.375rem;
        min-height: 42px; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 600; color: #fff;
        border-radius: 0.375rem; background-color: var(--primary-color); border: 1px solid transparent; cursor: pointer;
        text-decoration: none; line-height: 1;
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

    /* Progress page toolbar + segmented controls */
    .pm-progress-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.875rem;
        margin-top: 0.25rem;
        margin-bottom: 0.25rem;
        padding: 0.875rem 1rem;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
    }
    .pm-progress-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }
    .pm-export-panel {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.625rem;
    }
    .pm-export-panel.hidden { display: none !important; }
    .pm-export-label {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .pm-segmented {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: stretch;
        padding: 0.2rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .pm-segment {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0.35rem 0.8rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #4b5563;
        background: transparent;
        border: none;
        border-radius: 0.35rem;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
        transition: background-color 0.15s ease, color 0.15s ease;
    }
    .pm-segment:hover {
        background: #f3f4f6;
        color: #111827;
        text-decoration: none;
    }
    .pm-segment.is-active,
    .results-status-filter.is-active {
        background: var(--primary-color);
        color: #fff;
    }
    .pm-segment.is-active:hover,
    .results-status-filter.is-active:hover {
        background: #e0410f;
        color: #fff;
    }

    .pm-results-toolbar {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #f3f4f6;
    }
    @media (min-width: 640px) {
        .pm-results-toolbar {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }
    .pm-results-table-wrap {
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .pm-results-table-wrap table { margin: 0; }

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
        .pm-filter-grid-wide { grid-template-columns: repeat(5, minmax(0, 1fr)); }
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
    .pm-col-user { width: 12%; }
    .pm-col-package { width: 22%; }
    .pm-history-table-all .pm-col-package { width: 16%; }
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
        .pm-history-table-all { min-width: 1120px; }
    }

    .pm-library-card { min-width: 0; max-width: 100%; overflow: hidden; }
    .pm-library-note {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        min-width: 0;
        margin-bottom: 1.25rem;
        padding: 1rem 1.125rem;
        background: rgba(255, 74, 23, 0.08);
        border: 1px solid rgba(255, 74, 23, 0.25);
        border-radius: 0.5rem;
    }
    .pm-library-note-icon { color: var(--primary-color); font-size: 1.25rem; flex-shrink: 0; margin-top: 0.1rem; }
    .pm-library-note-body { min-width: 0; flex: 1; }
    .pm-library-note-title { margin: 0; font-size: 0.875rem; font-weight: 700; color: #111827; }
    .pm-library-note-copy { margin: 0.35rem 0 0; font-size: 0.8125rem; line-height: 1.55; color: #374151; }
    .pm-upload-form {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid #f3f4f6;
    }
    .pm-upload-copy p { margin: 0.25rem 0 0; }
    .pm-upload-fields {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        max-width: 36rem;
        min-width: 0;
    }
    .pm-file-input {
        width: 100%;
        min-height: 42px;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
    }
    .pm-library-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .pm-library-count {
        font-size: 0.75rem;
        color: #6b7280;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        padding: 0.25rem 0.75rem;
        white-space: nowrap;
    }
    .pm-library-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
    }
    .pm-library-table {
        width: max-content;
        min-width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }
    .pm-library-table thead tr { background: #1f2937; color: #fff; }
    .pm-library-table thead th {
        padding: 0.75rem 0.875rem;
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        text-align: left;
        white-space: nowrap;
    }
    .pm-library-table thead th.pm-th-actions { text-align: right; }
    .pm-library-table thead th.pm-th-sno,
    .pm-library-table tbody td.pm-sno {
        width: 3.25rem;
        text-align: center;
        color: #6b7280;
        font-variant-numeric: tabular-nums;
    }
    .pm-library-table thead th.pm-th-sno { color: #fff; }
    .pm-library-table tbody td {
        padding: 0.85rem 0.875rem;
        vertical-align: middle;
        border-bottom: 1px solid #f3f4f6;
        background: #fff;
        color: #111827;
        white-space: nowrap;
    }
    .pm-library-table tbody tr:hover td { background: #fafafa; }
    .pm-library-table tbody tr:last-child td { border-bottom: none; }
    .pm-library-name {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        max-width: 16rem;
        font-weight: 600;
    }
    .pm-name-text,
    .pm-version {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .pm-name-text { flex: 1 1 auto; }
    .pm-version { max-width: 11rem; }
    .pm-agent-tag { flex-shrink: 0; }
    .pm-agent-tag {
        display: inline-flex;
        padding: 0.1rem 0.4rem;
        font-size: 0.6875rem;
        font-weight: 700;
        color: #c2410c;
        background: #fff7ed;
        border-radius: 999px;
    }
    .pm-mono {
        display: inline-block;
        max-width: 11rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: bottom;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.75rem;
        color: #374151;
    }
    .pm-library-date { white-space: nowrap; color: #4b5563; }
    .pm-row-actions {
        display: flex;
        flex-wrap: nowrap;
        justify-content: flex-end;
        gap: 0.375rem;
    }
    .pm-row-action {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        height: 32px;
        padding: 0 0.7rem;
        border-radius: 999px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #374151;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }
    .pm-row-action .material-symbols-outlined { font-size: 1rem !important; }
    .pm-row-action:hover { border-color: #d1d5db; background: #f9fafb; color: #111827; text-decoration: none; }
    .pm-row-action-primary { border-color: rgba(255, 74, 23, 0.35); color: var(--primary-color); background: rgba(255, 74, 23, 0.06); }
    .pm-row-action-primary:hover { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }
    .pm-batch-tag {
        display: inline-flex;
        align-items: center;
        margin-left: 0.35rem;
        padding: 0.1rem 0.45rem;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 0.6875rem;
        font-weight: 700;
        white-space: nowrap;
        vertical-align: middle;
    }
    .pm-package-search {
        width: 100%;
        max-width: 24rem;
        min-height: 42px;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        color: #111827;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        outline: none;
    }
    .pm-package-search:focus {
        border-color: var(--primary-color);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(255, 74, 23, 0.12);
    }
    .pm-package-picker {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
        gap: 0.75rem;
        max-height: 22rem;
        overflow: auto;
        padding: 0.875rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #fff;
    }
    .pm-package-option {
        position: relative;
        display: flex;
        align-items: flex-start;
        min-height: 5.75rem;
        min-width: 0;
        padding: 0.85rem 2rem 0.75rem 0.85rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #fff;
        cursor: pointer;
    }
    .pm-package-option:hover { border-color: #d1d5db; }
    .pm-package-option:has(input:focus-visible) {
        outline: 2px solid var(--primary-color);
        outline-offset: 2px;
    }
    .pm-package-option:has(input:checked) {
        border-color: var(--primary-color);
        background: rgba(255, 74, 23, 0.06);
        box-shadow: inset 0 0 0 1px var(--primary-color);
    }
    .pm-package-option-hidden { display: none !important; }
    .pm-package-check {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }
    .pm-package-order {
        position: absolute;
        top: 0.45rem;
        right: 0.45rem;
        width: 1.35rem;
        height: 1.35rem;
        border-radius: 999px;
        background: var(--primary-color);
        color: #fff;
        font-size: 0.75rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }
    .pm-package-order[hidden] { display: none; }
    .pm-package-option-copy { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; width: 100%; }
    .pm-package-option-name {
        font-size: 0.875rem;
        font-weight: 600;
        color: #111827;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .pm-package-option-meta {
        font-size: 0.75rem;
        color: #6b7280;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .pm-package-empty { margin: 0; font-size: 0.8125rem; color: #6b7280; }
    .pm-run-order {
        padding: 0.875rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        background: #fff;
    }
    .pm-run-order-title { margin: 0 0 0.5rem; font-size: 0.8125rem; font-weight: 700; color: #111827; }
    .pm-run-order-list { margin: 0; padding-left: 1.25rem; list-style: decimal; }
    .pm-run-order-list li { font-size: 0.8125rem; color: #374151; }
    .pm-run-order-list li + li { margin-top: 0.35rem; }
    .pm-row-action-danger { border-color: #fecaca; color: #dc2626; background: #fff; }
    .pm-row-action-danger:hover { background: #dc2626; border-color: #dc2626; color: #fff; }
</style>
