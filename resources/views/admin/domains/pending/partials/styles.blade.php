<style>
    .pd-stat-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    @media (min-width: 1024px) {
        .pd-stat-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    .pd-stat-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.625rem;
        padding: 1rem 1.125rem;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        min-height: 5.5rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .pd-stat-label {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .pd-stat-value {
        font-size: 1.625rem;
        font-weight: 700;
        line-height: 1.1;
        color: #111827;
    }

    .pd-stat-hint {
        font-size: 0.75rem;
        color: #9ca3af;
    }

    .pd-stat-card.is-accent {
        border-color: rgba(255, 74, 23, 0.35);
        background: linear-gradient(135deg, rgba(255, 74, 23, 0.06) 0%, #fff 100%);
    }

    .pd-stat-card.is-accent .pd-stat-value {
        color: var(--primary-color);
    }

    .pd-header-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-start;
        gap: 0.5rem;
    }

    @media (min-width: 768px) {
        .pd-header-actions {
            justify-content: flex-end;
        }
    }

    .pd-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 42px;
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.25;
        border-radius: 0.5rem;
        white-space: nowrap;
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        text-decoration: none;
        border: 1px solid transparent;
        cursor: pointer;
    }

    .pd-btn-primary {
        background-color: var(--primary-color);
        color: #fff;
        box-shadow: 0 1px 2px rgba(255, 74, 23, 0.25);
    }

    .pd-btn-primary:hover {
        background-color: #e0410f;
        color: #fff;
    }

    .pd-btn-outline {
        background: #fff;
        color: #374151;
        border-color: #d1d5db;
    }

    .pd-btn-outline:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
        background: #fff7f5;
    }

    .pd-btn-outline.is-brand {
        color: var(--primary-color);
        border-color: rgba(255, 74, 23, 0.45);
    }

    .pd-btn-outline.is-brand:hover {
        background: var(--primary-color);
        color: #fff;
        border-color: var(--primary-color);
    }

    .pd-btn:disabled,
    .pd-btn.is-disabled {
        opacity: 0.55;
        cursor: not-allowed;
        pointer-events: none;
    }

    .pd-btn-sm {
        min-height: 36px;
        padding: 0.45rem 0.75rem;
        font-size: 0.8125rem;
    }

    .pd-callout {
        border: 1px solid #dbeafe;
        background: #f8fafc;
        border-radius: 0.625rem;
        padding: 0.875rem 1rem;
        font-size: 0.875rem;
        color: #475569;
        line-height: 1.5;
    }

    .pd-callout strong {
        color: #0f172a;
    }

    .pd-toolbar {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid #f1f5f9;
    }

    @media (min-width: 768px) {
        .pd-toolbar {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }

    .pd-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .pd-search-wrap {
        position: relative;
        width: 100%;
        max-width: 100%;
    }

    @media (min-width: 768px) {
        .pd-search-wrap {
            max-width: 320px;
        }
    }

    .pd-search-wrap .material-symbols-outlined {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.125rem;
        color: #9ca3af;
        pointer-events: none;
    }

    .pd-search-input {
        width: 100%;
        min-height: 40px;
        padding: 0.5rem 0.875rem 0.5rem 2.5rem;
        font-size: 0.875rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        background: #f9fafb;
        outline: none;
        transition: border-color 0.2s ease, background-color 0.2s ease;
    }

    .pd-search-input:focus {
        border-color: var(--primary-color);
        background: #fff;
    }

    .pd-filter-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    @media (min-width: 640px) {
        .pd-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1024px) {
        .pd-filter-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .pd-field-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 0.35rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .pd-select {
        width: 100%;
        min-height: 40px;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        background: #fff;
        outline: none;
    }

    .pd-select:focus {
        border-color: var(--primary-color);
    }

    .pd-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.55rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        border-radius: 9999px;
        white-space: nowrap;
    }

    .pd-badge-pending { background: #ffedd5; color: #c2410c; }
    .pd-badge-approved { background: #dcfce7; color: #166534; }
    .pd-badge-rejected { background: #fee2e2; color: #991b1b; }
    .pd-badge-new { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
    .pd-badge-viewed { background: #f3f4f6; color: #6b7280; }
    .pd-badge-inventory { background: #dbeafe; color: #1d4ed8; }
    .pd-badge-new-site { background: #f1f5f9; color: #475569; }

    .pd-domain-link {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-weight: 600;
        color: #0f172a;
        text-decoration: none;
        word-break: break-all;
        transition: color 0.2s ease;
    }

    .pd-domain-link:hover {
        color: var(--primary-color);
    }

    .pd-domain-link .material-symbols-outlined {
        font-size: 0.95rem;
        color: #94a3b8;
        flex-shrink: 0;
    }

    .pd-id {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.8125rem;
        color: #64748b;
    }

    .pd-table-wrap {
        overflow-x: auto;
        border: 1px solid #e5e7eb;
        border-radius: 0.625rem;
    }

    .pd-table {
        width: 100%;
        min-width: 920px;
        font-size: 0.875rem;
        text-align: left;
        border-collapse: collapse;
    }

    .pd-table thead {
        background: #1e293b;
        color: #f8fafc;
    }

    .pd-table thead th {
        padding: 0.75rem 1rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .pd-table tbody td {
        padding: 0.875rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .pd-table tbody tr {
        background: #fff;
        transition: background-color 0.15s ease;
    }

    .pd-table tbody tr:hover {
        background: #f8fafc;
    }

    .pd-table tbody tr.is-unviewed {
        background: #fffbf7;
    }

    .pd-table tbody tr.is-unviewed:hover {
        background: #fff7ed;
    }

    .pd-table tbody tr.is-unviewed td:first-child {
        box-shadow: inset 3px 0 0 var(--primary-color);
    }

    .pd-meta {
        font-size: 0.8125rem;
        color: #64748b;
    }

    .pd-action-group {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: wrap;
    }

    .pd-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 0.375rem;
        border: 1px solid transparent;
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        text-decoration: none;
        cursor: pointer;
    }

    .pd-action-btn .material-symbols-outlined {
        font-size: 1.05rem;
    }

    .pd-action-view { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .pd-action-view:hover { background: #059669; color: #fff; }

    .pd-action-transfer { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .pd-action-transfer:hover { background: #2563eb; color: #fff; }

    .pd-action-reject { background: #fffbeb; color: #d97706; border-color: #fde68a; }
    .pd-action-reject:hover { background: #d97706; color: #fff; }

    .pd-action-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .pd-action-delete:hover { background: #dc2626; color: #fff; }

    .pd-mobile-card {
        border: 1px solid #e5e7eb;
        border-radius: 0.625rem;
        background: #fff;
        padding: 1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .pd-mobile-card.is-unviewed {
        border-color: #fed7aa;
        background: #fffbf7;
        box-shadow: inset 3px 0 0 var(--primary-color);
    }

    .pd-selected-count {
        font-size: 0.8125rem;
        font-weight: 500;
        color: #64748b;
        padding: 0.35rem 0.65rem;
        background: #f1f5f9;
        border-radius: 9999px;
    }

    .pd-empty {
        text-align: center;
        padding: 2.5rem 1.5rem;
        color: #64748b;
    }

    .pd-empty-icon {
        width: 3rem;
        height: 3rem;
        margin: 0 auto 0.75rem;
        border-radius: 9999px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
    }

    .theme-badge {
        background-color: var(--primary-color);
        color: #fff;
    }

    /* Detail page */
    .pd-detail-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    @media (min-width: 1024px) {
        .pd-detail-grid {
            grid-template-columns: minmax(0, 1.65fr) minmax(280px, 1fr);
            align-items: start;
        }
    }

    .pd-detail-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .pd-detail-card.is-primary {
        border-top: 3px solid var(--primary-color);
    }

    .pd-detail-card-title {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 1rem;
    }

    .pd-detail-hero {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .pd-detail-hero-domain {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        word-break: break-all;
    }

    .pd-detail-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin-top: 0.5rem;
    }

    .pd-detail-dl {
        display: grid;
        gap: 0;
    }

    .pd-detail-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.35rem;
        padding: 0.875rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    @media (min-width: 640px) {
        .pd-detail-row {
            grid-template-columns: 9rem minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }
    }

    .pd-detail-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .pd-detail-dt {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
    }

    .pd-detail-dd {
        font-size: 0.875rem;
        color: #1e293b;
        min-width: 0;
    }

    .pd-api-key-box {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    @media (min-width: 640px) {
        .pd-api-key-box {
            flex-direction: row;
            align-items: stretch;
        }
    }

    .pd-api-key-value {
        flex: 1;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.8125rem;
        line-height: 1.5;
        padding: 0.65rem 0.75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        word-break: break-all;
        color: #334155;
    }

    .pd-copy-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-height: 40px;
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #475569;
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .pd-copy-btn:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    .pd-copy-btn.is-success {
        background: #059669;
        border-color: #059669;
        color: #fff;
    }

    .pd-action-stack {
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
    }

    .pd-action-stack .pd-btn {
        width: 100%;
        justify-content: center;
    }

    .pd-action-stack .pd-btn-success {
        background: #059669;
        color: #fff;
        border: none;
        box-shadow: 0 1px 2px rgba(5, 150, 105, 0.25);
    }

    .pd-action-stack .pd-btn-success:hover {
        background: #047857;
        color: #fff;
    }

    .pd-action-stack .pd-btn-danger {
        background: #fff;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    .pd-action-stack .pd-btn-danger:hover {
        background: #dc2626;
        color: #fff;
        border-color: #dc2626;
    }

    .pd-guide-block {
        border: 1px solid #e2e8f0;
        border-radius: 0.625rem;
        padding: 0.875rem 1rem;
        background: #f8fafc;
    }

    .pd-guide-block + .pd-guide-block {
        margin-top: 0.75rem;
    }

    .pd-guide-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }

    .pd-guide-title .material-symbols-outlined {
        font-size: 1.1rem;
        color: var(--primary-color);
    }

    .pd-guide-list {
        margin: 0;
        padding-left: 1.15rem;
        font-size: 0.8125rem;
        color: #475569;
        line-height: 1.55;
    }

    .pd-guide-list li + li {
        margin-top: 0.25rem;
    }

    .pd-status-banner {
        border-radius: 0.625rem;
        padding: 0.875rem 1rem;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .pd-status-banner.is-success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }

    .pd-status-banner.is-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
</style>
