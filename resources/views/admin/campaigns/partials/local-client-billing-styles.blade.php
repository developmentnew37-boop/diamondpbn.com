<style>
    .lcb-panel {
        width: 100%;
        margin-bottom: 0;
        padding: 1rem 1.25rem;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        background: linear-gradient(180deg, #fafafa 0%, #ffffff 100%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
    }

    .lcb-panel.is-collapsed {
        display: none;
    }

    .lcb-panel:not(.is-collapsed) {
        margin-top: 0.75rem;
    }

    @media (min-width: 640px) {
        .lcb-panel {
            padding: 1.25rem 1.5rem;
        }
    }

    .lcb-panel-header {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .lcb-panel-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        flex-shrink: 0;
        border-radius: 0.5rem;
        background: rgba(255, 74, 23, 0.1);
        color: var(--primary-color);
    }

    .lcb-panel-icon .material-symbols-outlined {
        font-size: 1.375rem !important;
    }

    .lcb-panel-title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.25rem;
    }

    .lcb-panel-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #111827;
    }

    .lcb-optional-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.5rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #6b7280;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 9999px;
    }

    .lcb-panel-desc {
        margin: 0;
        font-size: 0.8125rem;
        line-height: 1.5;
        color: #6b7280;
        max-width: 52rem;
    }

    .lcb-field {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .lcb-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
    }

    .lcb-select {
        width: 100%;
        padding: 0.75rem;
        font-size: 0.875rem;
        color: #111827;
        background-color: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    .lcb-select:hover {
        border-color: #d1d5db;
        background-color: #ffffff;
    }

    .lcb-select:focus {
        border-color: var(--primary-color);
        background-color: #ffffff;
        box-shadow: 0 0 0 3px rgba(255, 74, 23, 0.12);
    }

    .lcb-estimate {
        margin-top: 1rem;
        padding: 0.875rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid #dbeafe;
        background: #eff6ff;
    }

    .lcb-estimate-total {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .lcb-estimate-label {
        font-size: 0.8125rem;
        font-weight: 500;
        color: #1e40af;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .lcb-estimate-amount {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1e3a8a;
    }

    .lcb-estimate-lines {
        margin: 0.75rem 0 0;
        padding: 0;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .lcb-estimate-line {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.5rem 0.625rem;
        font-size: 0.8125rem;
        color: #374151;
        background: rgba(255, 255, 255, 0.75);
        border: 1px solid #dbeafe;
        border-radius: 0.375rem;
    }

    .lcb-estimate-line-meta {
        color: #6b7280;
    }

    .lcb-estimate-line-price {
        font-weight: 600;
        color: #1e3a8a;
        white-space: nowrap;
    }

    .lcb-error {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding: 0.625rem 0.75rem;
        font-size: 0.8125rem;
        line-height: 1.45;
        color: #b91c1c;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 0.375rem;
    }

    .lcb-error .material-symbols-outlined {
        font-size: 1.125rem !important;
        margin-top: 0.05rem;
    }

    /* Campaign view — billing summary */
    .lcb-view-panel {
        width: 100%;
        margin-bottom: 1.25rem;
        padding: 1rem 1.25rem;
        border-radius: 0.75rem;
        border: 1px solid #e5e7eb;
        background: linear-gradient(180deg, #fafafa 0%, #ffffff 100%);
    }

    @media (min-width: 640px) {
        .lcb-view-panel {
            padding: 1.25rem 1.5rem;
        }
    }

    .lcb-view-top {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .lcb-view-heading {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        min-width: 0;
        flex: 1 1 16rem;
    }

    .lcb-view-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .lcb-btn-invoice {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #374151;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        text-decoration: none;
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    }

    .lcb-btn-invoice:hover {
        background: #f9fafb;
        border-color: #9ca3af;
        color: #111827;
    }

    .lcb-btn-invoice .material-symbols-outlined {
        font-size: 1.125rem !important;
    }

    .lcb-payment-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 9999px;
        white-space: nowrap;
    }

    .lcb-payment-badge.paid {
        color: #166534;
        background: #dcfce7;
        border: 1px solid #bbf7d0;
    }

    .lcb-payment-badge.unpaid {
        color: #92400e;
        background: #fef3c7;
        border: 1px solid #fde68a;
    }

    .lcb-view-stats {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    @media (min-width: 640px) {
        .lcb-view-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .lcb-stat-card {
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .lcb-stat-label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 0.25rem;
    }

    .lcb-stat-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #111827;
        word-break: break-word;
    }

    .lcb-stat-value.total {
        font-size: 1.25rem;
        color: var(--primary-color);
    }

    .lcb-stat-value a {
        color: #2563eb;
        text-decoration: none;
    }

    .lcb-stat-value a:hover {
        text-decoration: underline;
    }

    .lcb-breakdown {
        margin-bottom: 1rem;
    }

    .lcb-breakdown summary {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
        color: #2563eb;
        list-style: none;
        user-select: none;
    }

    .lcb-breakdown summary::-webkit-details-marker {
        display: none;
    }

    .lcb-breakdown summary .material-symbols-outlined {
        font-size: 1.125rem !important;
        transition: transform 0.2s ease;
    }

    .lcb-breakdown[open] summary .material-symbols-outlined {
        transform: rotate(90deg);
    }

    .lcb-breakdown-table-wrap {
        margin-top: 0.75rem;
        overflow-x: auto;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
    }

    .lcb-breakdown-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .lcb-breakdown-table thead {
        background: #1f2937;
        color: #ffffff;
    }

    .lcb-breakdown-table th {
        padding: 0.625rem 0.875rem;
        text-align: left;
        font-weight: 600;
        white-space: nowrap;
    }

    .lcb-breakdown-table td {
        padding: 0.625rem 0.875rem;
        border-top: 1px solid #e5e7eb;
        color: #374151;
        background: #ffffff;
    }

    .lcb-breakdown-table tbody tr:hover td {
        background: #f9fafb;
    }

    .lcb-breakdown-table .price-col {
        font-weight: 600;
        color: #111827;
        white-space: nowrap;
    }

    .lcb-payment-form {
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }

    .lcb-payment-form-title {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
    }

    .lcb-payment-form-title .material-symbols-outlined {
        font-size: 1.125rem !important;
        color: #6b7280;
    }

    .lcb-payment-form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
        align-items: end;
    }

    @media (min-width: 768px) {
        .lcb-payment-form-grid {
            grid-template-columns: minmax(8rem, 10rem) 1fr auto;
        }
    }

    .lcb-input {
        width: 100%;
        padding: 0.625rem 0.75rem;
        font-size: 0.875rem;
        color: #111827;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .lcb-input:focus {
        border-color: var(--primary-color);
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(255, 74, 23, 0.12);
    }

    .lcb-btn-update {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        min-height: 2.5rem;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #ffffff;
        background: var(--primary-color);
        border: none;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .lcb-btn-update:hover {
        background: #e0410f;
    }

    .lcb-view-wrapper {
        width: 100%;
        margin-bottom: 1rem;
    }

    .lcb-view-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem 1rem;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .lcb-view-bar-left {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.75rem;
        min-width: 0;
        flex: 0 1 auto;
    }

    .lcb-view-bar-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        flex-shrink: 0;
        border-radius: 0.375rem;
        background: rgba(255, 74, 23, 0.1);
        color: var(--primary-color);
    }

    .lcb-view-bar-icon .material-symbols-outlined {
        font-size: 1.125rem !important;
    }

    .lcb-view-bar-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: #111827;
        word-break: break-word;
    }

    .lcb-view-bar-meta {
        font-size: 0.8125rem;
        color: #6b7280;
    }

    .lcb-view-bar-meta strong {
        color: #111827;
        font-weight: 600;
    }

    .lcb-view-bar-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
        margin-left: auto;
    }

    .lcb-view-bar-fields {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem 1rem;
        flex: 1 1 16rem;
        min-width: 0;
    }

    .lcb-field-inline {
        flex-direction: row;
        align-items: center;
        gap: 0.5rem;
        flex: 0 1 auto;
        min-width: 0;
    }

    .lcb-view-bar-fields .lcb-field-inline .lcb-label {
        margin: 0;
        font-size: 0.8125rem;
        font-weight: 500;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .lcb-view-bar-fields .lcb-field-inline .lcb-select-compact {
        min-width: 9rem;
        max-width: 100%;
    }

    .lcb-select-compact {
        padding: 0.5rem 0.625rem;
        font-size: 0.8125rem;
        min-height: 2.25rem;
    }

    .lcb-toggle-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        min-height: 2.25rem;
        padding: 0.375rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #374151;
        background: #f9fafb;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    }

    .lcb-toggle-btn:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
        color: #111827;
    }

    .lcb-toggle-btn .material-symbols-outlined {
        font-size: 1.125rem !important;
    }

    .lcb-view-panel.is-collapsed {
        display: none;
    }

    .lcb-view-panel:not(.is-collapsed) {
        margin-top: 0.75rem;
    }
</style>
