<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        // Step 1: Check permission
        Gate::authorize('createTask', $project);

        // Step 2: Validate request data
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'due_date' => ['nullable', 'date'],
        ]);

        // Step 3: Check whether assignee belongs to this project
        if (!empty($validated['assigned_to'])) {
            $isMember = $project->members()
                ->where('users.id', $validated['assigned_to'])
                ->exists();

            $isOwner = (int) $project->owner_id === (int) $validated['assigned_to'];

            if (!$isMember && !$isOwner) {
                return response()->json([
                    'message' => 'Assigned user must belong to this project.',
                ], 422);
            }
        }

        // Step 4: Create task
        $task = $project->tasks()->create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => 'todo',
        ]);

        // Step 5: Return response
        return response()->json([
            'message' => 'Task created successfully.',
            'task' => $task->load(['creator', 'assignee']),
        ], 201);
    }

    public function update(
    Request $request,
    Project $project,
    \App\Models\Task $task
): JsonResponse {

    // Check task belongs to the requested project
    if ((int) $task->project_id !== (int) $project->id) {
        abort(404);
    }

    // Check permission
    Gate::authorize('updateTask', [$project, $task]);

    // Validate request
    $validated = $request->validate([
        'title' => ['sometimes', 'required', 'string', 'max:255'],
        'description' => ['sometimes', 'nullable', 'string'],
        'priority' => ['sometimes', 'in:low,medium,high,urgent'],
        'due_date' => ['sometimes', 'nullable', 'date'],
    ]);

    // Update task
    $task->update($validated);

    return response()->json([
        'message' => 'Task updated successfully.',
        'task' => $task->fresh()->load(['creator', 'assignee']),
    ]);
}
public function updateStatus(
    Request $request,
    Project $project,
    \App\Models\Task $task
): JsonResponse {

    if ((int) $task->project_id !== (int) $project->id) {
        abort(404);
    }

    Gate::authorize('updateTask', [$project, $task]);

    $validated = $request->validate([
        'status' => [
            'required',
            'in:todo,in_progress,blocked,done',
        ],
    ]);

    $task->status = $validated['status'];

    if ($validated['status'] === 'done') {
        $task->completed_at = now();
    } else {
        $task->completed_at = null;
    }

    $task->save();

    return response()->json([
        'message' => 'Task status updated successfully.',
        'task' => $task->fresh(),
    ]);
}
public function destroy(
    Project $project,
    \App\Models\Task $task
): JsonResponse {

    if ((int) $task->project_id !== (int) $project->id) {
        abort(404);
    }

    Gate::authorize('deleteTask', $project);

    $task->delete();

    return response()->json([
        'message' => 'Task deleted successfully.',
    ]);
}

public function index(Request $request, Project $project): JsonResponse
{
    // 1. Check whether the user can access this project
    Gate::authorize('view', $project);

    // 2. Validate filters
    $filters = $request->validate([
        'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        'status' => ['sometimes', 'in:todo,in_progress,blocked,done'],
        'priority' => ['sometimes', 'in:low,medium,high,urgent'],
        'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
        'due_date_from' => ['sometimes', 'date'],
        'due_date_to' => ['sometimes', 'date', 'after_or_equal:due_date_from'],
        'page' => ['sometimes', 'integer', 'min:1'],
    ]);

    // 3. Start project-specific task query
    $query = $project->tasks()
        ->with(['creator', 'assignee']);

    // 4. Search by title
    $query->when($filters['search'] ?? null, function ($query, $search) {
        $query->where('title', 'like', '%' . $search . '%');
    });

    // 5. Filter by status
    $query->when($filters['status'] ?? null, function ($query, $status) {
        $query->where('status', $status);
    });

    // 6. Filter by priority
    $query->when($filters['priority'] ?? null, function ($query, $priority) {
        $query->where('priority', $priority);
    });

    // 7. Filter by assigned user
    $query->when($filters['assigned_to'] ?? null, function ($query, $userId) {
        $query->where('assigned_to', $userId);
    });

    // 8. Filter by due date range
    $query->when($filters['due_date_from'] ?? null, function ($query, $date) {
        $query->whereDate('due_date', '>=', $date);
    });

    $query->when($filters['due_date_to'] ?? null, function ($query, $date) {
        $query->whereDate('due_date', '<=', $date);
    });

    // 9. Paginate results
    $tasks = $query->latest()
        ->paginate(10)
        ->withQueryString();

    // 10. Return response
    return response()->json([
        'message' => 'Tasks retrieved successfully.',
        'tasks' => $tasks,
    ]);
}
}