<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Jobs\CheckDomainStatus;
use App\Models\Admin\Domain;

class DomainController extends Controller
{
    //

    public function bulk_upload(Request $request)
    {
        $request->validate([
            'data'     => 'required|array|min:1',
            'category' => 'required|exists:domain_categories,id'
        ]);

        $admin_id = Auth::guard('admin')->id();

        // You will pass values dynamically when importing
        foreach ($request->data as $item) {

            CheckDomainStatus::dispatch(
                $item['name'],      // or $item if list only contains name
                $request->category,
                $admin_id,
                $item['da'] ?? 0,
                $item['dr'] ?? 0,
                $item['tf'] ?? 0,
                $item['ss'] ?? 0,
                $item['ip'] ?? null,
                $item['api_key'] ?? null
            )->onQueue('domainCheck');
        }

        return response()->json([
            "status" => true,
            "message" => count($request->data) . " domains queued successfully."
        ]);
    }

    // public function domains(string $id)
    // {

    //     // Validate route ID (must be integer)
    //     if (!ctype_digit($id)) {
    //         return response()->json([
    //             "status"  => false,
    //             "message" => "Invalid domain category ID."
    //         ], 422);
    //     }

    //     $domains = Domain::where('domain_category_id', (int) $id)->get();

    //     // Return empty list (NOT an error)
    //     return response()->json([
    //         "status"  => true,
    //         "message" => $domains->isEmpty()
    //             ? "No domains found for this category."
    //             : "Successfully fetched domains.",
    //         "data"    => $domains,
    //         "total"   => $domains->count(),
    //     ], 200);
    // }

    public function domains(Request $request, string $id)
    {
        // Validate route ID
        if (!ctype_digit($id)) {
            return response()->json([
                "status"  => false,
                "message" => "Invalid domain category ID."
            ], 422);
        }

        $perPage = (int) $request->get('per_page', 30);

        $domains = Domain::query()
            ->where('domain_category_id', (int) $id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            "status" => true,
            "data"   => [
                "domains" => $domains
            ]
        ], 200);
    }



    // public function validateDomains(Request $request)
    // {
    //     // 1️⃣ Basic validation
    //     $request->validate([
    //         'domains'   => 'required|array|min:1',
    //         'domains.*' => 'required|string'
    //     ]);

    //     // 2️⃣ Normalize input
    //     $inputDomains = collect($request->domains)
    //         ->map(fn($d) => strtolower(trim($d)))
    //         ->unique()
    //         ->values();

    //     // 3️⃣ Fetch matching domains (ID + name)
    //     $domains = Domain::whereIn('name', $inputDomains->toArray())
    //         ->get(['id', 'name']);

    //     // 4️⃣ Normalize DB names
    //     $existingDomainNames = $domains
    //         ->pluck('name')
    //         ->map(fn($d) => strtolower($d))
    //         ->values();

    //     // 5️⃣ Find missing domains
    //     $missingDomains = $inputDomains
    //         ->diff($existingDomainNames)
    //         ->values();

    //     // 6️⃣ If everything is OK → return IDs
    //     if ($missingDomains->isEmpty()) {
    //         return response()->json([
    //             'status'  => true,
    //             'code'    => 'ALL_DOMAINS_VALID',
    //             'message' => 'All domains are present. Campaign is safe to run.',
    //             'data'    => [
    //                 'total'      => $domains->count(),
    //                 'domain_ids' => $domains->pluck('id')->values(),
    //                 'domains'    => $domains->map(fn($d) => [
    //                     'id'   => $d->id,
    //                     'name' => $d->name
    //                 ])->values(),
    //                 'missing'    => []
    //             ]
    //         ]);
    //     }

    //     // 7️⃣ Some domains missing → STOP campaign
    //     return response()->json([
    //         'status'  => false,
    //         'code'    => 'SOME_DOMAINS_MISSING',
    //         'message' => 'some domains from the provided data (domains) are missing in the database. Campaign cannot proceed.',
    //         'data'    => [
    //             'total'   => $inputDomains->count(),
    //             'valid'   => $domains->pluck('name')->values(),
    //             'missing' => $missingDomains
    //         ]
    //     ], 422);
    // }
    public function validateDomains(Request $request)
    {
        // 1️⃣ Basic validation
        $request->validate([
            'domains'   => 'required|array|min:1',
            'domains.*' => 'required|string'
        ]);

        /**
         * 2️⃣ Normalize input
         * - trim spaces
         * - lowercase
         * - keep FIRST occurrence
         * - KEEP ORIGINAL ORDER
         */
        $inputDomains = collect($request->domains)
            ->map(fn($d) => strtolower(trim($d)))
            ->filter()
            ->unique()      // removes duplicates but keeps first position
            ->values();     // reindex without reordering

        // 3️⃣ Fetch matching domains from DB (ORDER NOT GUARANTEED HERE)
        $domains = Domain::whereIn('name', $inputDomains->toArray())
            ->get(['id', 'name']);

        // 4️⃣ Build lookup map: normalized_name => Domain model
        $domainMap = $domains->keyBy(
            fn($d) => strtolower($d->name)
        );

        // 5️⃣ Rebuild domains in USER INPUT ORDER 🔥
        $orderedDomains = $inputDomains
            ->map(fn($name) => $domainMap[$name] ?? null)
            ->filter()
            ->values();

        // 6️⃣ Detect missing domains
        $existingNames = $orderedDomains
            ->pluck('name')
            ->map(fn($d) => strtolower($d))
            ->values();

        $missingDomains = $inputDomains
            ->diff($existingNames)
            ->values();

        // 7️⃣ If all domains exist → SUCCESS
        if ($missingDomains->isEmpty()) {
            return response()->json([
                'status'  => true,
                'code'    => 'ALL_DOMAINS_VALID',
                'message' => 'All domains are present. Campaign is safe to run.',
                'data'    => [
                    'total'      => $orderedDomains->count(),
                    'domain_ids' => $orderedDomains->pluck('id')->values(),
                    'domains'    => $orderedDomains->map(fn($d) => [
                        'id'   => $d->id,
                        'name' => $d->name
                    ])->values(),
                    'missing'    => []
                ]
            ]);
        }

        // 8️⃣ Some domains missing → BLOCK campaign
        return response()->json([
            'status'  => false,
            'code'    => 'SOME_DOMAINS_MISSING',
            'message' => 'Some domains from the provided list are missing in the database. Campaign cannot proceed.',
            'data'    => [
                'total'   => $inputDomains->count(),
                'valid'   => $existingNames,
                'missing' => $missingDomains
            ]
        ], 422);
    }
}
