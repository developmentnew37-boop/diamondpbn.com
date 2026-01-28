<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Domain;
use Illuminate\Database\Eloquent\Attributes\Initialize;
use Illuminate\Http\Request;
use App\Models\Admin\DomainSet;
use Illuminate\Support\Facades\Auth;

class DomainSetController extends Controller
{
    //
    public function initialize(Request $request)
    {
        // Validate request
        $request->validate([
            'name'      => 'required|string|max:255',
            'category'  => 'required|integer|exists:domain_categories,id'
        ]);

        // Check duplicate domain set name
        if (DomainSet::where('name', $request->name)->exists()) {
            return response()->json([
                "status"  => false,
                "message" => "Domain set with this name already exists!"
            ], 409); // conflict status code
        }

        $domains = Domain::where('domain_category_id', $request->category)->get();

        if (count($domains) <= 0) {
            return response()->json([
                "status"  => false,
                "message" =>  "There are no domains related to the selected category"
            ], 409); // conflict status code
        }

        // Create domain set
        $set = DomainSet::create([
            'name'                => $request->name,
            'qty'                 => 0,
            'domains'             => json_encode([]), // Laravel will cast to JSON if cast is set
            'domain_category_id'  => $request->category,
            'admin_id'            => Auth::guard('admin')->id(),
        ]);

        return response()->json([
            "status"  => true,
            "message" => "Domain set initialized successfully",
            "data"    => $set
        ], 201);
    }
    // public function setDomains(string $id)
    // {
    //     if (!ctype_digit($id)) {
    //         return response()->json([
    //             "status"  => false,
    //             "message" => "Invalid domain set ID."
    //         ], 422);
    //     }

    //     $adminId = Auth::guard('admin')->id();

    //     // Secure: only fetch sets belonging to this admin
    //     $set = DomainSet::where('id', (int) $id)
    //         ->where('admin_id', $adminId)
    //         ->first();

    //     if (!$set) {
    //         return response()->json([
    //             "status"  => false,
    //             "message" => "Domain set not found."
    //         ], 404);
    //     }

    //     // Decode JSON safely
    //     $domainIds = json_decode($set->domains, true);

    //     if (!is_array($domainIds)) {
    //         return response()->json([
    //             "status"  => false,
    //             "message" => "Invalid domains JSON stored in this set."
    //         ], 500);
    //     }

    //     // Clean IDs (important)
    //     $domainIds = array_values(array_unique(array_filter(array_map(function ($v) {
    //         return ctype_digit((string)$v) ? (int)$v : null;
    //     }, $domainIds))));

    //     if (count($domainIds) < 1) {
    //         return response()->json([
    //             "status"  => true,
    //             "message" => "This domain set contains no domains.",
    //             "data"    => [],
    //             "total"   => 0,
    //         ], 200);
    //     }

    //     // Fetch domains (optional: also secure domains belong to admin)
    //     $domains = Domain::whereIn('id', $domainIds)
    //         ->where('admin_id', $adminId)
    //         ->get();

    //     return response()->json([
    //         "status"  => true,
    //         "message" => "Successfully fetched domains.",
    //         "data"    => $domains,
    //         "total"   => $domains->count(),
    //         "domainIds" => $domainIds,
    //     ], 200);
    // }
    public function setDomains(Request $request, string $id)
    {
        if (!ctype_digit($id)) {
            return response()->json([
                "status"  => false,
                "message" => "Invalid domain set ID."
            ], 422);
        }

        $adminId = Auth::guard('admin')->id();
        $perPage = max((int) $request->get('per_page', 10), 1);

        $set = DomainSet::where('id', (int) $id)
            ->where('admin_id', $adminId)
            ->first();

        if (!$set) {
            return response()->json([
                "status"  => false,
                "message" => "Domain set not found."
            ], 404);
        }

        $domainIds = json_decode($set->domains, true);

        if (!is_array($domainIds)) {
            return response()->json([
                "status"  => false,
                "message" => "Invalid domains JSON stored in this set."
            ], 500);
        }

        $domainIds = array_values(array_unique(array_filter(array_map(
            fn($v) => ctype_digit((string)$v) ? (int)$v : null,
            $domainIds
        ))));

        if (count($domainIds) === 0) {
            return response()->json([
                "status" => true,
                "data"   => [
                    "domains" => [
                        "data" => [],
                        "total" => 0
                    ]
                ]
            ]);
        }

        // ✅ PAGINATION WITH ORDER PRESERVED
        $domains = Domain::whereIn('id', $domainIds)
            ->where('admin_id', $adminId)
            ->orderByRaw('FIELD(id,' . implode(',', $domainIds) . ')')
            ->paginate($perPage);

        return response()->json([
            "status" => true,
            "data"   => [
                "domains" => $domains
            ]
        ], 200);
    }



    // ------------- dummy ------------

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
}
