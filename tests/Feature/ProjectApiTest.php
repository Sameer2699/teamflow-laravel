<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', [
                'name' => 'Customer Management System',
                'description' => 'Manage customer operations and tasks',
            ]);

        $response->assertCreated()
            ->assertJsonPath('project.name', 'Customer Management System')
            ->assertJsonPath('project.owner_id', $user->id)
            ->assertJsonPath('project.slug', 'customer-management-system');

        $this->assertDatabaseHas('projects', [
            'name' => 'Customer Management System',
            'owner_id' => $user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_create_project(): void
    {
        $response = $this->postJson('/api/projects', [
            'name' => 'Unauthorized Project',
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_owner_id_cannot_be_spoofed(): void
    {
        $actualOwner = User::factory()->create();
        $authenticatedUser = User::factory()->create();

        $response = $this->actingAs($authenticatedUser, 'sanctum')
            ->postJson('/api/projects', [
                'name' => 'Secure Project',
                'description' => 'Testing ownership protection',
                'owner_id' => $actualOwner->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('project.owner_id', $authenticatedUser->id);

        $this->assertDatabaseHas('projects', [
            'name' => 'Secure Project',
            'owner_id' => $authenticatedUser->id,
        ]);
    }

    public function test_user_can_only_list_owned_and_member_projects(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $outsider = User::factory()->create();

        $ownedProject = Project::create([
            'owner_id' => $user->id,
            'name' => 'My Project',
            'slug' => 'my-project',
            'status' => 'active',
        ]);

        $memberProject = Project::create([
            'owner_id' => $otherUser->id,
            'name' => 'Shared Project',
            'slug' => 'shared-project',
            'status' => 'active',
        ]);

        $memberProject->members()->attach($user->id, [
            'role' => 'developer',
            'joined_at' => now(),
        ]);

        $hiddenProject = Project::create([
            'owner_id' => $outsider->id,
            'name' => 'Private Project',
            'slug' => 'private-project',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/projects');

        $response->assertOk();

        $visibleIds = collect($response->json('data'))
            ->pluck('id')
            ->all();

        $this->assertContains($ownedProject->id, $visibleIds);
        $this->assertContains($memberProject->id, $visibleIds);
        $this->assertNotContains($hiddenProject->id, $visibleIds);
    }

    public function test_non_member_cannot_view_project(): void
    {
        $owner = User::factory()->create();
        $nonMember = User::factory()->create();

        $project = Project::create([
            'owner_id' => $owner->id,
            'name' => 'Private Project',
            'slug' => 'private-project',
            'status' => 'active',
        ]);

        $response = $this->actingAs($nonMember, 'sanctum')
            ->getJson('/api/projects/' . $project->id);

        $response->assertForbidden();
    }

    public function test_project_member_can_view_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $project = Project::create([
            'owner_id' => $owner->id,
            'name' => 'Team Project',
            'slug' => 'team-project',
            'status' => 'active',
        ]);

        $project->members()->attach($member->id, [
            'role' => 'developer',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson('/api/projects/' . $project->id);

        $response->assertOk()
            ->assertJsonPath('project.id', $project->id);
    }

    
public function test_project_owner_can_update_project(): void
{
    $owner = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Original Project',
        'slug' => 'original-project',
        'status' => 'active',
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson('/api/projects/' . $project->id, [
            'name' => 'Updated Project',
        ]);

    $response->assertOk()
        ->assertJsonPath('project.name', 'Updated Project');

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Updated Project',
    ]);
}

public function test_project_manager_can_update_project(): void
{
    $owner = User::factory()->create();
    $manager = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Manager Project',
        'slug' => 'manager-project',
        'status' => 'active',
    ]);

    $project->members()->attach($manager->id, [
        'role' => 'manager',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->patchJson('/api/projects/' . $project->id, [
            'description' => 'Updated by manager',
        ]);

    $response->assertOk()
        ->assertJsonPath('project.description', 'Updated by manager');
}

public function test_developer_cannot_update_project(): void
{
    $owner = User::factory()->create();
    $developer = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Developer Project',
        'slug' => 'developer-project',
        'status' => 'active',
    ]);

    $project->members()->attach($developer->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($developer, 'sanctum')
        ->patchJson('/api/projects/' . $project->id, [
            'name' => 'Unauthorized Update',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Developer Project',
    ]);
}

public function test_project_owner_can_delete_project(): void
{
    $owner = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Delete Project',
        'slug' => 'delete-project',
        'status' => 'active',
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->deleteJson('/api/projects/' . $project->id);

    $response->assertOk();

    $this->assertDatabaseMissing('projects', [
        'id' => $project->id,
    ]);
}

public function test_project_manager_cannot_delete_project(): void
{
    $owner = User::factory()->create();
    $manager = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Protected Project',
        'slug' => 'protected-project',
        'status' => 'active',
    ]);

    $project->members()->attach($manager->id, [
        'role' => 'manager',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->deleteJson('/api/projects/' . $project->id);

    $response->assertForbidden();

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
    ]);
}

public function test_global_admin_can_update_and_delete_project(): void
{
    $owner = User::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Admin Test Project',
        'slug' => 'admin-test-project',
        'status' => 'active',
    ]);

    $updateResponse = $this->actingAs($admin, 'sanctum')
        ->patchJson('/api/projects/' . $project->id, [
            'name' => 'Admin Updated Project',
        ]);

    $updateResponse->assertOk()
        ->assertJsonPath('project.name', 'Admin Updated Project');

    $deleteResponse = $this->deleteJson(
        '/api/projects/' . $project->id
    );

    $deleteResponse->assertOk();

    $this->assertDatabaseMissing('projects', [
        'id' => $project->id,
    ]);
}
}