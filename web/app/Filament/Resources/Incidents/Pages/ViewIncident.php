<?php

namespace App\Filament\Resources\Incidents\Pages;

use App\Actions\Incidents\AssignIncident;
use App\Actions\Incidents\TransitionIncident;
use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Incidents\IncidentResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ViewIncident extends ViewRecord
{
    protected static string $resource = IncidentResource::class;

    #[Locked]
    public ?string $transitionOrigin = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign')->label('Asignar técnico')
                ->visible(fn (): bool => auth()->user()->hasRole(UserRole::Admin))
                ->authorize('assign', $this->getRecord())
                ->schema([
                    Select::make('technician_id')->label('Técnico')->required()
                        ->options(fn (): array => User::where('role', UserRole::Technician)->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn () => $this->getRecord()->technician_id)->searchable(),
                ])
                ->action(function (array $data): void {
                    $this->performChange(fn () => app(AssignIncident::class)->handle(auth()->user(), $this->getRecord(), (int) $data['technician_id']));
                }),
            Action::make('selfAssign')->label('Asignarme')
                ->visible(fn (): bool => auth()->user()->hasRole(UserRole::Technician) && Gate::allows('assign', $this->getRecord()))
                ->authorize('assign', $this->getRecord())->requiresConfirmation()
                ->action(function (): void {
                    $this->performChange(fn () => app(AssignIncident::class)->handle(auth()->user(), $this->getRecord(), auth()->id()));
                }),
            Action::make('transition')->label(fn (): string => 'Pasar a '.($this->getRecord()->status->next()?->getLabel() ?? 'Cerrada'))
                ->visible(fn (): bool => $this->getRecord()->status->next() !== null && Gate::allows('transition', $this->getRecord()))
                ->authorize('transition', $this->getRecord())->requiresConfirmation()
                ->mountUsing(function (): void {
                    $this->transitionOrigin = $this->getRecord()->status->value;
                })
                ->action(function (): void {
                    $expected = IncidentStatus::from($this->transitionOrigin);
                    $this->performChange(fn () => app(TransitionIncident::class)->handle(auth()->user(), $this->getRecord(), $expected, $expected->next()));
                }),
        ];
    }

    protected function performChange(callable $change): void
    {
        try {
            $change();
        } catch (ValidationException $exception) {
            Notification::make()->title('No se pudo actualizar la incidencia')->body($exception->getMessage())->danger()->send();
            $this->getRecord()->refresh()->load(['requester', 'technician']);
            throw new Halt;
        }
        $this->getRecord()->refresh()->load(['requester', 'technician']);
        $this->dispatch('incident-updated', incidentId: $this->getRecord()->id);
        Notification::make()->title('Incidencia actualizada')->success()->send();
    }
}
