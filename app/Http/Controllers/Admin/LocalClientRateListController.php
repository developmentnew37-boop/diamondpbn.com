<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\LocalClientRateList;
use App\Services\LocalClientPriceMatrixService;
use App\Services\LocalClientRateListSeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LocalClientRateListController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:local_clients.manage');
    }

    public function index(Request $request)
    {
        $query = LocalClientRateList::query()->withCount('clients');

        if ($request->filled('search')) {
            $search = '%'.trim($request->string('search')->toString()).'%';
            $query->where('name', 'like', $search);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $rateLists = $query->orderBy('name')->paginate(15)->appends($request->query());

        return view('admin.local-clients.rate-lists.index', compact('rateLists'));
    }

    public function create(LocalClientPriceMatrixService $matrixService)
    {
        return view('admin.local-clients.rate-lists.form', [
            'rateList' => new LocalClientRateList(['is_active' => true]),
            'matrix' => $matrixService->buildGridForRateList(),
            'mode' => 'create',
        ]);
    }

    public function store(
        Request $request,
        LocalClientPriceMatrixService $matrixService,
        LocalClientRateListSeedService $seedService,
    ) {
        $validated = $this->validateRateList($request);
        $matrixService->validateCompleteMatrix($request->input('prices', []));

        $rateList = LocalClientRateList::create([
            ...$validated,
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        $seedService->seedForNewRateList($rateList);
        $matrixService->upsertRateListPrices($rateList, $request->input('prices', []));

        return redirect()
            ->route('admin.local-client-rate-lists.index')
            ->with('cus__success', 'Rate list created successfully.');
    }

    public function edit(LocalClientRateList $localClientRateList, LocalClientPriceMatrixService $matrixService)
    {
        return view('admin.local-clients.rate-lists.form', [
            'rateList' => $localClientRateList,
            'matrix' => $matrixService->buildGridForRateList($localClientRateList),
            'mode' => 'edit',
            'clientsCount' => $localClientRateList->clients()->count(),
        ]);
    }

    public function update(
        Request $request,
        LocalClientRateList $localClientRateList,
        LocalClientPriceMatrixService $matrixService,
    ) {
        $validated = $this->validateRateList($request, $localClientRateList);
        $matrixService->validateCompleteMatrix($request->input('prices', []));
        $localClientRateList->update($validated);
        $matrixService->upsertRateListPrices($localClientRateList, $request->input('prices', []));

        return redirect()
            ->route('admin.local-client-rate-lists.index')
            ->with('cus__success', 'Rate list updated successfully.');
    }

    public function destroy(LocalClientRateList $localClientRateList)
    {
        $clientsCount = $localClientRateList->clients()->count();

        if ($clientsCount > 0) {
            return redirect()
                ->route('admin.local-client-rate-lists.index')
                ->with('cus__error', sprintf(
                    'Cannot delete "%s" — %d client%s %s using it. Reassign those clients first.',
                    $localClientRateList->name,
                    $clientsCount,
                    $clientsCount === 1 ? '' : 's',
                    $clientsCount === 1 ? 'is' : 'are'
                ));
        }

        $name = $localClientRateList->name;
        $localClientRateList->delete();

        return redirect()
            ->route('admin.local-client-rate-lists.index')
            ->with('cus__success', "Rate list \"{$name}\" deleted successfully.");
    }

    /** @return array<string, mixed> */
    private function validateRateList(Request $request, ?LocalClientRateList $rateList = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('local_client_rate_lists', 'name')->ignore($rateList?->id),
            ],
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'nullable|in:0,1',
        ]);

        $validated['is_active'] = filter_var($request->input('is_active', '1'), FILTER_VALIDATE_BOOLEAN);

        return $validated;
    }
}
