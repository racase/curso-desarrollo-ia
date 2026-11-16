<?php

namespace App\Filament\Resources\Tickets\RelationManagers\Concerns;

use Livewire\Attributes\On;

trait RefreshesTicket
{
    #[On('ticket-updated')]
    public function refreshTicket(): void
    {
        $this->getOwnerRecord()->refresh();
        $this->resetTable();
    }
}
