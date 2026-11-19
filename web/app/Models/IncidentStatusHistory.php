<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['incident_id', 'actor_id', 'from_status', 'to_status'])]
class IncidentStatusHistory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['from_status' => IncidentStatus::class, 'to_status' => IncidentStatus::class];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
