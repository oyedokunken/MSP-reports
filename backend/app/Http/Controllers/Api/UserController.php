<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function getUser(Request $request)
    {
        $authUser = $request->user();

        $users = User::where('company_id', $authUser->company_id)
                     ->with('role')
                     ->get();

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

        $defaultPermissions = [
            "integrations" => ["view" => true, "manage" => true, "sync" => true],
            "customers" => ["view" => true, "edit" => true, "map" => true],
            "reports" => ["view" => true, "configure" => true, "generate" => true, "download" => true],
            "users" => ["view" => true, "manage" => true],
            "company_settings" => ["view" => true, "edit" => true]
        ];

        $permissions = $validated['permissions'] ?? [];
        foreach ($defaultPermissions as $section => $actions) {
            if (!isset($permissions[$section]) || !is_array($permissions[$section])) {
                $permissions[$section] = $actions;
            } else {
                $permissions[$section] = array_merge($actions, $permissions[$section]);
            }
        }

        $user = User::create([
            'company_id' => $authUser->company_id,
            'role_id' => $validated['role_id'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status' => $validated['status'] ?? 'active',
            'auth_method' => $validated['auth_method'] ?? 'local',
            'permissions' => $permissions,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $user->load('role')
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
            if (!isset($permissions[$section]) || !is_array($permissions[$section])) {
                $permissions[$section] = $actions;
            } else {
                $permissions[$section] = array_merge($actions, $permissions[$section]);
            }
        }

        $user->permissions = $permissions;
        $user->save();

        return response()->json([
            'status' => 'success',
            'data' => $user->load('role')
        ]);
    }
}
