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


public function test_project_owner_can_view_project_members(): void
{
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Owner Members Project',
        'slug' => 'owner-members-project',
        'status' => 'active',
    ]);

    $project->members()->attach($member->id, [
        'role' => 'manager',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson('/api/projects/' . $project->id . '/members');

    $response->assertOk()
        ->assertJsonPath('project_id', $project->id)
        ->assertJsonPath('owner.id', $owner->id)
        ->assertJsonPath('members.0.id', $member->id)
        ->assertJsonPath('members.0.role', 'manager');
}

public function test_project_member_can_view_project_members(): void
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $anotherMember = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Shared Members Project',
        'slug' => 'shared-members-project',
        'status' => 'active',
    ]);

    $project->members()->attach([
        $member->id => [
            'role' => 'developer',
            'joined_at' => now(),
        ],
        $anotherMember->id => [
            'role' => 'viewer',
            'joined_at' => now(),
        ],
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->getJson('/api/projects/' . $project->id . '/members');

    $response->assertOk()
        ->assertJsonCount(2, 'members');
}

public function test_non_member_cannot_view_project_members(): void
{
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Private Members Project',
        'slug' => 'private-members-project',
        'status' => 'active',
    ]);

    $response = $this->actingAs($outsider, 'sanctum')
        ->getJson('/api/projects/' . $project->id . '/members');

    $response->assertForbidden();
}

public function test_unauthenticated_user_cannot_view_project_members(): void
{
    $owner = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Protected Members Project',
        'slug' => 'protected-members-project',
        'status' => 'active',
    ]);

    $response = $this->getJson(
        '/api/projects/' . $project->id . '/members'
    );

    $response->assertUnauthorized();
}

public function test_project_owner_can_add_member(): void
{
    $owner = User::factory()->create();
    $newMember = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Add Member Project',
        'slug' => 'add-member-project',
        'status' => 'active',
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/projects/' . $project->id . '/members', [
            'user_id' => $newMember->id,
            'role' => 'developer',
        ]);

    $response->assertCreated()
        ->assertJsonPath('member.id', $newMember->id)
        ->assertJsonPath('member.role', 'developer');

    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id' => $newMember->id,
        'role' => 'developer',
    ]);
}

public function test_project_owner_cannot_add_duplicate_member(): void
{
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Duplicate Test Project',
        'slug' => 'duplicate-test-project',
        'status' => 'active',
    ]);

    $project->members()->attach($member->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/projects/' . $project->id . '/members', [
            'user_id' => $member->id,
            'role' => 'manager',
        ]);

    $response->assertStatus(409);

    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
        'role' => 'developer',
    ]);
}

public function test_project_owner_cannot_add_themselves_as_member(): void
{
    $owner = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Owner Protection Project',
        'slug' => 'owner-protection-project',
        'status' => 'active',
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/projects/' . $project->id . '/members', [
            'user_id' => $owner->id,
            'role' => 'developer',
        ]);

    $response->assertUnprocessable();

    $this->assertDatabaseMissing('project_user', [
        'project_id' => $project->id,
        'user_id' => $owner->id,
    ]);
}

public function test_project_owner_cannot_add_member_with_invalid_role(): void
{
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Invalid Role Project',
        'slug' => 'invalid-role-project',
        'status' => 'active',
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson('/api/projects/' . $project->id . '/members', [
            'user_id' => $member->id,
            'role' => 'superadmin',
        ]);

    $response->assertUnprocessable();

    $this->assertDatabaseMissing('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);
}

public function test_project_manager_cannot_add_members(): void
{
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $newMember = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Manager Restriction Project',
        'slug' => 'manager-restriction-project',
        'status' => 'active',
    ]);

    $project->members()->attach($manager->id, [
        'role' => 'manager',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson('/api/projects/' . $project->id . '/members', [
            'user_id' => $newMember->id,
            'role' => 'developer',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('project_user', [
        'project_id' => $project->id,
        'user_id' => $newMember->id,
    ]);
}



public function test_project_owner_can_update_member_role(): void
{
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Role Update Project',
        'slug' => 'role-update-project',
        'status' => 'active',
    ]);

    $project->members()->attach($member->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson('/api/projects/' . $project->id . '/members/' . $member->id, [
            'role' => 'manager',
        ]);

    $response->assertOk()
        ->assertJsonPath('member.role', 'manager');

    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
        'role' => 'manager',
    ]);
}

