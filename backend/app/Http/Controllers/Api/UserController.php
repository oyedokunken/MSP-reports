<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserController extends Controller
{
    public function getUser(Request $request)
    {
        $authUser = $request->user();

        $users = User::where('company_id', $authUser->company_id)
                     ->with('role')
                     ->get()
                     /** 🛡️ Hide passwords from the returned collection **/
                     ->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }

    public function createUser(Request $request)
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'status' => 'sometimes|in:active,inactive,locked',
            'auth_method' => 'sometimes|in:local,saml,m365,ldap',
            'permissions' => 'nullable|array',
        ]);

        /** 🧹 Sanitise inputs **/
        $validated['name'] = trim(strip_tags($validated['name']));
        $validated['email'] = trim(strtolower($validated['email']));
        $validated['auth_method'] = trim($validated['auth_method'] ?? 'local');
        $validated['status'] = trim($validated['status'] ?? 'active');

        $defaultPermissions = [
            "integrations" => ["view" => true, "manage" => true, "sync" => true],
            "customers" => ["view" => true, "edit" => true, "map" => true],
            "reports" => ["view" => true, "configure" => true, "generate" => true, "download" => true],
            "users" => ["view" => true, "manage" => true],
            "company_settings" => ["view" => true, "edit" => true]
        ];

        $permissions = $validated['permissions'] ?? [];
        foreach ($defaultPermissions as $section => $actions) {
            $permissions[$section] = array_merge($actions, $permissions[$section] ?? []);
        }

        $user = User::create([
            'company_id' => $authUser->company_id,
            'role_id' => $validated['role_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
            'auth_method' => $validated['auth_method'],
            'permissions' => $permissions,
        ]);

        /** 🛡️ Hide password before returning **/
        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'data' => $sanitisedUser->load('role')
        ], 201);
    }

    public function updatePermissions(Request $request, $id)
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
                    ->where('company_id', $authUser->company_id)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company'
            ], 404);
        }

        $validated = $request->validate([
            'permissions' => 'required|array'
        ]);

        $defaultPermissions = [
            "integrations" => ["view" => true, "manage" => true, "sync" => true],
            "customers" => ["view" => true, "edit" => true, "map" => true],
            "reports" => ["view" => true, "configure" => true, "generate" => true, "download" => true],
            "users" => ["view" => true, "manage" => true],
            "company_settings" => ["view" => true, "edit" => true]
        ];

        $permissions = $validated['permissions'];
        foreach ($defaultPermissions as $section => $actions) {
            $permissions[$section] = array_merge($actions, $permissions[$section] ?? []);
        }

        $user->permissions = $permissions;
        $user->save();

        /** 🛡️ Hide password before returning **/
        $sanitisedUser = $user->makeHidden(['password']);

        return response()->json([
            'status' => 'success',
            'data' => $sanitisedUser->load('role')
        ]);
    }
}
