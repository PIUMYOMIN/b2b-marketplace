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
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a paginated list of users.
     */
    // In UserController::index() method
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $role = $request->input('role');

        $query = User::with('roles') // Make sure this line is present
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
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|unique:users,phone,' . $user->id,
            'password' => ['sometimes', 'confirmed', Rules\Password::defaults()],
            'type' => 'sometimes|in:admin,seller,buyer',
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|in:active,inactive,suspended,disabled,restricted',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'profile_photo' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated, $user, $request) {
            $updateData = array_filter([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? null,
                'status' => $validated['status'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'country' => $validated['country'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'profile_photo' => $validated['profile_photo'] ?? null,
            ], fn($v) => $v !== null);

            if ($request->has('password')) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            if ($request->has('type') && $user->type !== $validated['type']) {
                $updateData['type'] = $validated['type'];
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
     * Update user profile (for any authenticated user — all roles).
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|unique:users,phone,' . $user->id,
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            // Profile photo: accepts a relative storage path returned by the upload endpoint
            'profile_photo' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated, $user) {
            $user->update($validated);

            return response()->json([
                'success' => true,
                'data' => new UserResource($user->fresh()->load('roles')),
                'message' => 'Profile updated successfully',
            ]);
        });
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => [
                'required',
                function ($attribute, $value, $fail) use ($user) {
                    if (!Hash::check($value, $user->password)) {
                        $fail('The current password is incorrect.');
                    }
                }
            ],
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
            'total_revenue' => (float) $user->orders()->sum('total_amount'),      // FIX: was 'total'
            'average_order_value' => (float) $user->orders()->avg('total_amount') ?? 0, // FIX: was 'total'
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }


}