public function test_project_owner_cannot_update_role_of_non_member(): void
{
    $owner = User::factory()->create();
    $user = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Non Member Role Project',
        'slug' => 'non-member-role-project',
        'status' => 'active',
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson('/api/projects/' . $project->id . '/members/' . $user->id, [
            'role' => 'manager',
        ]);

    $response->assertNotFound();
}

public function test_project_owner_cannot_assign_invalid_member_role(): void
{
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Invalid Member Role Project',
        'slug' => 'invalid-member-role-project',
        'status' => 'active',
    ]);

    $project->members()->attach($member->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->patchJson('/api/projects/' . $project->id . '/members/' . $member->id, [
            'role' => 'superadmin',
        ]);

    $response->assertUnprocessable();

    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
        'role' => 'developer',
    ]);
}

public function test_project_manager_cannot_update_member_role(): void
{
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $member = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Manager Role Restriction',
        'slug' => 'manager-role-restriction',
        'status' => 'active',
    ]);

    $project->members()->attach([
        $manager->id => [
            'role' => 'manager',
            'joined_at' => now(),
        ],
        $member->id => [
            'role' => 'developer',
            'joined_at' => now(),
        ],
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->patchJson('/api/projects/' . $project->id . '/members/' . $member->id, [
            'role' => 'viewer',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
        'role' => 'developer',
    ]);
}

public function test_project_owner_can_remove_member(): void
{
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Remove Member Project',
        'slug' => 'remove-member-project',
        'status' => 'active',
    ]);

    $project->members()->attach($member->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->deleteJson('/api/projects/' . $project->id . '/members/' . $member->id);

    $response->assertOk()
        ->assertJson([
            'message' => 'Project member removed successfully.',
            'removed_user_id' => $member->id,
        ]);

    $this->assertDatabaseMissing('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $member->id,
    ]);
}

public function test_project_owner_cannot_remove_themselves(): void
{
    $owner = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Owner Protection Project',
        'slug' => 'owner-protection-project',
        'status' => 'active',
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->deleteJson('/api/projects/' . $project->id . '/members/' . $owner->id);

    $response->assertUnprocessable();
}

public function test_project_owner_cannot_remove_non_member(): void
{
    $owner = User::factory()->create();
    $user = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Non Member Removal Project',
        'slug' => 'non-member-removal-project',
        'status' => 'active',
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->deleteJson('/api/projects/' . $project->id . '/members/' . $user->id);

    $response->assertNotFound();
}

public function test_project_manager_cannot_remove_member(): void
{
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $member = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Manager Removal Restriction',
        'slug' => 'manager-removal-restriction',
        'status' => 'active',
    ]);

    $project->members()->attach([
        $manager->id => [
            'role' => 'manager',
            'joined_at' => now(),
        ],
        $member->id => [
            'role' => 'developer',
            'joined_at' => now(),
        ],
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->deleteJson('/api/projects/' . $project->id . '/members/' . $member->id);

    $response->assertForbidden();

    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);
}

public function test_global_admin_can_remove_project_member(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Admin Removal Project',
        'slug' => 'admin-removal-project',
        'status' => 'active',
    ]);

    $project->members()->attach($member->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson('/api/projects/' . $project->id . '/members/' . $member->id);

    $response->assertOk();

    $this->assertDatabaseMissing('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);
}

public function test_unauthenticated_user_cannot_remove_project_member(): void
{
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $project = Project::create([
        'owner_id' => $owner->id,
        'name' => 'Unauthenticated Removal Project',
        'slug' => 'unauthenticated-removal-project',
        'status' => 'active',
    ]);

    $project->members()->attach($member->id, [
        'role' => 'developer',
        'joined_at' => now(),
    ]);

    $response = $this->deleteJson(
        '/api/projects/' . $project->id . '/members/' . $member->id
    );

    $response->assertUnauthorized();

    $this->assertDatabaseHas('project_user', [
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);
}

}