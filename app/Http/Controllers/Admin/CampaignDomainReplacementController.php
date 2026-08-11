<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplaceCampaignDomainRequest;
use App\Models\Admin\CampaignDomainReplacement;
use App\Models\Admin\CampaignPost;
use App\Services\CampaignDomainReplacementService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class CampaignDomainReplacementController extends Controller
{
    public function __construct(
        private readonly CampaignDomainReplacementService $replacementService,
    ) {}

    public function create(Request $request, CampaignPost $post)
    {
        $post->loadMissing('campaign', 'campaignDomain.domain', 'campaignArticle.article');
        $campaign = $post->campaign;
        abort_unless($campaign, 404);

        $admin = auth('admin')->user();
        $search = $request->string('search')->toString();
        $searchedDomain = null;
        $searchFeedback = null;

        if (trim($search) !== '') {
            $searchedDomain = $this->replacementService->lookupDomain($search);

            if (! $searchedDomain) {
                $searchFeedback = [
                    'type' => 'not_found',
                    'message' => 'No domain named "'.trim($search).'" exists in your inventory. Add it under Domains first.',
                ];
            } else {
                $ineligibility = $this->replacementService->ineligibilityReason(
                    $campaign,
                    $post,
                    $admin,
                    $searchedDomain,
                    manual: true
                );

                $searchFeedback = [
                    'type' => $ineligibility ? 'ineligible' : 'eligible',
                    'message' => $ineligibility
                        ?? 'This domain is eligible. Confirm the name in the replacement field below and submit.',
                    'domain' => $searchedDomain,
                ];
            }
        }

        $oldDomain = $post->campaignDomain?->domain;

        if (! $oldDomain || ($reason = CampaignDomainReplacementService::ineligibleReason($post))) {
            return redirect()
                ->route('admin.campaign.show', $campaign->id)
                ->with('cus__error', $reason ?? 'The current campaign domain is no longer available.');
        }

        return view('admin.campaigns.pbn-post.replace-domain', [
            'campaign' => $campaign,
            'post' => $post,
            'oldDomain' => $oldDomain,
            'search' => $search,
            'searchedDomain' => $searchedDomain,
            'searchFeedback' => $searchFeedback,
            'requestUuid' => (string) Str::uuid(),
        ]);
    }

    public function store(ReplaceCampaignDomainRequest $request, CampaignPost $post)
    {
        $post->loadMissing('campaign');
        $campaign = $post->campaign;
        abort_unless($campaign, 404);

        $admin = auth('admin')->user();

        try {
            $newDomainId = $this->replacementService->resolveReplacementDomainId(
                $campaign,
                $post,
                $admin,
                null,
                $request->input('new_domain_name'),
                manual: true,
            );

            $replacement = $this->replacementService->replace(
                $post,
                $admin,
                $newDomainId,
                (int) $request->validated('expected_old_domain_id'),
                (string) $request->validated('request_uuid'),
                (string) ($request->validated('reason') ?? ''),
                manual: true,
            );
        } catch (\Illuminate\Validation\ValidationException|\Illuminate\Auth\Access\AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            $committed = CampaignDomainReplacement::query()
                ->where('request_uuid', (string) $request->validated('request_uuid'))
                ->whereIn('state', ['dispatch_pending', 'dispatching', 'dispatch_failed', 'completed'])
                ->first();

            if ($committed) {
                return redirect()
                    ->route('admin.campaign.show', $committed->campaign_id ?? $post->campaign_id)
                    ->with(
                        'cus__error',
                        'The domain replacement was applied, but queue dispatch could not be confirmed. '
                        .'Use Retry on the queued post; do not submit a new replacement.'
                    );
            }

            return back()
                ->withInput()
                ->with('cus__error', 'The domain could not be replaced. No campaign post changes were applied.');
        }

        if ($replacement->state !== 'completed') {
            return redirect()
                ->route('admin.campaign.show', $replacement->campaign_id ?? $post->campaign_id)
                ->with(
                    'cus__error',
                    'The domain replacement was applied, but the publish job could not be queued. '
                    .'Use Retry on this queued post to safely dispatch the current generation.'
                );
        }

        return redirect()
            ->route('admin.campaign.show', $replacement->campaign_id ?? $post->campaign_id)
            ->with('cus__success', 'Domain replaced and the post was safely queued for publishing.');
    }
}
