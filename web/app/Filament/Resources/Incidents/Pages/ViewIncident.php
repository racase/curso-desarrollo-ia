<?php

namespace App\Filament\Resources\Incidents\Pages;

use App\Actions\Incidents\AssignIncident;
use App\Actions\Incidents\CorrectIncidentClassification;
use App\Actions\Incidents\LinkIncidentAsset;
use App\Actions\Incidents\RateIncident;
use App\Actions\Incidents\TransitionIncident;
use App\Enums\IncidentCategory;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Incidents\IncidentResource;
use App\Models\Asset;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ViewIncident extends ViewRecord
{
    protected static string $resource = IncidentResource::class;

    #[Locked]
    public ?string $transitionOrigin = null;

    #[Unexposed]
    public function hydrate(): void
    {
        parent::hydrate();
        if (! in_array('rate', array_column($this->mountedActions, 'name'), true)) {
            return;
        }

        $incident = $this->getRecord()->refresh();
        if (auth()->user()->hasRole(UserRole::Requester) && $incident->requester_id === auth()->id()
            && $incident->resolution_rating !== null) {
            $this->unmountAction();
            Notification::make()->title('Esta incidencia ya está valorada')
                ->body('Se conserva la valoración enviada anteriormente.')->warning()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rate')->label('Valorar atención')
                ->visible(fn (): bool => Gate::allows('rate', $this->getRecord()))
                ->modalHeading('Valorar atención')->modalSubmitActionLabel('Enviar valoración definitiva')
                ->modalDescription('Elige de 1 a 5 estrellas. El envío es definitivo: no podrás editar ni eliminar la valoración.')
                ->schema([
                    Radio::make('resolution_rating')->label('Atención recibida')->required()
                        ->options([1 => '★ · 1 estrella', 2 => '★★ · 2 estrellas', 3 => '★★★ · 3 estrellas',
                            4 => '★★★★ · 4 estrellas', 5 => '★★★★★ · 5 estrellas'])
                        ->extraAttributes(['class' => 'it-rating-options']),
                ])
                ->action(function (array $data): void {
                    $this->performChange(fn () => app(RateIncident::class)->handle(auth()->user(), $this->getRecord(), $data));
                }),
            Action::make('classify')->label('Corregir clasificación')->outlined()
                ->visible(fn (): bool => Gate::allows('classify', $this->getRecord()))
                ->authorize('classify', $this->getRecord())
                ->schema([
                    Select::make('category')->label('Categoría')->options(IncidentCategory::class)->required()->native(false)
                        ->default(fn () => $this->getRecord()->category->value),
                    Select::make('priority')->label('Prioridad (urgencia)')->options(IncidentPriority::class)->required()->native(false)
                        ->default(fn () => $this->getRecord()->priority->value),
                ])
                ->action(function (array $data): void {
                    $this->performChange(fn () => app(CorrectIncidentClassification::class)->handle(auth()->user(), $this->getRecord(), $data));
                }),
            Action::make('linkAsset')->label('Vincular o corregir activo')->outlined()
                ->visible(fn (): bool => Gate::allows('linkAsset', $this->getRecord()))
                ->authorize('linkAsset', $this->getRecord())
                ->schema([
                    Select::make('asset_id')->label('Activo y sede')->required()->searchable()
                        ->options(fn (): array => Asset::groupedOptions())
                        ->default(fn () => $this->getRecord()->asset_id),
                ])
                ->action(function (array $data): void {
                    $this->performChange(fn () => app(LinkIncidentAsset::class)->handle(auth()->user(), $this->getRecord(), $data));
                }),
            Action::make('assign')->label('Asignar técnico')
                ->outlined(fn (): bool => $this->getRecord()->status->next() !== null && Gate::allows('transition', $this->getRecord()))
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
            $this->getRecord()->refresh()->load(['requester', 'technician', 'asset.site']);
            throw new Halt;
        }
        $this->getRecord()->refresh()->load(['requester', 'technician', 'asset.site']);
        $this->dispatch('incident-updated', incidentId: $this->getRecord()->id);
        Notification::make()->title('Incidencia actualizada')->success()->send();
    }
}
