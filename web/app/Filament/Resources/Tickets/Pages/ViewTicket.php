<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\TransitionTicket;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('assign')->label('Asignar técnico')
                ->authorize(fn (): bool => auth()->user()->can('assign', $this->getRecord()))
                ->fillForm(fn (): array => ['technician_id' => $this->getRecord()->technician_id, 'tracking_version' => $this->getRecord()->tracking_version])
                ->schema([
                    Select::make('technician_id')->label('Técnico')->options(fn () => User::where('role', UserRole::Technician)->orderBy('name')->pluck('name', 'id'))->searchable()
                        ->required(fn (): bool => ! in_array($this->getRecord()->status, [TicketStatus::Proposed, TicketStatus::Open], true)),
                    Hidden::make('tracking_version')->required()->rules(['integer', 'min:0']),
                ])
                ->action(function (array $data): void {
                    $this->runTrackingAction(fn () => app(AssignTicket::class)->handle(auth()->user(), $this->getRecord(), isset($data['technician_id']) ? (int) $data['technician_id'] : null, (int) $data['tracking_version']));
                }),
            Action::make('transition')->label('Avanzar estado')
                ->authorize(fn (): bool => auth()->user()->can('transition', $this->getRecord()))
                ->fillForm(fn (): array => ['status' => $this->getRecord()->status->next()?->value, 'tracking_version' => $this->getRecord()->tracking_version])
                ->schema([
                    Select::make('status')->label('Nuevo estado')->required()->options(fn (): array => ($next = $this->getRecord()->status->next()) ? [$next->value => $next->getLabel()] : []),
                    Hidden::make('tracking_version')->required()->rules(['integer', 'min:0']),
                ])
                ->action(function (array $data): void {
                    $this->runTrackingAction(fn () => app(TransitionTicket::class)->handle(auth()->user(), $this->getRecord(), TicketStatus::from($data['status']), (int) $data['tracking_version']));
                }),
        ];
    }

    private function runTrackingAction(callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
            $statePath = $this->getMountedActionSchema()->getStatePath();
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field): array => [$statePath.'.'.$field => $messages])->all());
        }
        $this->getRecord()->refresh();
        $this->dispatch('ticket-updated');
        Notification::make()->success()->title('Incidencia actualizada')->send();
    }
}
