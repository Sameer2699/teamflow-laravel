<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
   
public function index(Request $request): JsonResponse
{
    $user = $request->user();

    $projects = Project::query()
        ->where(function ($query) use ($user) {
            $query->where('owner_id', $user->id)
                ->orWhereHas('members', function ($membersQuery) use ($user) {
                    $membersQuery->where('users.id', $user->id);
                });
        })
        ->with('owner:id,name,email')
        ->withCount('members')
        ->latest()
        ->paginate(10);

    return response()->json($projects);
}

    public function store(Request $request): JsonResponse
    {
        // 1. Validate incoming data
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        // 2. Generate a unique slug
        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 1;

        while (Project::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        // 3. Create project under authenticated user
        $project = $request->user()->ownedProjects()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'slug' => $slug,
            'status' => 'active',
        ]);

        // 4. Return created resource
        return response()->json([
            'message' => 'Project created successfully.',
            'project' => $project,
        ], 201);
    }

    public function show(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json([
            'project' => $project,
        ]);
    }
    
public function update(Request $request, Project $project): JsonResponse
{
    Gate::authorize('update', $project);

    $validated = $request->validate([
        'name' => ['sometimes', 'required', 'string', 'max:150'],
        'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
    ]);

    $project->fill($validated);
    $project->save();

    return response()->json([
        'message' => 'Project updated successfully.',
        'project' => $project->fresh(),
    ]);
}

public function destroy(Project $project): JsonResponse
{
    Gate::authorize('delete', $project);

    $project->delete();

    return response()->json([
        'message' => 'Project deleted successfully.',
    ]);
}

public function members(Project $project): JsonResponse
{
    Gate::authorize('viewMembers', $project);

    $members = $project->members()
        ->select('users.id', 'users.name', 'users.email')
        ->get()
        ->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'joined_at' => $member->pivot->joined_at,
            ];
        });

    return response()->json([
        'project_id' => $project->id,
        'owner' => $project->owner()->select('id', 'name', 'email')->first(),
        'members' => $members,
    ]);
}


public function addMember(Request $request, Project $project): JsonResponse
{
    Gate::authorize('addMember', $project);

    $validated = $request->validate([
        'user_id' => [
            'required',
            'integer',
            'exists:users,id',
        ],
        'role' => [
            'required',
            'string',
            Rule::in(['manager', 'developer', 'viewer']),
        ],
    ]);

    if ((int) $project->owner_id === (int) $validated['user_id']) {
        return response()->json([
            'message' => 'Project owner cannot be added as a member.',
        ], 422);
    }

    $alreadyMember = $project->members()
        ->where('users.id', $validated['user_id'])
        ->exists();

    if ($alreadyMember) {
        return response()->json([
            'message' => 'User is already a member of this project.',
        ], 409);
    }

    $project->members()->attach($validated['user_id'], [
        'role' => $validated['role'],
        'joined_at' => now(),
    ]);

    $member = User::query()
        ->select('id', 'name', 'email')
        ->findOrFail($validated['user_id']);

    return response()->json([
        'message' => 'Project member added successfully.',
        'member' => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $validated['role'],
        ],
    ], 201);
}


public function removeMember(Project $project, User $user): JsonResponse
{
    Gate::authorize('removeMember', $project);

    if ((int) $project->owner_id === (int) $user->id) {
        return response()->json([
            'message' => 'Project owner cannot be removed.',
        ], 422);
    }

    $isMember = $project->members()
        ->where('users.id', $user->id)
        ->exists();

    if (!$isMember) {
        return response()->json([
            'message' => 'User is not a member of this project.',
        ], 404);
    }

    $project->members()->detach($user->id);

    return response()->json([
        'message' => 'Project member removed successfully.',
        'removed_user_id' => $user->id,
    ]);
}

public function updateMemberRole(
    Request $request,
    Project $project,
    User $user
): JsonResponse {
    Gate::authorize('updateMemberRole', $project);

    $validated = $request->validate([
        'role' => [
            'required',
            'string',
            Rule::in(['manager', 'developer', 'viewer']),
        ],
    ]);

    if ((int) $project->owner_id === (int) $user->id) {
        return response()->json([
            'message' => 'Project owner role cannot be changed.',
        ], 422);
    }

    $isMember = $project->members()
        ->where('users.id', $user->id)
        ->exists();

    if (!$isMember) {
        return response()->json([
            'message' => 'User is not a member of this project.',
        ], 404);
    }

    $project->members()->updateExistingPivot(
        $user->id,
        ['role' => $validated['role']]
    );

    return response()->json([
        'message' => 'Project member role updated successfully.',
        'member' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $validated['role'],
        ],
    ]);
}

}