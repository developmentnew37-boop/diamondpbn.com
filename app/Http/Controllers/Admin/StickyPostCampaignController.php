<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Domain;
use Illuminate\Http\Request;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\ArticleSet;
use App\Models\Admin\DomainSet;
use App\Models\Admin\Campaign;
use Illuminate\Support\Facades\Auth;

class StickyPostCampaignController extends Controller
{


    public function index(Request $request)
    {
        // ✅ Validate inputs
        $request->validate([
            'search' => 'nullable|string|max:150',
        ]);

        // ✅ Clean empty search from URL
        if ($request->has('search') && trim($request->search) === '') {
            return redirect()->to(
                url()->current() . '?' . http_build_query(
                    $request->except('search')
                )
            );
        }

        // ✅ Pagination limit
        $limit = 20;

        // ✅ Base query
        $query = Campaign::query()
            ->where('is_sticky_campaign', true);

        // 🔍 Search by campaign_no
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where('campaign_no', 'LIKE', $search);
        }
        $admin = Auth::guard('admin')->user();
        if (!$admin->isSuperAdmin()) {
            $query->where('admin_id', $admin->id);
        }
        // ✅ Paginate + keep query params
        $campaigns = $query
            ->orderByDesc('id')
            ->paginate($limit)
            ->appends($request->all());

        // ✅ Offset (for serial numbers in table)
        $offset = ($campaigns->currentPage() - 1) * $limit;

        return view('admin.campaigns.pbn-post.campaign', compact('campaigns', 'offset'));
    }

    public function create()
    {
        $campaignId = 'STK-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
        // // domain + article category
        $domainCategory = DomainCategory::all();
        $articleCategory =  ArticleCategory::all();
        // // ** now fetching the user the articles ** //
        $articleSet = ArticleSet::withCount('articles')->where('admin_id', auth('admin')->id())->get();
        // ** now providing user domain sets
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();
        $is_sticky = 1;
        return view('admin.campaigns.pbn-post.create-campaign', compact('campaignId', 'domainCategory', 'articleCategory', 'articleSet', 'domainSets', 'is_sticky'));
    }
}
