<style>
    body:has(.billing-public-shell) .main-content {
        margin: 0 !important;
        padding: 0 !important;
        min-height: 100vh;
        max-width: none;
    }

    .billing-public-shell {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        font-family: var(--font-primary), 'Outfit', system-ui, -apple-system, sans-serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
        background:
            radial-gradient(circle at top right, rgba(255, 74, 23, 0.06), transparent 42%),
            radial-gradient(circle at bottom left, rgba(69, 123, 157, 0.08), transparent 38%),
            #eef1f6;
    }

    .billing-public-container {
        width: 100%;
        max-width: 1140px;
        margin: 0 auto;
        padding-left: 1.25rem;
        padding-right: 1.25rem;
    }

    /* ── Top brand bar ── */
    .billing-public-header {
        background: linear-gradient(135deg, #1e2130 0%, #252836 48%, #2a3044 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        box-shadow: 0 4px 24px rgba(15, 23, 42, 0.18);
    }

    .billing-public-header-inner {
        min-height: 72px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding-top: 0.875rem;
        padding-bottom: 0.875rem;
    }

    .billing-brand {
        display: inline-flex;
        align-items: center;
        gap: 0.875rem;
        text-decoration: none;
    }

    .billing-brand-mark {
        width: 44px;
        height: 44px;
        flex-shrink: 0;
        border-radius: 0.625rem;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .billing-brand-mark img {
        width: 32px;
        height: 32px;
        object-fit: contain;
    }

    .billing-brand-name {
        color: #fff;
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.15;
        letter-spacing: -0.01em;
    }

    .billing-brand-tagline {
        color: rgba(255, 255, 255, 0.58);
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-top: 0.2rem;
    }

    .billing-whatsapp-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #ecfdf5;
        background: rgba(37, 211, 102, 0.16);
        border: 1px solid rgba(37, 211, 102, 0.42);
        border-radius: 9999px;
        text-decoration: none;
        transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
        white-space: nowrap;
    }

    .billing-whatsapp-badge:hover {
        color: #fff;
        background: rgba(37, 211, 102, 0.28);
        border-color: rgba(37, 211, 102, 0.65);
        transform: translateY(-1px);
    }

    .billing-whatsapp-icon {
        width: 1.125rem;
        height: 1.125rem;
        flex-shrink: 0;
        color: #25d366;
    }

    /* ── Main report document ── */
    .billing-public-main {
        flex: 1;
        padding: 1.75rem 0 2.5rem;
    }

    .billing-report-document {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.04),
            0 12px 40px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }

    .billing-doc-accent {
        height: 4px;
        background: linear-gradient(90deg, var(--primary-color) 0%, #ff7a4d 55%, #457b9d 100%);
    }

    .billing-doc-header {
        padding: 1.75rem 1.75rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    @media (min-width: 768px) {
        .billing-doc-header {
            flex-direction: row;
            align-items: flex-start;
            justify-content: space-between;
        }
    }

    .billing-doc-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        color: var(--primary-color);
        margin-bottom: 0.5rem;
    }

    .billing-client-name {
        font-size: clamp(1.375rem, 3vw, 2rem);
        font-weight: 700;
        color: #0f172a;
        line-height: 1.25;
        letter-spacing: -0.025em;
        margin: 0;
    }

    .billing-client-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.75rem;
        margin-top: 0.625rem;
        font-size: 0.9375rem;
        font-weight: 500;
        color: #64748b;
    }

    .billing-doc-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem 1.25rem;
        margin-top: 1.125rem;
        padding-top: 1.125rem;
        border-top: 1px dashed #e2e8f0;
    }

    @media (min-width: 640px) {
        .billing-doc-meta-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .billing-doc-meta-item label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        color: #94a3b8;
        margin-bottom: 0.25rem;
    }

    .billing-doc-meta-item span {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #1e293b;
        letter-spacing: -0.01em;
    }

    .billing-doc-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.625rem;
        flex-shrink: 0;
    }

    .billing-filter-panel {
        margin: 1.5rem 1.5rem 1.25rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.875rem;
        overflow: hidden;
    }

    .billing-filter-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.875rem 1.125rem;
        cursor: pointer;
        list-style: none;
        user-select: none;
    }

    .billing-filter-summary::-webkit-details-marker {
        display: none;
    }

    .billing-filter-summary-left {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .billing-filter-summary-left .material-symbols-outlined {
        color: var(--primary-color);
        font-size: 1.125rem !important;
    }

    .billing-filter-summary-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #0f172a;
    }

    .billing-filter-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.5rem;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #c2410c;
        background: rgba(255, 74, 23, 0.12);
        border: 1px solid rgba(255, 74, 23, 0.25);
        border-radius: 9999px;
    }

    .billing-filter-chevron {
        color: #64748b;
        transition: transform 0.2s ease;
    }

    .billing-filter-panel[open] .billing-filter-chevron {
        transform: rotate(180deg);
    }

    .billing-filter-form {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        padding: 0 1.125rem 1rem;
        border-top: 1px solid #e2e8f0;
    }

    .billing-filter-section {
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
        padding-top: 1rem;
    }

    .billing-filter-section-title {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
    }

    .billing-filter-grid {
        display: grid;
        gap: 0.75rem;
        align-items: end;
    }

    .billing-filter-grid-date {
        grid-template-columns: minmax(120px, 0.8fr) minmax(150px, 1fr) auto minmax(140px, 1fr) minmax(140px, 1fr);
    }

    .billing-filter-grid-meta {
        grid-template-columns: minmax(160px, 1fr) minmax(160px, 1fr) auto;
    }

    .billing-filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .billing-filter-group label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #475569;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .billing-filter-input {
        width: 100%;
        min-height: 42px;
        padding: 0.625rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #1e293b;
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 0.5rem;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .billing-filter-input:focus {
        border-color: rgba(255, 74, 23, 0.55);
        box-shadow: 0 0 0 3px rgba(255, 74, 23, 0.12);
    }

    .billing-filter-input:disabled {
        background: #f1f5f9;
        color: #94a3b8;
        border-color: #e2e8f0;
        cursor: not-allowed;
    }

    .billing-filter-divider {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding-bottom: 0.125rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .billing-filter-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .billing-filter-apply,
    .billing-filter-clear {
        min-height: 42px;
    }

    .billing-filter-active {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #334155;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
    }

    .billing-filter-active .material-symbols-outlined {
        color: var(--primary-color);
    }

    .billing-filter-empty-link {
        display: inline-block;
        margin-top: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--primary-color);
        text-decoration: none;
    }

    .billing-filter-empty-link:hover {
        color: #e0410f;
        text-decoration: underline;
    }

    @media (max-width: 960px) {
        .billing-filter-grid-date,
        .billing-filter-grid-meta {
            grid-template-columns: 1fr 1fr;
        }

        .billing-filter-divider {
            grid-column: 1 / -1;
            min-height: auto;
            padding: 0.125rem 0;
        }

        .billing-filter-actions {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 640px) {
        .billing-filter-panel {
            margin-left: 1rem;
            margin-right: 1rem;
            margin-top: 1.25rem;
        }

        .billing-filter-grid-date,
        .billing-filter-grid-meta {
            grid-template-columns: 1fr;
        }
    }

    .billing-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 42px;
        padding: 0.625rem 1.125rem;
        font-size: 0.8125rem;
        font-weight: 600;
        border-radius: 0.5rem;
        text-decoration: none;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        border: none;
        cursor: pointer;
    }

    .billing-btn:hover {
        transform: translateY(-1px);
    }

    .billing-btn-primary {
        color: #fff;
        background: var(--primary-color);
        box-shadow: 0 4px 14px rgba(255, 74, 23, 0.28);
    }

    .billing-btn-primary:hover {
        background: #e0410f;
        color: #fff;
    }

    .billing-btn-secondary {
        color: #334155;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .billing-btn-secondary:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #0f172a;
    }

    /* ── Summary stats ── */
    .billing-doc-body {
        padding: 1.5rem 1.75rem 1.75rem;
    }

    .billing-stats-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    @media (min-width: 640px) {
        .billing-stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    .billing-stat-card {
        position: relative;
        border-radius: 0.875rem;
        padding: 1.125rem 1.25rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        overflow: hidden;
    }

    .billing-stat-card::before {
        content: '';
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
    }

    .billing-stat-card.total::before { background: #3b82f6; }
    .billing-stat-card.paid::before { background: #22c55e; }
    .billing-stat-card.unpaid::before { background: #f59e0b; }

    .billing-stat-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .billing-stat-label {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #64748b;
        letter-spacing: 0.01em;
    }

    .billing-stat-icon {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.625rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .billing-stat-icon .material-symbols-outlined {
        font-size: 1.25rem;
    }

    .billing-stat-card.total .billing-stat-icon {
        background: #eff6ff;
        color: #2563eb;
    }

    .billing-stat-card.paid .billing-stat-icon {
        background: #ecfdf5;
        color: #16a34a;
    }

    .billing-stat-card.unpaid .billing-stat-icon {
        background: #fffbeb;
        color: #d97706;
    }

    .billing-stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.15;
        letter-spacing: -0.025em;
        font-variant-numeric: tabular-nums;
    }

    .billing-stat-card.paid .billing-stat-value { color: #15803d; }
    .billing-stat-card.unpaid .billing-stat-value { color: #b45309; }

    .billing-stat-sub {
        margin-top: 0.35rem;
        font-size: 0.75rem;
        color: #94a3b8;
        font-weight: 500;
    }

    /* ── Progress bar ── */
    .billing-progress-wrap {
        margin-bottom: 1.5rem;
        padding: 1rem 1.125rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
    }

    .billing-progress-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.625rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #475569;
    }

    .billing-progress-track {
        height: 8px;
        background: #e2e8f0;
        border-radius: 9999px;
        overflow: hidden;
    }

    .billing-progress-fill {
        height: 100%;
        border-radius: 9999px;
        background: linear-gradient(90deg, #22c55e, #16a34a);
        transition: width 0.4s ease;
    }

    /* ── Table section ── */
    .billing-table-section {
        border: 1px solid #e2e8f0;
        border-radius: 0.875rem;
        overflow: hidden;
    }

    .billing-table-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        padding: 0.875rem 1.125rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .billing-table-heading-left {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .billing-table-heading h2 {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 700;
        color: #0f172a;
    }

    .billing-table-count {
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 9999px;
        padding: 0.2rem 0.625rem;
    }

    .billing-report-table {
        width: 100%;
        min-width: 720px;
        border-collapse: collapse;
        font-size: 0.9375rem;
        line-height: 1.5;
    }

    .billing-report-table thead tr {
        background: #1e293b;
    }

    .billing-report-table th {
        padding: 0.8125rem 1rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        color: rgba(255, 255, 255, 0.9);
        white-space: nowrap;
    }

    .billing-report-table td {
        padding: 0.9375rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        color: #475569;
        vertical-align: middle;
    }

    .billing-report-table tbody tr:last-child td {
        border-bottom: none;
    }

    .billing-report-table tbody tr:hover {
        background: #f8fafc;
    }

    .billing-report-table tbody tr:nth-child(even) {
        background: #fcfdfe;
    }

    .billing-report-table tbody tr:nth-child(even):hover {
        background: #f8fafc;
    }

    .billing-row-index {
        font-size: 0.8125rem;
        font-weight: 500;
        color: #94a3b8;
        font-variant-numeric: tabular-nums;
    }

    .billing-campaign-no {
        display: inline-block;
        font-family: inherit;
        font-weight: 600;
        font-size: 0.9375rem;
        letter-spacing: -0.015em;
        color: #0f172a;
        line-height: 1.45;
        word-break: break-word;
    }

    .billing-campaign-no.is-system-id {
        font-weight: 500;
        font-size: 0.8125rem;
        letter-spacing: 0;
        color: #475569;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.375rem;
        padding: 0.25rem 0.5rem;
        font-variant-numeric: tabular-nums;
    }

    .billing-date {
        font-size: 0.8125rem;
        font-weight: 500;
        color: #64748b;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .billing-date time {
        font: inherit;
        color: inherit;
    }

    .billing-type-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.3rem 0.65rem;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        border-radius: 9999px;
        white-space: nowrap;
    }

    .billing-type-badge.type-post { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .billing-type-badge.type-sidebar { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .billing-type-badge.type-hidden { background: #fdf2f8; color: #be185d; border: 1px solid #fbcfe8; }
    .billing-type-badge.type-schedule { background: #ecfeff; color: #0e7490; border: 1px solid #a5f3fc; }
    .billing-type-badge.type-sticky { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .billing-type-badge.type-default { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    .billing-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.7rem;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        border-radius: 9999px;
    }

    .billing-status-badge.paid {
        color: #166534;
        background: #dcfce7;
        border: 1px solid #bbf7d0;
    }

    .billing-status-badge.unpaid {
        color: #92400e;
        background: #fef3c7;
        border: 1px solid #fde68a;
    }

    .billing-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .billing-amount {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.01em;
    }

    .billing-report-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        min-height: 34px;
        padding: 0.4375rem 0.875rem;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1;
        border-radius: 0.375rem;
        border: 1px solid #dbeafe;
        background: linear-gradient(180deg, #ffffff 0%, #eff6ff 100%);
        color: #1d4ed8;
        text-decoration: none !important;
        box-shadow: 0 1px 2px rgba(29, 78, 216, 0.08);
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease;
        white-space: nowrap;
    }

    .billing-report-link:hover,
    .billing-report-link:focus,
    .billing-report-link:visited {
        text-decoration: none !important;
        color: #1e40af;
    }

    .billing-report-link:hover {
        border-color: #93c5fd;
        background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
        box-shadow: 0 2px 6px rgba(29, 78, 216, 0.14);
    }

    .billing-report-link:active {
        transform: scale(0.98);
    }

    .billing-report-link .material-symbols-outlined {
        font-size: 0.9375rem !important;
        line-height: 1;
    }

    .billing-report-link-unavailable {
        font-size: 0.875rem;
        color: #cbd5e1;
    }

    .billing-empty-state {
        padding: 3.5rem 1.5rem;
        text-align: center;
        color: #64748b;
    }

    .billing-empty-state .material-symbols-outlined {
        font-size: 2.75rem;
        color: #cbd5e1;
        margin-bottom: 0.75rem;
    }

    .billing-empty-state p {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 500;
    }

    /* ── Document footer ── */
    .billing-doc-footer {
        padding: 1rem 1.75rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        font-size: 0.75rem;
        color: #64748b;
    }

    .billing-doc-footer-brand {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: #334155;
    }

    .billing-doc-footer-brand img {
        width: 18px;
        height: 18px;
        border-radius: 0.25rem;
    }

    /* ── Page footer ── */
    .billing-public-footer {
        padding: 1rem 0 1.25rem;
        text-align: center;
        font-size: 0.75rem;
        color: #94a3b8;
    }

    @media (max-width: 639px) {
        .billing-public-container {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .billing-doc-header,
        .billing-doc-body {
            padding-left: 1.125rem;
            padding-right: 1.125rem;
        }

        .billing-doc-actions {
            width: 100%;
        }

        .billing-btn {
            flex: 1;
            min-width: calc(50% - 0.3125rem);
        }

        .billing-desktop-table {
            display: none;
        }

        .billing-mobile-cards {
            display: flex;
        }
    }

    .billing-desktop-table {
        display: block;
    }

    .billing-mobile-cards {
        display: none;
        flex-direction: column;
        gap: 0.75rem;
        padding: 0.875rem 1.125rem 1.125rem;
    }

    .billing-mobile-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        background: #fff;
        padding: 0.875rem 1rem;
    }

    .billing-mobile-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .billing-mobile-card-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.625rem;
        font-size: 0.8125rem;
    }

    .billing-mobile-card-grid label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 0.125rem;
    }

    .billing-mobile-card-grid span {
        color: #334155;
        font-weight: 500;
    }

    .billing-whatsapp-badge {
        max-width: 100%;
    }

    .billing-whatsapp-badge span {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .billing-pagination {
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
    }

    .billing-pagination nav[role="navigation"] {
        width: 100%;
    }
</style>
