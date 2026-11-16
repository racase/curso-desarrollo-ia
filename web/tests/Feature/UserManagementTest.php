<?php

namespace Tests\Feature;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatusChange;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function data(User $user, array $overrides = []): array
    {
        return array_replace(['name' => $user->name, 'email' => $user->email, 'role' => $user->role->value], $overrides);
    }

    public function test_creation_normalizes_identity_and_hashes_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = app(CreateUser::class)->handle($admin, ['name' => '  Nombre  ', 'email' => '  NUEVO@example.com ', 'role' => 'technician', 'password' => 'a-long-password']);
        $this->assertSame('Nombre', $user->name);
        $this->assertSame('nuevo@example.com', $user->email);
        $this->assertSame(UserRole::Technician, $user->role);
        $this->assertTrue(Hash::check('a-long-password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertTrue(auth()->attempt(['email' => $user->email, 'password' => 'a-long-password']));
    }

    public function test_validation_rejects_invalid_fields(): void
    {
        $admin = User::factory()->admin()->create();
        foreach ([['name' => '  '], ['name' => str_repeat('x', 256)], ['email' => 'invalid'], ['email' => strtoupper($admin->email)], ['role' => 'unknown'], ['password' => 'short']] as $invalid) {
            try {
                app(CreateUser::class)->handle($admin, array_replace(['name' => 'Nuevo', 'email' => 'new@example.com', 'role' => 'requester', 'password' => 'long-password'], $invalid));
                $this->fail('Invalid user accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey(array_key_first($invalid), $exception->errors());
            }
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_edit_preserves_or_replaces_password_and_updates_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $hash = $user->password;
        $updated = app(UpdateUser::class)->handle($admin, $user, $this->data($user, ['name' => 'Editado', 'password' => '']));
        $this->assertSame($hash, $updated->password);
        $updated = app(UpdateUser::class)->handle($admin, $user, $this->data($user, ['role' => 'technician', 'password' => 'new-long-password']));
        $this->assertTrue(Hash::check('new-long-password', $updated->password));
        $this->assertFalse(Hash::check('password', $updated->password));
        $this->assertSame(UserRole::Technician, $updated->role);
    }

    public function test_non_admins_cannot_mutate_users(): void
    {
        $target = User::factory()->create();
        foreach ([User::factory()->create(), User::factory()->technician()->create()] as $actor) {
            foreach ([fn () => app(CreateUser::class)->handle($actor, []), fn () => app(UpdateUser::class)->handle($actor, $target, $this->data($target)), fn () => app(DeleteUser::class)->handle($actor, $target)] as $operation) {
                try {
                    $operation();
                    $this->fail('Unauthorized operation accepted');
                } catch (AuthorizationException) {
                    $this->assertModelExists($target);
                }
            }
        }
    }

    public function test_last_admin_cannot_be_deleted_or_demoted(): void
    {
        $admin = User::factory()->admin()->create();
        foreach ([fn () => app(DeleteUser::class)->handle($admin, $admin), fn () => app(UpdateUser::class)->handle($admin, $admin, $this->data($admin, ['role' => 'requester']))] as $operation) {
            try {
                $operation();
                $this->fail('Last administrator removed');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('role', $exception->errors());
            }
        }
        User::factory()->admin()->create();
        app(UpdateUser::class)->handle($admin, $admin, $this->data($admin, ['role' => 'requester']));
        $this->assertSame(UserRole::Requester, $admin->refresh()->role);
    }

    public function test_referenced_users_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $requester = Ticket::factory()->create()->requester;
        $technician = User::factory()->technician()->create();
        Ticket::factory()->create(['technician_id' => $technician->id, 'status' => 'closed']);
        $commenter = TicketComment::factory()->create()->author;
        $historian = TicketStatusChange::factory()->create()->author;
        foreach ([$requester, $technician, $commenter, $historian] as $user) {
            try {
                app(DeleteUser::class)->handle($admin, $user);
                $this->fail('Referenced user deleted');
            } catch (ValidationException) {
                $this->assertModelExists($user);
            }
        }
        $unreferenced = User::factory()->create();
        app(DeleteUser::class)->handle($admin, $unreferenced);
        $this->assertModelMissing($unreferenced);
    }

    public function test_technician_role_requires_reassignment_or_closure(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $ticket = Ticket::factory()->create(['technician_id' => $technician->id]);
        try {
            app(UpdateUser::class)->handle($admin, $technician, $this->data($technician, ['role' => 'requester']));
            $this->fail('Assigned technician demoted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
        $ticket->forceFill(['status' => 'closed'])->save();
        app(UpdateUser::class)->handle($admin, $technician, $this->data($technician, ['role' => 'requester']));
        $this->assertSame($technician->id, $ticket->refresh()->technician_id);
    }

    public function test_stale_administrator_cannot_perform_operations(): void
    {
        $admin = User::factory()->admin()->create();
        $stale = clone $admin;
        $admin->update(['role' => 'requester']);
        $this->expectException(AuthorizationException::class);
        app(CreateUser::class)->handle($stale, []);
    }
}
