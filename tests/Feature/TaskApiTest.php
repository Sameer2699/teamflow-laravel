<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_owner_can_create_task(): void
    {
        // Create project owner
        $owner = User::factory()->create([
            'role' => 'developer',
        ]);

        // Create project owned by this user
        $project = Project::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Authenticate as project owner
        Sanctum::actingAs($owner);

        // Send API request
        $response = $this->postJson(
            "/api/projects/{$project->id}/tasks",
            [
                'title' => 'Create Login API',
                'description' => 'Develop login endpoint',
                'priority' => 'high',
            ]
        );

        // Verify API response
        $response->assertStatus(201)
            ->assertJsonPath('message', 'Task created successfully.')
            ->assertJsonPath('task.title', 'Create Login API')
            ->assertJsonPath('task.status', 'todo')
            ->assertJsonPath('task.priority', 'high')
            ->assertJsonPath('task.created_by', $owner->id);

        // Verify database record
        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Create Login API',
            'status' => 'todo',
            'priority' => 'high',
        ]);
    }

    public function test_project_manager_can_create_task(): void
{
    $owner = User::factory()->create();

    $manager = User::factory()->create([
        'role' => 'developer',
    ]);

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    // Add manager to the project
    $project->members()->attach($manager->id, [
        'role' => 'manager',
        'joined_at' => now(),
    ]);

    // Login as project manager
    Sanctum::actingAs($manager);

    // Create task
    $response = $this->postJson(
        "/api/projects/{$project->id}/tasks",
        [
            'title' => 'Develop Dashboard API',
            'priority' => 'high',
        ]
    );

    $response->assertStatus(201)
        ->assertJsonPath('task.title', 'Develop Dashboard API')
        ->assertJsonPath('task.status', 'todo');
}
public function test_developer_cannot_create_task(): void
{
    $owner = User::factory()->create();

    $developer = User::factory()->create([
        'role' => 'developer',
    ]);

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $project->members()->attach($developer->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    Sanctum::actingAs($developer);

    $response = $this->postJson(
        "/api/projects/{$project->id}/tasks",
        [
            'title' => 'Create Dashboard API',
            'priority' => 'high',
        ]
    );

    $response->assertForbidden();

    $this->assertDatabaseMissing('tasks', [
        'project_id' => $project->id,
        'title' => 'Create Dashboard API',
    ]);
}
public function test_viewer_cannot_create_task(): void
{
    $owner = User::factory()->create();

    $viewer = User::factory()->create([
        'role' => 'developer',
    ]);

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $project->members()->attach($viewer->id, [
        'role' => 'viewer',
        'joined_at' => now(),
    ]);

    Sanctum::actingAs($viewer);

    $response = $this->postJson(
        "/api/projects/{$project->id}/tasks",
        [
            'title' => 'Create Dashboard API',
            'priority' => 'high',
        ]
    );

    $response->assertForbidden();

    $this->assertDatabaseMissing('tasks', [
        'project_id' => $project->id,
        'title' => 'Create Dashboard API',
    ]);
}
public function test_global_admin_can_create_task(): void
{
    $owner = User::factory()->create();

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    // Admin is not a project member
    Sanctum::actingAs($admin);

    $response = $this->postJson(
        "/api/projects/{$project->id}/tasks",
        [
            'title' => 'Admin Created Task',
            'priority' => 'urgent',
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('task.title', 'Admin Created Task')
        ->assertJsonPath('task.created_by', $admin->id);

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'created_by' => $admin->id,
        'title' => 'Admin Created Task',
    ]);
}

public function test_task_can_be_assigned_to_project_member(): void
{
    $owner = User::factory()->create();

    $developer = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $project->members()->attach($developer->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    Sanctum::actingAs($owner);

    $response = $this->postJson(
        "/api/projects/{$project->id}/tasks",
        [
            'title' => 'Develop Login API',
            'assigned_to' => $developer->id,
            'priority' => 'high',
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('task.assigned_to', $developer->id);

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'assigned_to' => $developer->id,
        'title' => 'Develop Login API',
    ]);
}
public function test_task_cannot_be_assigned_to_non_member(): void
{
    $owner = User::factory()->create();

    $externalUser = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    Sanctum::actingAs($owner);

    $response = $this->postJson(
        "/api/projects/{$project->id}/tasks",
        [
            'title' => 'Develop Login API',
            'assigned_to' => $externalUser->id,
            'priority' => 'high',
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Assigned user must belong to this project.'
        );

    $this->assertDatabaseMissing('tasks', [
        'project_id' => $project->id,
        'title' => 'Develop Login API',
    ]);
}
public function test_unauthenticated_user_cannot_create_task(): void
{
    $owner = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $response = $this->postJson(
        "/api/projects/{$project->id}/tasks",
        [
            'title' => 'Create Login API',
            'priority' => 'high',
        ]
    );

    $response->assertUnauthorized();

    $this->assertDatabaseMissing('tasks', [
        'project_id' => $project->id,
        'title' => 'Create Login API',
    ]);
}
public function test_project_owner_can_update_task(): void
{
    $owner = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $task = $project->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Old title',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/tasks/{$task->id}", [
            'title' => 'Updated title',
            'priority' => 'high',
        ])
        ->assertOk()
        ->assertJsonPath('task.title', 'Updated title')
        ->assertJsonPath('task.priority', 'high');
}

public function test_project_manager_can_update_task(): void
{
    $owner = User::factory()->create();
    $manager = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $project->members()->attach($manager->id, [
        'role' => 'manager',
        'joined_at' => now(),
    ]);

    $task = $project->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Old title',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $this->actingAs($manager)
        ->patchJson("/api/projects/{$project->id}/tasks/{$task->id}", [
            'title' => 'Manager updated title',
        ])
        ->assertOk()
        ->assertJsonPath('task.title', 'Manager updated title');
}

public function test_assigned_developer_can_update_own_task(): void
{
    $owner = User::factory()->create();
    $developer = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $project->members()->attach($developer->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    $task = $project->tasks()->create([
        'created_by' => $owner->id,
        'assigned_to' => $developer->id,
        'title' => 'Old title',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $this->actingAs($developer)
        ->patchJson("/api/projects/{$project->id}/tasks/{$task->id}", [
            'title' => 'Developer updated title',
        ])
        ->assertOk()
        ->assertJsonPath('task.title', 'Developer updated title');
}

public function test_other_developer_cannot_update_task(): void
{
    $owner = User::factory()->create();
    $developer = User::factory()->create();
    $assignee = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $project->members()->attach([
        $developer->id => ['role' => 'developer', 'joined_at' => now()],
        $assignee->id => ['role' => 'developer', 'joined_at' => now()],
    ]);

    $task = $project->tasks()->create([
        'created_by' => $owner->id,
        'assigned_to' => $assignee->id,
        'title' => 'Original title',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $this->actingAs($developer)
        ->patchJson("/api/projects/{$project->id}/tasks/{$task->id}", [
            'title' => 'Unauthorized update',
        ])
        ->assertForbidden();
}
public function test_project_owner_can_mark_task_as_done(): void
{
    $owner = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $task = $project->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Complete API development',
        'status' => 'in_progress',
        'priority' => 'high',
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/tasks/{$task->id}/status", [
            'status' => 'done',
        ])
        ->assertOk()
        ->assertJsonPath('task.status', 'done')
        ->assertJsonPath('task.completed_at', fn ($value) => !empty($value));

    $this->assertNotNull($task->fresh()->completed_at);
}
public function test_completed_at_is_reset_when_task_moves_back_to_progress(): void
{
    $owner = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $task = $project->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Complete API development',
        'status' => 'done',
        'priority' => 'high',
        'completed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/tasks/{$task->id}/status", [
            'status' => 'in_progress',
        ])
        ->assertOk()
        ->assertJsonPath('task.status', 'in_progress')
        ->assertJsonPath('task.completed_at', null);

    $this->assertNull($task->fresh()->completed_at);
}
public function test_invalid_task_status_is_rejected(): void
{
    $owner = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $task = $project->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Test task',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $this->actingAs($owner)
        ->patchJson("/api/projects/{$project->id}/tasks/{$task->id}/status", [
            'status' => 'completed',
        ])
        ->assertUnprocessable();
}
public function test_project_owner_can_delete_task(): void
{
    $owner = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $task = $project->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Task to delete',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $this->actingAs($owner)
        ->deleteJson("/api/projects/{$project->id}/tasks/{$task->id}")
        ->assertOk()
        ->assertJson([
            'message' => 'Task deleted successfully.',
        ]);

    $this->assertDatabaseMissing('tasks', [
        'id' => $task->id,
    ]);
}
public function test_project_manager_can_delete_task(): void
{
    $owner = User::factory()->create();
    $manager = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $project->members()->attach($manager->id, [
        'role' => 'manager',
        'joined_at' => now(),
    ]);

    $task = $project->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Task to delete',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $this->actingAs($manager)
        ->deleteJson("/api/projects/{$project->id}/tasks/{$task->id}")
        ->assertOk();

    $this->assertDatabaseMissing('tasks', [
        'id' => $task->id,
    ]);
}
public function test_developer_cannot_delete_task(): void
{
    $owner = User::factory()->create();
    $developer = User::factory()->create();

    $project = Project::factory()->create([
        'owner_id' => $owner->id,
    ]);

    $project->members()->attach($developer->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    $task = $project->tasks()->create([
        'created_by' => $owner->id,
        'title' => 'Task to delete',
        'status' => 'todo',
        'priority' => 'medium',
    ]);

    $this->actingAs($developer)
        ->deleteJson("/api/projects/{$project->id}/tasks/{$task->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
    ]);
}
}