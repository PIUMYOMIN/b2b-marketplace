<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a paginated list of users.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $role = $request->input('role');

        $query = User::with('roles')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            })
            ->when($role, function ($query) use ($role) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            });

        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'type' => 'required|in:admin,seller,buyer',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'account_number' => 'nullable|string'
        ]);

        //user_id generation
        $validated['user_id'] = 'USER-' . strtoupper(Str::random(9));

        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'user_id' => $validated['user_id'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
                'type' => $validated['type'],
                'company_name' => $validated['company_name'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
            ]);

            // Assign role based on type
            $user->assignRole($validated['type']);

            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'message' => 'User created successfully'
            ], 201);
        });
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load('roles'))
        ]);
    }

    public function showProfile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load('roles'))
        ]);
    }


    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'sometimes|string|unique:users,phone,'.$user->id,
            'password' => ['sometimes', 'confirmed', Rules\Password::defaults()],
            'type' => 'sometimes|in:admin,seller,buyer',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'account_number' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($validated, $user, $request) {
            $updateData = [
                'name' => $validated['name'] ?? $user->name,
                'email' => $validated['email'] ?? $user->email,
                'phone' => $validated['phone'] ?? $user->phone,
                'company_name' => $validated['company_name'] ?? $user->company_name,
                'address' => $validated['address'] ?? $user->address,
                'city' => $validated['city'] ?? $user->city,
                'state' => $validated['state'] ?? $user->state,
                'account_number' => $validated['account_number'] ?? $user->account_number
            ];

            if ($request->has('password')) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            if ($request->has('type') && $user->type !== $validated['type']) {
                $updateData['type'] = $validated['type'];
                // Sync roles when type changes
                $user->syncRoles($validated['type']);
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'data' => new UserResource($user->fresh()->load('roles')),
                'message' => 'User updated successfully'
            ]);
        });
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            $user->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Assign roles to a user.
     */
    public function assignRoles(Request $request, User $user)
    {
        $this->authorize('assignRoles', $user);

        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        $user->syncRoles($validated['roles']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load('roles')),
            'message' => 'Roles assigned successfully'
        ]);
    }

    /**
     * Get available roles.
     */
    public function getRoles()
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::all();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Update user profile (for authenticated user).
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'sometimes|string|unique:users,phone,'.$user->id,
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'account_number' => 'nullable|string'
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->fresh()),
            'message' => 'Profile updated successfully'
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', function ($attribute, $value, $fail) use ($user) {
                if (!Hash::check($value, $user->password)) {
                    $fail('The current password is incorrect.');
                }
            }],
            'new_password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['new_password'])
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    public function stats(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_orders' => $user->orders()->count(),
            'total_revenue' => $user->orders()->sum('total'),
            'average_order_value' => $user->orders()->avg('total'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function viewWishlist()
{
    try {
        $user = auth()->user();
        $wishlist = $user->wishlist()->with('product')->get();
        
        return response()->json([
            'success' => true,
            'data' => $wishlist
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch wishlist'
        ], 500);
    }
}



public function addToWishlist($productId)
{
    try {
        $user = auth()->user();
        $user->wishlist()->attach($productId);

        return response()->json([
            'success' => true,
            'message' => 'Product added to wishlist'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to add to wishlist'
        ], 500);
    }
}


public function removeFromWishlist($productId)
{
    try {
        $user = auth()->user();
        $user->wishlist()->detach($productId);
        
        return response()->json([
            'success' => true,
            'message' => 'Product removed from wishlist'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to remove from wishlist'
        ], 500);
    }
}

    
}