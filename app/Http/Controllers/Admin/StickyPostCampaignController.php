<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AppliesCampaignListStatusFilter;
use App\Http\Controllers\Admin\Concerns\AppliesSuperAdminCampaignOwnerFilter;
use App\Http\Controllers\Admin\Concerns\ProvidesLocalClientsForForms;
use App\Http\Controllers\Controller;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\ArticleLanguage;
use App\Models\Admin\ArticleSet;
use App\Models\Admin\Campaign;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use App\Services\CampaignBulkDomainReplacementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StickyPostCampaignController extends Controller
{
    use AppliesCampaignListStatusFilter;
    use AppliesSuperAdminCampaignOwnerFilter;
    use ProvidesLocalClientsForForms;

    public function __construct()
    {
        $this->middleware('can.create.campaigns');
    }

    public function index(Request $request)
    {
        // ✅ Validate inputs
        $request->validate([
            'search' => 'nullable|string|max:150',
            'filter_user' => 'nullable|string|max:20',
            'status' => $this->campaignListStatusValidationRule(),
        ]);

        // ✅ Clean empty search from URL
        if ($request->has('search') && trim($request->search) === '') {
            return redirect()->to(
                url()->current().'?'.http_build_query(
                    $request->except('search')
                )
            );
        }

        $limit = config('campaign.pagination.default_limit');

        // ✅ Base query
        $query = Campaign::query()
            ->with('domainCategory:id,name')
            ->where('is_sticky_campaign', true);

        // 🔍 Search by campaign_no
        if ($request->filled('search')) {
            $search = '%'.trim($request->search).'%';
            $query->where('campaign_no', 'LIKE', $search);
        }

        $this->applyCampaignListStatusFilter($query, $request);

        $admin = Auth::guard('admin')->user();
        $ownerData = $this->scopeCampaignQueryForOwner($query, $request, $admin);
        // ✅ Paginate + keep query params
        $campaigns = $query
            ->orderByDesc('id')
            ->paginate($limit)
            ->appends($request->all());

        // ✅ Offset (for serial numbers in table)
        $offset = ($campaigns->currentPage() - 1) * $limit;

        $replaceableCampaignIds = [];
        if ($admin->canCreateCampaigns()) {
            $replaceableCampaignIds = app(CampaignBulkDomainReplacementService::class)
                ->replaceableCampaignIds($campaigns->pluck('id')->all());
        }

        return view(
            'admin.campaigns.pbn-post.campaign',
            array_merge(
                compact('campaigns', 'offset', 'replaceableCampaignIds'),
                $ownerData,
                ['isStickyCampaignList' => true]
            )
        );
    }

    public function create()
    {
        $campaignId = 'STK-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
        // // domain + article category
        $domainCategory = DomainCategory::all();
        $articleCategory = ArticleCategory::all();
        // // ** now fetching the user the articles ** //
        $articleSet = ArticleSet::withCount('articles')->where('admin_id', auth('admin')->id())->get();
        // ** now providing user domain sets
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();
        $articleLanguages = ArticleLanguage::withCount(['Article' => function ($query) {
            $query->where('status', 0)
                ->whereNull('deleted_at')
                ->whereNull('lock_at')
                ->where('status', '!=', '1');
        }])->having('article_count', '>', 0)->get();
        $is_sticky = 1;

        return view('admin.campaigns.pbn-post.create-campaign', compact('campaignId', 'domainCategory', 'articleCategory', 'articleSet', 'domainSets', 'articleLanguages', 'is_sticky'))
            ->with('localClients', $this->activeLocalClientsForForms());
    }
}
