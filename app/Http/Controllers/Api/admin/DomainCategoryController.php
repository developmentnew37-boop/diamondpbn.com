<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DomainCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DomainCategoryController extends Controller
{
    //
    public function update(Request $request, string $id)
    {

        // 1) Check if ID exists
        $category = DomainCategory::find($id);

        if (!$category) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid ID. Record not found.',
                'errors'  => [
                    'id' => ['The selected ID is invalid.']
                ]
            ], 404);
        }

        // 2) validating input
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:100|unique:domain_categories,name,' . $id,
        ]);

        if ($validator->fails()) {

            // collect first error message for main "message" field
            $firstError = $validator->errors()->first();

            return response()->json([
                'status'  => false,
                'message' => $firstError,   // 👈 This becomes your single combined error message
                'errors'  => $validator->errors()
            ], 422);
        }

        $category->update(['name' => $request->name]);

        return response()->json([
            'status'  => true,
            'message' => "successfully updated the domain category",
            'data' => $category
        ]);
    }
}
