{{-- Live → Dripfeed list only — does not alter other campaign list pages --}}
<style>
    .converted-dripfeed-list-page .converted-dripfeed-stats-card {
        margin-bottom: 20px;
    }

    .converted-dripfeed-list-page .converted-dripfeed-table-scroll {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-top: 0.75rem;
    }

    .converted-dripfeed-list-page .campaign-list-table td.actions-col,
    .converted-dripfeed-list-page .campaign-list-table th.actions-col {
        position: sticky;
        right: 0;
        z-index: 5;
        isolation: isolate;
        overflow: visible;
        background-color: #fff !important;
        border-left: 1px solid #e5e7eb;
        box-shadow: -14px 0 18px -12px rgba(15, 23, 42, 0.28);
    }

    .converted-dripfeed-list-page .campaign-list-table thead th.actions-col {
        z-index: 6;
        background-color: #1f2937 !important;
        border-left-color: #374151;
    }

    .converted-dripfeed-list-page .campaign-list-table tbody tr:hover td.actions-col {
        background-color: #f9fafb !important;
    }

    .converted-dripfeed-list-page .campaign-list-table td.actions-col::before {
        content: '';
        position: absolute;
        inset: 0;
        background: inherit;
        z-index: 0;
        pointer-events: none;
    }

    .converted-dripfeed-list-page .campaign-list-table td.actions-col .campaign-list-actions-wrap {
        position: relative;
        z-index: 1;
        background: transparent;
    }

    .converted-dripfeed-list-page .campaign-list-table td:nth-last-child(2) {
        padding-right: 0.75rem;
    }

    @media (max-width: 767px) {
        .converted-dripfeed-list-page .campaign-list-table td.actions-col,
        .converted-dripfeed-list-page .campaign-list-table th.actions-col {
            position: static;
            box-shadow: none;
        }

        .converted-dripfeed-list-page .campaign-list-table td.actions-col::before {
            display: none;
        }
    }
</style>
