{{-- Shared Convert post campaign wizard UI (buttons, inputs, panels) --}}
<style>
    .convert-wizard-field-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .convert-wizard-input {
        width: 100%;
        min-height: 44px;
        padding: 0.625rem 0.75rem;
        font-size: 0.875rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        background: #f9fafb;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .convert-wizard-input:focus {
        border-color: var(--primary-color, #ff4a17);
        box-shadow: 0 0 0 3px rgba(255, 74, 23, 0.12);
        background: #fff;
    }

    .convert-wizard-summary {
        padding: 0.875rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        background: linear-gradient(135deg, #fff7ed 0%, #fff 55%, #f8fafc 100%);
        font-size: 0.875rem;
        color: #475569;
    }

    .convert-wizard-summary strong {
        color: #111827;
    }

    .convert-wizard-btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-height: 44px;
        padding: 0.625rem 1.35rem;
        font-size: 0.9375rem;
        font-weight: 500;
        color: #fff;
        background: var(--primary-color, #ff4a17);
        border: 1px solid transparent;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: opacity 0.15s, background-color 0.15s;
    }

    .convert-wizard-btn-primary:hover {
        opacity: 0.92;
    }

    .convert-wizard-btn-primary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .convert-wizard-btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-height: 44px;
        padding: 0.625rem 1.25rem;
        font-size: 0.9375rem;
        font-weight: 500;
        color: #374151;
        text-decoration: none;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: background-color 0.15s, border-color 0.15s;
    }

    .convert-wizard-btn-secondary:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
        color: #111827;
    }

    .convert-wizard-btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        min-height: 42px;
        padding: 0.5rem 1.15rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #fff;
        background: #2563eb;
        border: 1px solid #1d4ed8;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: background-color 0.15s;
    }

    .convert-wizard-btn-action:hover {
        background: #1d4ed8;
    }

    .convert-wizard-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 1.5rem;
        padding-top: 1.25rem;
        border-top: 1px solid #e5e7eb;
    }

    .convert-wizard-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        overflow: hidden;
    }

    .convert-wizard-table thead th {
        background: #1f2937;
        color: #fff;
        font-weight: 500;
        text-align: left;
        padding: 0.65rem 0.75rem;
    }

    .convert-wizard-table tbody td {
        padding: 0.65rem 0.75rem;
        border-top: 1px solid #e5e7eb;
        background: #fff;
    }

    .convert-wizard-table tbody tr:hover td {
        background: #f9fafb;
    }

    .convert-wizard-table .qty-input {
        width: 5rem;
        min-height: 36px;
        padding: 0.35rem 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 0.25rem;
        font-size: 0.875rem;
        text-align: center;
    }

    .convert-wizard-table .qty-input:focus {
        outline: none;
        border-color: var(--primary-color, #ff4a17);
    }

    .convert-wizard-hint {
        font-size: 0.8125rem;
        color: #b45309;
    }

    .convert-wizard-section {
        max-width: 42rem;
    }

    .convert-primary-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0.625rem 1.35rem;
        font-size: 0.9375rem;
        font-weight: 500;
        color: #fff;
        background: var(--primary-color, #ff4a17);
        border: 0;
        border-radius: 0.375rem;
        cursor: pointer;
    }

    .convert-search-input {
        width: 100%;
        min-height: 44px;
        padding: 0.625rem 0.75rem;
        font-size: 0.875rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        background: #f9fafb;
    }

    .page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0.625rem 1.25rem;
        font-size: 0.9375rem;
        font-weight: 500;
        color: #374151;
        text-decoration: none;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        cursor: pointer;
        transition: background-color 0.15s, border-color 0.15s;
    }

    .page-btn:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
</style>
