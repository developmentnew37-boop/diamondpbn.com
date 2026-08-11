{{-- Campaign list tables with sticky actions column --}}
<style>
    .campaign-list-table {
        border-collapse: separate;
        border-spacing: 0;
    }

    .campaign-list-table tbody td {
        background-color: #fff;
    }

    .campaign-list-table tbody tr:hover td {
        background-color: #f9fafb;
    }

    .campaign-list-table th.actions-col,
    .campaign-list-table td.actions-col {
        position: sticky;
        right: 0;
        z-index: 5;
        isolation: isolate;
        overflow: hidden;
        background-color: #fff !important;
        border-left: 1px solid #e5e7eb;
        box-shadow: -14px 0 18px -12px rgba(15, 23, 42, 0.28);
        transform: translateZ(0);
        min-width: 252px;
    }

    .campaign-list-table thead th.actions-col {
        z-index: 6;
        background-color: #1f2937 !important;
        border-left-color: #374151;
        box-shadow: -14px 0 18px -12px rgba(0, 0, 0, 0.35);
    }

    .campaign-list-table tbody tr:hover td.actions-col {
        background-color: #f9fafb !important;
    }

    .campaign-list-table .campaign-list-actions-wrap {
        position: relative;
        z-index: 1;
        min-width: 240px;
        background: inherit;
    }

    @media (max-width: 767px) {
        .campaign-list-table th.actions-col,
        .campaign-list-table td.actions-col {
            position: static;
            box-shadow: none;
            transform: none;
            border-left: none;
        }
    }

    .campaign-list-toolbar .campaign-owner-filter-wrap:empty {
        display: none;
    }
</style>
