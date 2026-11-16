<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_create_edit_and_delete_user(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(CreateUser::class)->fillForm(['name' => 'Nuevo', 'email' => 'new@example.com', 'role' => 'requester', 'password' => 'long-password'])->call('create')->assertHasNoFormErrors();
        $user = User::where('email', 'new@example.com')->sole();
        Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$user]);
        Livewire::test(EditUser::class, ['record' => $user->id])->assertFormSet(['password' => ''])->fillForm(['name' => 'Corregido'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Corregido', $user->refresh()->name);
        Livewire::test(ViewUser::class, ['record' => $user->id])->callAction('delete');
        $this->assertModelMissing($user);
    }

    public function test_last_administrator_delete_and_edit_show_explanations(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Livewire::test(EditUser::class, ['record' => $admin->id])->fillForm(['role' => 'requester'])->call('save')->assertHasFormErrors(['role']);
        Livewire::test(ViewUser::class, ['record' => $admin->id])->callAction('delete')->assertNotified('No se puede eliminar');
        $this->assertModelExists($admin);
    }

    public function test_form_validation_maps_action_errors_to_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(CreateUser::class)->fillForm(['name' => '  ', 'email' => 'new@example.com', 'role' => 'requester', 'password' => 'long-password'])->call('create')->assertHasFormErrors(['name']);
    }

    public function test_other_roles_and_guests_cannot_access_user_management(): void
    {
        $record = User::factory()->create();
        $this->get(UserResource::getUrl())->assertRedirect('/admin/login');
        foreach ([User::factory()->create(), User::factory()->technician()->create()] as $actor) {
            $this->actingAs($actor);
            $this->assertFalse(UserResource::shouldRegisterNavigation());
            Livewire::test(ListUsers::class)->assertForbidden();
            Livewire::test(CreateUser::class)->assertForbidden();
            Livewire::test(EditUser::class, ['record' => $record->id])->assertForbidden();
            Livewire::test(ViewUser::class, ['record' => $record->id])->assertForbidden();
        }
    }
}
