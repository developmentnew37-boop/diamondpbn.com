<style>
    .lc-section-heading {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        padding-bottom: 1rem;
        margin-bottom: 1.25rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .lc-section-heading h3 {
        font-size: 1.0625rem;
        font-weight: 600;
        color: #111827;
        margin: 0;
    }

    .lc-theme-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 44px;
        padding: 0.625rem 1.25rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #fff;
        border-radius: 0.25rem;
        background-color: var(--primary-color);
        transition: background-color 0.3s ease;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }

    .lc-theme-btn:hover {
        background-color: #e0410f;
        color: #fff;
    }

    .lc-btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 44px;
        padding: 0.625rem 1.25rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
        border-radius: 0.25rem;
        background-color: #f9fafb;
        border: 1px solid #e5e7eb;
        transition: background-color 0.2s ease, border-color 0.2s ease;
        text-decoration: none;
        cursor: pointer;
    }

    .lc-btn-secondary:hover {
        background-color: #f3f4f6;
        border-color: #d1d5db;
        color: #111827;
    }

    .lc-btn-danger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 44px;
        padding: 0.625rem 1.25rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #b91c1c;
        border-radius: 0.25rem;
        background-color: #fef2f2;
        border: 1px solid #fecaca;
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        cursor: pointer;
    }

    .lc-btn-danger:hover {
        background-color: #fee2e2;
        border-color: #f87171;
        color: #991b1b;
    }

    .lc-form-label {
        display: flex;
        align-items: center;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
    }

    .lc-form-label.required::after {
        content: '*';
        margin-left: 0.25rem;
        color: var(--primary-color);
    }

    .lc-form-input {
        background-color: #f9fafb;
        border: 1px solid #e5e7eb;
        padding: 0.75rem;
        font-size: 0.875rem;
        width: 100%;
        border-radius: 0.25rem;
        outline: none;
        transition: border-color 0.2s ease;
    }

    .lc-form-input:focus {
        border-color: var(--primary-color);
    }

    .lc-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 9999px;
        white-space: nowrap;
    }

    .lc-status-badge.active {
        color: #166534;
        background-color: #dcfce7;
        border: 1px solid #bbf7d0;
    }

    .lc-status-badge.inactive {
        color: #6b7280;
        background-color: #f3f4f6;
        border: 1px solid #e5e7eb;
    }

    .lc-status-badge.paid {
        color: #166534;
        background-color: #dcfce7;
        border: 1px solid #bbf7d0;
    }

    .lc-status-badge.unpaid {
        color: #92400e;
        background-color: #fef3c7;
        border: 1px solid #fde68a;
    }

    .lc-currency-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #c2410c;
        background-color: rgba(255, 74, 23, 0.12);
        border: 1px solid rgba(255, 74, 23, 0.25);
        border-radius: 9999px;
    }

    .lc-action-link {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--primary-color);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .lc-action-link:hover {
        color: #e0410f;
        text-decoration: underline;
    }

    .lc-chip-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        padding: 0.4375rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 500;
        line-height: 1;
        border-radius: 0.375rem;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #374151;
        cursor: pointer;
        text-decoration: none !important;
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease;
        white-space: nowrap;
    }

    .lc-chip-btn:hover,
    .lc-chip-btn:focus,
    .lc-chip-btn:visited {
        text-decoration: none !important;
    }

    .lc-chip-btn:hover {
        border-color: rgba(255, 74, 23, 0.45);
        color: var(--primary-color);
        background: rgba(255, 74, 23, 0.06);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }

    .lc-chip-btn:active {
        transform: scale(0.98);
    }

    .lc-chip-btn .material-symbols-outlined {
        font-size: 1rem !important;
    }

    .lc-chip-btn.primary {
        border-color: rgba(255, 74, 23, 0.35);
        color: var(--primary-color);
        background: rgba(255, 74, 23, 0.08);
    }

    .lc-chip-btn.primary:hover {
        background: rgba(255, 74, 23, 0.14);
        border-color: rgba(255, 74, 23, 0.55);
    }

    .lc-chip-btn.outline {
        border-color: #d1d5db;
        background: #f9fafb;
    }

    .lc-chip-btn.outline:hover {
        background: #fff;
        border-color: #9ca3af;
        color: #111827;
    }

    .lc-chip-btn.danger {
        border-color: #fecaca;
        color: #b91c1c;
        background: #fef2f2;
    }

    .lc-chip-btn.danger:hover {
        border-color: #fca5a5;
        color: #991b1b;
        background: #fee2e2;
    }

    .lc-chip-btn.is-copied {
        border-color: #86efac;
        color: #166534;
        background: #dcfce7;
        pointer-events: none;
    }

    .lc-table-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        min-height: 36px;
        padding: 0.5rem 0.875rem;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1;
        border-radius: 0.25rem;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #374151;
        cursor: pointer;
        text-decoration: none !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease;
        white-space: nowrap;
    }

    .lc-table-btn:hover,
    .lc-table-btn:focus,
    .lc-table-btn:visited {
        text-decoration: none !important;
    }

    .lc-table-btn:active {
        transform: scale(0.98);
    }

    .lc-table-btn .material-symbols-outlined {
        font-size: 0.9375rem !important;
        line-height: 1;
    }

    .lc-table-btn-view {
        border-color: #d1d5db;
        background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
        color: #1f2937;
    }

    .lc-table-btn-view .material-symbols-outlined {
        color: #6b7280;
        transition: color 0.2s ease;
    }

    .lc-table-btn-view:hover {
        border-color: rgba(255, 74, 23, 0.45);
        background: linear-gradient(180deg, #fff7f4 0%, #ffede6 100%);
        color: var(--primary-color);
        box-shadow: 0 2px 6px rgba(255, 74, 23, 0.12);
    }

    .lc-table-btn-view:hover .material-symbols-outlined {
        color: var(--primary-color);
    }

    .lc-table-btn-view:focus-visible {
        outline: 2px solid rgba(255, 74, 23, 0.35);
        outline-offset: 2px;
    }

    .lc-table-btn-delete {
        border-color: #fecaca;
        background: linear-gradient(180deg, #fff 0%, #fef2f2 100%);
        color: #b91c1c;
    }

    .lc-table-btn-delete .material-symbols-outlined {
        color: #dc2626;
    }

    .lc-table-btn-delete:hover {
        border-color: #f87171;
        background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%);
        color: #991b1b;
        box-shadow: 0 2px 6px rgba(220, 38, 38, 0.12);
    }

    .lc-table-btn-delete:hover .material-symbols-outlined {
        color: #991b1b;
    }

    .lc-report-wrap {
        position: relative;
        margin-top: 0.375rem;
    }

    .lc-report-wrap .lc-report-input {
        margin-top: 0;
        padding-right: 2.25rem;
    }

    .lc-report-copy-icon {
        position: absolute;
        right: 0.375rem;
        top: 50%;
        transform: translateY(-50%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border: none;
        border-radius: 0.25rem;
        background: transparent;
        color: #9ca3af;
        cursor: pointer;
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .lc-report-copy-icon:hover {
        background: rgba(255, 74, 23, 0.1);
        color: var(--primary-color);
    }

    .lc-report-copy-icon.is-copied {
        color: #16a34a;
        background: #dcfce7;
    }

    .lc-toast-stack {
        position: fixed;
        bottom: 1.5rem;
        right: 1.5rem;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
        pointer-events: none;
    }

    .lc-toast {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        min-width: 240px;
        max-width: 360px;
        padding: 0.875rem 1rem;
        border-radius: 0.5rem;
        background: #111827;
        color: #fff;
        font-size: 0.875rem;
        font-weight: 500;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.18);
        opacity: 0;
        transform: translateY(12px);
        transition: opacity 0.25s ease, transform 0.25s ease;
    }

    .lc-toast.is-visible {
        opacity: 1;
        transform: translateY(0);
    }

    .lc-toast.is-success {
        background: #166534;
    }

    .lc-toast.is-error {
        background: #991b1b;
    }

    .lc-toast .material-symbols-outlined {
        font-size: 1.25rem !important;
        flex-shrink: 0;
    }

    .lc-action-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 9999px;
        transition: background-color 0.2s ease;
        border: none;
        cursor: pointer;
    }

    .lc-stat-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        padding: 1rem 1.25rem;
        min-height: 88px;
    }

    .lc-stat-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .lc-stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #111827;
        margin-top: 0.375rem;
    }

    .lc-stat-value.paid { color: #15803d; }
    .lc-stat-value.unpaid { color: #b45309; }

    .lc-matrix-table {
        width: 100%;
        min-width: 720px;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .lc-matrix-table thead tr {
        background: var(--sidebar-bg);
        color: #fff;
    }

    .lc-matrix-table th,
    .lc-matrix-table td {
        border: 1px solid #e5e7eb;
        padding: 0.625rem 0.75rem;
        text-align: left;
    }

    .lc-matrix-table tbody tr:hover {
        background-color: #f9fafb;
    }

    .lc-matrix-table .lc-form-input {
        padding: 0.5rem 0.625rem;
        min-width: 5rem;
    }

    .lc-empty-state {
        padding: 3rem 1.5rem;
        text-align: center;
        color: #6b7280;
    }

    .lc-empty-state .material-symbols-outlined {
        font-size: 2.5rem;
        color: #d1d5db;
        margin-bottom: 0.75rem;
    }

    .lc-help-text {
        font-size: 0.8125rem;
        color: #6b7280;
        line-height: 1.5;
    }

    .lc-report-input {
        background-color: #f9fafb;
        border: 1px solid #e5e7eb;
        padding: 0.5rem 0.625rem;
        font-size: 0.75rem;
        width: 100%;
        border-radius: 0.25rem;
        color: #374151;
        margin-top: 0.375rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .lc-toast-stack {
        max-width: calc(100vw - 2rem);
    }

    .lc-toast {
        min-width: 0;
        max-width: min(360px, calc(100vw - 2rem));
    }

    .lc-desktop-table {
        display: block;
    }

    .lc-mobile-cards {
        display: none;
        flex-direction: column;
        gap: 0.75rem;
    }

    .lc-mobile-card {
        border: 1px solid #e5e7eb;
        border-radius: 0.625rem;
        background: #fff;
        padding: 0.875rem 1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .lc-mobile-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.625rem;
    }

    .lc-mobile-card-title {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #111827;
        word-break: break-word;
    }

    .lc-mobile-card-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem 0.75rem;
        font-size: 0.8125rem;
        color: #64748b;
    }

    .lc-mobile-card-meta label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 0.125rem;
    }

    .lc-mobile-card-meta span {
        color: #334155;
        font-weight: 500;
    }

    .lc-mobile-card-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #f1f5f9;
    }

    @media (max-width: 767px) {
        .lc-desktop-table {
            display: none;
        }

        .lc-mobile-cards {
            display: flex;
        }

        .page-header-actions,
        .lc-stat-card {
            min-width: 0;
        }
    }
</style>
