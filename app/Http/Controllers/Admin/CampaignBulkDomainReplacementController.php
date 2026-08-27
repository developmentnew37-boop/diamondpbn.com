<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesAdminCampaign;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkReplaceCampaignDomainsRequest;
use App\Models\Admin\Campaign;
use App\Services\CampaignBulkDomainReplacementService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CampaignBulkDomainReplacementController extends Controller
{
    use AuthorizesAdminCampaign;

    public function __construct(
        private readonly CampaignBulkDomainReplacementService $bulkReplacementService,
    ) {}

    public function create(Request $request, Campaign $campaign)
    {
        $this->authorizeCampaignAccess($campaign);

        $admin = auth('admin')->user();
        $failedPrefill = implode("\n", $this->bulkReplacementService->failedDomainNamesForCampaign($campaign));

        $failedLines = $this->bulkReplacementService->parseLines(
            (string) old('failed_domains', $failedPrefill)
        );
        $replacementLines = $this->bulkReplacementService->parseLines(
            (string) old('replacement_domains', '')
        );

        $preview = [];

        if ($failedLines !== [] && $replacementLines !== []) {
            try {
                $preview = $this->bulkReplacementService->buildPreview(
                    $campaign,
                    $admin,
                    $failedLines,
                    $replacementLines,
                );
            } catch (ValidationException) {
                // Validation errors are shown via $errors from redirect/back.
            }
        }

        return view('admin.campaigns.bulk-replace-domains', [
            'campaign' => $campaign,
            'failedPrefill' => $failedPrefill,
            'preview' => $preview,
            'hasReplaceableItems' => $this->bulkReplacementService->campaignHasReplaceablePosts($campaign),
            'backUrl' => route('admin.campaign.show', $campaign->id),
            'storeRoute' => route('admin.campaign.bulk-domain-replacement.store', $campaign),
            'itemLabel' => 'post',
            'itemLabelPlural' => 'post(s)',
        ]);
    }

    public function store(BulkReplaceCampaignDomainsRequest $request, Campaign $campaign)
    {
        $this->authorizeCampaignAccess($campaign);

        $admin = auth('admin')->user();
        $failedLines = $this->bulkReplacementService->parseLines((string) $request->input('failed_domains', ''));
        $replacementLines = $this->bulkReplacementService->parseLines((string) $request->input('replacement_domains', ''));

        if ($replacementLines === []) {
            throw ValidationException::withMessages([
                'replacement_domains' => 'Enter at least one replacement domain.',
            ]);
        }

        $result = $this->bulkReplacementService->execute(
            $campaign,
            $admin,
            $failedLines,
            $replacementLines,
            (string) ($request->input('reason') ?? ''),
        );

        $message = $result->summaryMessage();
        $flashKey = $result->domainsReplaced > 0
            ? ($result->hasFailures() ? 'cus__error' : 'cus__success')
            : 'cus__error';

        return redirect()
            ->route('admin.campaign.show', $campaign->id)
            ->with($flashKey, $message);
    }
}
