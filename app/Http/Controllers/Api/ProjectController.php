<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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
}