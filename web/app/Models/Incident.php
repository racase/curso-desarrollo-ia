<?php

namespace App\Models;

use App\Enums\IncidentCategory;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'status', 'category', 'priority', 'requester_id', 'technician_id', 'asset_id'])]
class Incident extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => IncidentStatus::class, 'category' => IncidentCategory::class, 'priority' => IncidentPriority::class];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when($user->hasRole(UserRole::Requester), fn (Builder $query) => $query->where('requester_id', $user->id));
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(IncidentStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IncidentComment::class)->orderBy('created_at')->orderBy('id');
    }
}
