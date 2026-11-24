<?php

namespace Tests\Feature;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function input(array $overrides = []): array
    {
        return [...['name' => '  Nueva Cuenta ', 'email' => ' NUEVA@infraticket.test ', 'role' => 'requester',
            'password' => 'una-clave-larga', 'password_confirmation' => 'una-clave-larga'], ...$overrides];
    }

    public function test_admin_can_create_update_password_and_delete_an_unreferenced_account(): void
    {
        $admin = User::factory()->admin()->create();
        $user = app(CreateUser::class)->handle($admin, $this->input());
        $this->assertSame('Nueva Cuenta', $user->name);
        $this->assertSame('nueva@infraticket.test', $user->email);
        $this->assertTrue(Hash::check('una-clave-larga', $user->password));
        $oldHash = $user->password;
        $user = app(UpdateUser::class)->handle($admin, $user, $this->input(['password' => '', 'role' => 'technician']));
        $this->assertSame($oldHash, $user->password);
        $this->assertSame(UserRole::Technician, $user->role);
        $user = app(UpdateUser::class)->handle($admin, $user, $this->input(['password' => 'otra-clave-larga', 'password_confirmation' => 'otra-clave-larga']));
        $this->assertTrue(Hash::check('otra-clave-larga', $user->password));
        $this->assertFalse(Hash::check('una-clave-larga', $user->password));
        app(DeleteUser::class)->handle($admin, $user);
        $this->assertModelMissing($user);
    }

    public function test_non_admins_and_stale_actors_cannot_mutate_accounts(): void
    {
        $target = User::factory()->create();
        $stale = User::factory()->admin()->create();
        User::whereKey($stale->id)->update(['role' => UserRole::Requester]);
        foreach ([User::factory()->create(), User::factory()->technician()->create(), $stale] as $actor) {
            foreach ([fn () => app(CreateUser::class)->handle($actor, $this->input()),
                fn () => app(UpdateUser::class)->handle($actor, $target, $this->input()),
                fn () => app(DeleteUser::class)->handle($actor, $target)] as $operation) {
                try {
                    $operation();
                    $this->fail('Unauthorized mutation accepted');
                } catch (AuthorizationException) {
                    $this->assertModelExists($target);
                }
            }
        }
        $this->assertFalse(Gate::allows('viewAny', User::class));
        $this->assertDatabaseMissing('users', ['email' => 'nueva@infraticket.test']);
    }

    public function test_invalid_accounts_are_atomic(): void
    {
        $admin = User::factory()->admin()->create();
        foreach ([['name' => ' '], ['name' => str_repeat('a', 256)], ['email' => 'bad'], ['email' => strtoupper($admin->email)],
            ['role' => 'unknown'], ['password' => 'short'], ['password_confirmation' => 'different']] as $invalid) {
            try {
                app(CreateUser::class)->handle($admin, $this->input($invalid));
                $this->fail('Invalid account accepted');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_self_deletion_last_admin_and_assigned_technician_are_protected(): void
    {
        $admin = User::factory()->admin()->create();
        $tech = User::factory()->technician()->create();
        Incident::factory()->create(['technician_id' => $tech->id, 'status' => 'closed']);
        foreach ([fn () => app(DeleteUser::class)->handle($admin, $admin),
            fn () => app(UpdateUser::class)->handle($admin, $admin, $this->input()),
            fn () => app(UpdateUser::class)->handle($admin, $tech, $this->input())] as $operation) {
            try {
                $operation();
                $this->fail('Protected mutation accepted');
            } catch (ValidationException) {
                $this->assertSame(UserRole::Admin, $admin->fresh()->role);
                $this->assertSame(UserRole::Technician, $tech->fresh()->role);
            }
        }
        Incident::query()->update(['technician_id' => User::factory()->technician()->create()->id]);
        app(UpdateUser::class)->handle($admin, $tech, $this->input());
        User::factory()->admin()->create();
        app(UpdateUser::class)->handle($admin, $admin, $this->input(['email' => $admin->email]));
        $this->assertSame(UserRole::Requester, $admin->fresh()->role);
        $this->actingAs($admin->fresh())->get('/admin/users')->assertForbidden();
    }

    public function test_all_four_user_references_prevent_deletion(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->assigned()->create();
        $comment = IncidentComment::factory()->create();
        $history = IncidentStatusHistory::factory()->create();
        foreach ([$incident->requester, $incident->technician, $comment->author, $history->actor] as $user) {
            try {
                app(DeleteUser::class)->handle($admin, $user);
                $this->fail('Referenced account deleted');
            } catch (ValidationException) {
                $this->assertModelExists($user);
            }
        }
        $this->assertModelExists($incident);
        $this->assertModelExists($comment);
        $this->assertModelExists($history);
    }
}
