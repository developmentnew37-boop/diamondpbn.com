<style>
    .campaign-list-table th.actions-col,
    .campaign-list-table td.actions-col {
        position: sticky;
        right: 0;
        z-index: 2;
        background: #fff;
        box-shadow: -6px 0 10px -8px rgba(15, 23, 42, 0.35);
    }

    .campaign-list-table thead th.actions-col {
        z-index: 3;
        background: #1f2937;
    }

    .campaign-list-table tbody tr:hover td.actions-col {
        background: #f9fafb;
    }

    @media (max-width: 767px) {
        .campaign-list-table th.actions-col,
        .campaign-list-table td.actions-col {
            position: static;
            box-shadow: none;
        }
    }

    .campaign-list-toolbar .campaign-owner-filter-wrap:empty {
        display: none;
    }
</style>
