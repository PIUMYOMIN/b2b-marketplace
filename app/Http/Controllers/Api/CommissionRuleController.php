<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommissionRuleController extends Controller
{
    /** GET /admin/commission-rules */
    public function index()
    {
        try {
            $rules = CommissionRule::orderByRaw("FIELD(type,'default','account_level','business_type','category')")
                ->orderBy('reference_id')
                ->get();

            return response()->json(['success' => true, 'data' => $rules]);
        } catch (\Exception $e) {
            Log::error('CommissionRule index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load commission rules.'], 500);
        }
    }

    /** POST /admin/commission-rules */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'type' => 'required|in:default,account_level,category,business_type',
            'reference_id' => 'nullable|integer',
            'rate' => 'required|numeric|min:0|max:1',
            'notes' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        try {
            $rule = CommissionRule::create(array_merge(
                $v->validated(),
                ['is_active' => $request->input('is_active', true)]
            ));
            return response()->json(['success' => true, 'data' => $rule], 201);
        } catch (\Exception $e) {
            Log::error('CommissionRule store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create rule.'], 500);
        }
    }

    /** PUT /admin/commission-rules/{id} */
    public function update(Request $request, int $id)
    {
        $rule = CommissionRule::findOrFail($id);

        $v = Validator::make($request->all(), [
            'rate' => 'sometimes|numeric|min:0|max:1',
            'notes' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        try {
            $rule->update($v->validated());
            return response()->json(['success' => true, 'data' => $rule->fresh()]);
        } catch (\Exception $e) {
            Log::error('CommissionRule update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update rule.'], 500);
        }
    }

    /** DELETE /admin/commission-rules/{id} */
    public function destroy(int $id)
    {
        try {
            CommissionRule::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => 'Rule deleted.']);
        } catch (\Exception $e) {
            Log::error('CommissionRule destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete rule.'], 500);
        }
    }
}