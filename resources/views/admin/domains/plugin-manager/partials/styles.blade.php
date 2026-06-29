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
</style>
