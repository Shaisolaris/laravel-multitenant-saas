<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        $tenant = app('current_tenant');
        $teams = Team::forTenant($tenant->id)->with('members:id,name,email')->paginate(20);
        return response()->json($teams);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = app('current_tenant');

        if ($tenant->hasReachedLimit('teams')) {
            return response()->json(['error' => 'Team limit reached for your plan'], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $team = Team::create([...$validated, 'tenant_id' => $tenant->id]);
        $team->members()->attach($request->user()->id, ['role' => 'lead']);

        return response()->json(['team' => $team->load('members')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenant = app('current_tenant');
        $team = Team::forTenant($tenant->id)->with('members:id,name,email')->findOrFail($id);
        return response()->json(['team' => $team]);
    }

    public function addMember(Request $request, int $id): JsonResponse
    {
        $tenant = app('current_tenant');
        $team = Team::forTenant($tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:lead,member',
        ]);

        $user = User::forTenant($tenant->id)->findOrFail($validated['user_id']);
        $team->members()->syncWithoutDetaching([$user->id => ['role' => $validated['role']]]);

        return response()->json(['team' => $team->load('members')]);
    }

    public function removeMember(int $teamId, int $userId): JsonResponse
    {
        $tenant = app('current_tenant');
        $team = Team::forTenant($tenant->id)->findOrFail($teamId);
        $team->members()->detach($userId);
        return response()->json(['message' => 'Member removed from team']);
    }

    public function destroy(int $id): JsonResponse
    {
        $tenant = app('current_tenant');
        $team = Team::forTenant($tenant->id)->findOrFail($id);
        $team->members()->detach();
        $team->delete();
        return response()->json(null, 204);
    }
}
