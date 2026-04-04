<?php

namespace App\Http\Controllers\Api;

use App\Models\BusinessType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class BusinessTypeController extends Controller
{
    /**
     * Get all active business types
     */
    /** Shared formatter for a BusinessType → array */
    private function formatType(BusinessType $type): array
    {
        return [
            'id'                            => $type->id,
            'name_en'                       => $type->name_en,
            'name_mm'                       => $type->name_mm,
            'slug_en'                       => $type->slug_en,
            'slug_mm'                       => $type->slug_mm,
            'description_en'                => $type->description_en,
            'description_mm'                => $type->description_mm,
            'requires_registration'         => (bool) $type->requires_registration,
            'requires_tax_document'         => (bool) $type->requires_tax_document,
            'requires_identity_document'    => (bool) $type->requires_identity_document,
            'requires_business_certificate' => (bool) $type->requires_business_certificate,
            'additional_requirements'       => $type->additional_requirements,
            'is_individual'                 => $type->isIndividualType(),
            // is_active as boolean (not string) so frontend toggle works correctly
            'is_active'                     => (bool) $type->is_active,
            'sort_order'                    => $type->sort_order,
            'icon'                          => $type->icon,
            'color'                         => $type->color,
            'document_requirements'         => $type->getDocumentRequirements(),
            'sellers_count'                 => $type->sellers_count ?? null,
        ];
    }

    /**
     * GET /business-types — public, active only (used by seller onboarding)
     */
    public function index(Request $request)
    {
        try {
            $businessTypes = BusinessType::active()->ordered()->get()
                ->map(fn($t) => $this->formatType($t));

            return response()->json(['success' => true, 'data' => $businessTypes]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch business types: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch business types'], 500);
        }
    }

    /**
     * GET /admin/business-types — admin: ALL types including inactive
     */
    public function adminIndex(Request $request)
    {
        try {
            $query = BusinessType::withCount([
                'sellerProfiles as sellers_count' => fn($q) => $q->whereIn('status', ['approved', 'active'])
            ])->ordered();

            $businessTypes = $query->get()->map(fn($t) => $this->formatType($t));

            return response()->json(['success' => true, 'data' => $businessTypes]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch all business types: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch business types'], 500);
        }
    }

    /**
     * Create new business type (Admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_en' => 'required|string|max:255|unique:business_types,name_en',
            'name_mm' => 'nullable|string|max:255',
            'slug_en' => 'required|string|max:255|unique:business_types,slug_en',
            'slug_mm' => 'nullable|string|max:255|unique:business_types,slug_mm',
            'description_en' => 'nullable|string',
            'description_mm' => 'nullable|string',
            'requires_registration' => 'boolean',
            'requires_tax_document' => 'boolean',
            'requires_identity_document' => 'boolean',
            'requires_business_certificate' => 'boolean',
            'additional_requirements' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'icon' => 'nullable|string',
            'color' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $businessType = BusinessType::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Business type created successfully',
                'data' => $businessType
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Failed to create business type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create business type'
            ], 500);
        }
    }

    /**
     * Update business type (Admin only)
     */
    public function update(Request $request, $id)
    {
        $businessType = BusinessType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name_en' => 'sometimes|string|max:255|unique:business_types,name_en,' . $id,
            'name_mm' => 'sometimes|string|max:255',
            'slug_en' => 'sometimes|string|max:255|unique:business_types,slug_en,' . $id,
            'slug_mm' => 'sometimes|string|max:255|unique:business_types,slug_mm,' . $id,
            'description_en' => 'nullable|string',
            'description_mm' => 'nullable|string',
            'requires_registration' => 'boolean',
            'requires_tax_document' => 'boolean',
            'requires_identity_document' => 'boolean',
            'requires_business_certificate' => 'boolean',
            'additional_requirements' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'icon' => 'nullable|string',
            'color' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $businessType->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Business type updated successfully',
                'data' => $businessType
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to update business type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update business type'
            ], 500);
        }
    }

    /**
     * Delete business type (Admin only)
     */

    /**
     * PATCH /admin/business-types/{id}/toggle — toggle active status
     */
    public function toggleStatus($id)
    {
        $type = BusinessType::findOrFail($id);
        $type->update(['is_active' => !$type->is_active]);
        return response()->json([
            'success' => true,
            'message' => 'Status updated.',
            'data'    => $this->formatType($type->fresh()),
        ]);
    }

    public function destroy($id)
    {
        try {
            $businessType = BusinessType::findOrFail($id);

            // Check if business type is being used
            if ($businessType->sellerProfiles()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete business type that is in use by sellers'
                ], 400);
            }

            $businessType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Business type deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to delete business type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete business type'
            ], 500);
        }
    }

    /**
     * Get business type by slug_en
     */
    public function show($slug)
    {
        try {
            $businessType = BusinessType::where('slug_en', $slug)
                ->active()
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $businessType->id,
                    'name_en' => $businessType->name_en,
                    'name_mm' => $businessType->name_mm,
                    'slug_en' => $businessType->slug_en,
                    'slug_mm' => $businessType->slug_mm,
                    'description_en' => $businessType->description_en,
                    'description_mm' => $businessType->description_mm,
                    'requirements' => $businessType->getDocumentRequirements(),
                    'metadata' => [
                        'icon' => $businessType->icon,
                        'color' => $businessType->color
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Business type not found'
            ], 404);
        }
    }
}