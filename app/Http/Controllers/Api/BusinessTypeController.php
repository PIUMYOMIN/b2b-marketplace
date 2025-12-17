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
    public function index(Request $request)
    {
        try {
            $businessTypes = BusinessType::active()
                ->ordered()
                ->get()
                ->map(function ($type) {
                    return [
                        'id' => $type->id,
                        'slug' => $type->slug,
                        'name' => $type->name,
                        'description' => $type->description,
                        'requires_registration' => $type->requires_registration,
                        'requires_tax_document' => $type->requires_tax_document,
                        'requires_identity_document' => $type->requires_identity_document,
                        'requires_business_certificate' => $type->requires_business_certificate,
                        'is_individual' => $type->isIndividualType(),
                        'icon' => $type->icon,
                        'color' => $type->color,
                        'document_requirements' => $type->getDocumentRequirements()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $businessTypes
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch business types: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch business types'
            ], 500);
        }
    }

    /**
     * Create new business type (Admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:business_types,name',
            'slug' => 'required|string|max:255|unique:business_types,slug',
            'description' => 'nullable|string',
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
            'name' => 'sometimes|string|max:255|unique:business_types,name,' . $id,
            'slug' => 'sometimes|string|max:255|unique:business_types,slug,' . $id,
            'description' => 'nullable|string',
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
     * Get business type by slug
     */
    public function show($slug)
    {
        try {
            $businessType = BusinessType::where('slug', $slug)
                ->active()
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $businessType->id,
                    'slug' => $businessType->slug,
                    'name' => $businessType->name,
                    'description' => $businessType->description,
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
