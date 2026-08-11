{{-- Live → Dripfeed list: small helpers on top of dashboard campaign-list-table styles --}}
<style>
    .converted-dripfeed-table .converted-campaign-primary {
        display: block;
        max-width: 15rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
        color: #111827;
    }

    .converted-dripfeed-table .converted-campaign-source {
        display: block;
        margin-top: 0.2rem;
        max-width: 15rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.75rem;
        color: #6b7280;
    }

    .converted-dripfeed-table .converted-campaign-source a {
        color: var(--primary-color);
        text-decoration: none;
    }

    .converted-dripfeed-table .converted-campaign-source a:hover {
        text-decoration: underline;
    }

    .converted-dripfeed-table .converted-pipeline-hint {
        display: block;
        font-size: 0.6875rem;
        color: #9ca3af;
        margin-top: 0.15rem;
    }
</style>
