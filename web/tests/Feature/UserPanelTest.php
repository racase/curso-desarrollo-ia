<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_edits_and_deletes_through_the_panel(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create());
        Livewire::test(CreateUser::class)->fillForm([
            'name' => 'Nueva', 'email' => 'panel@infraticket.test', 'role' => 'requester',
            'password' => 'clave-panel-larga', 'password_confirmation' => 'clave-panel-larga',
        ])->call('create')->assertHasNoFormErrors();
        $user = User::where('email', 'panel@infraticket.test')->sole();
        $hash = $user->password;
        Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$admin, $user])->assertTableActionExists('delete');
        Livewire::test(EditUser::class, ['record' => $user->id])->assertFormSet(['password' => ''])
            ->assertDontSee($hash)->fillForm(['name' => 'Editada'])->call('save')->assertHasNoFormErrors();
        $this->assertSame($hash, $user->fresh()->password);
        Livewire::test(EditUser::class, ['record' => $user->id])->fillForm([
            'password' => 'otra-clave-panel', 'password_confirmation' => 'otra-clave-panel',
        ])->call('save')->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('otra-clave-panel', $user->fresh()->password));
        Livewire::test(EditUser::class, ['record' => $user->id])->callAction('delete');
        $this->assertModelMissing($user);
        Livewire::test(EditUser::class, ['record' => $admin->id])->callAction('delete')
            ->assertNotified('No se pudo eliminar el usuario');
        $this->assertModelExists($admin);
    }

    public function test_validation_and_role_changes_are_enforced_in_the_panel(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create());
        Livewire::test(CreateUser::class)->fillForm([
            'name' => ' ', 'email' => $admin->email, 'role' => 'requester',
            'password' => 'clave-panel-larga', 'password_confirmation' => 'clave-panel-larga',
        ])->call('create')->assertHasFormErrors(['name']);
        Livewire::test(CreateUser::class)->fillForm([
            'name' => 'Cuenta', 'email' => $admin->email, 'role' => 'requester',
            'password' => 'clave-panel-larga', 'password_confirmation' => 'clave-panel-larga',
        ])->call('create')->assertHasFormErrors(['email']);
        Livewire::test(EditUser::class, ['record' => $admin->id])->fillForm(['role' => 'requester'])
            ->call('save')->assertHasFormErrors(['role']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_guests_and_non_admins_cannot_open_user_pages_or_components(): void
    {
        $user = User::factory()->create();
        foreach (['/admin/users', '/admin/users/create', '/admin/users/'.$user->id.'/edit'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }
        foreach ([$user, User::factory()->technician()->create()] as $actor) {
            $this->actingAs($actor);
            foreach (['/admin/users', '/admin/users/create', '/admin/users/'.$user->id.'/edit'] as $url) {
                $this->get($url)->assertForbidden();
            }
            Livewire::test(ListUsers::class)->assertForbidden();
            Livewire::test(CreateUser::class)->assertForbidden();
            Livewire::test(EditUser::class, ['record' => $user->id])->assertForbidden();
            $this->get('/admin')->assertDontSee('href="http://localhost/admin/users"', false);
        }
    }
}
