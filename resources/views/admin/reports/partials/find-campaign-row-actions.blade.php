@include('admin.campaigns.partials.campaign-list-actions', [
    'viewUrl' => $row['manage_url'],
    'editUrl' => $row['bulk_edit_url'] ?? null,
    'reportUrl' => $row['report_url'] ?? null,
    'bulkReplaceUrl' => $row['bulk_replace_url'] ?? null,
    'openReportInNewTab' => true,
    'destroyAction' => $row['destroy_action'] ?? null,
    'destroyMethod' => $row['destroy_method'] ?? 'DELETE',
    'destroyHiddenInputs' => $row['destroy_hidden_inputs'] ?? [],
    'destroyConfirm' => $row['destroy_confirm'] ?? 'Delete this campaign? This cannot be undone.',
])
