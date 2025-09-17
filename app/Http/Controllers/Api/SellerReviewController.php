<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SellerReviewController extends Controller
{
    function index()
    {
        return response()->json(['message' => 'StoreReview index']);
    }

    function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'seller_profile_id' => 'required|exists:seller_profiles,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
            'status' => 'nullable|in:pending,approved,rejected'
        ]);

        //store review creation logic would go here
        $storeReview = StoreReview::create($validated);
        return response()->json(['message' => 'StoreReview created', 'data' => $storeReview], 201);

    }

    function show($id)
    {
        return response()->json(['message' => "Show store review with ID: $id"]);
    }

    function update(Request $request, $id)
    {
        $validated = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'review' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:pending,approved,rejected'
        ]);

        //store review update logic would go here
        $storeReview = StoreReview::findOrFail($id);
        $storeReview->update($validated);

        return response()->json(['message' => 'StoreReview updated', 'data' => $storeReview], 200);
    }

    function destroy($id)
    {
        $storeReview = StoreReview::findOrFail($id);
        $storeReview->delete();

        return response()->json(['message' => 'StoreReview deleted'], 200);
    }
}