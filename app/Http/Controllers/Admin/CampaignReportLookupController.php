<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FindCampaignByKeywordUrlRequest;
use App\Http\Requests\Admin\FindCampaignByReportUrlRequest;
use App\Services\CampaignKeywordUrlLookupService;
use App\Services\CampaignReportUrlResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CampaignReportLookupController extends Controller
{
    public function index(): View
    {
        return view('admin.reports.find-campaign');
    }

    public function find(
        FindCampaignByReportUrlRequest $request,
        CampaignReportUrlResolver $resolver
    ): View {
        $result = $resolver->resolve(
            $request->validated('report_url'),
            $request,
            Auth::guard('admin')->user()
        );

        return view('admin.reports.find-campaign', [
            'result' => $result,
            'lookupFailed' => $result === null,
        ]);
    }

    public function findByKeywordUrl(
        FindCampaignByKeywordUrlRequest $request,
        CampaignKeywordUrlLookupService $lookup
    ): View {
        $keywordUrl = trim((string) $request->validated('keyword_url'));
        $results = $lookup->search($keywordUrl, Auth::guard('admin')->user());

        return view('admin.reports.find-campaign', [
            'keywordUrlInput' => $keywordUrl,
            'keywordResults' => $results,
            'keywordLookupFailed' => $results === [],
        ]);
    }
}
