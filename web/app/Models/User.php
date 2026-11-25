<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Every role can sign in to the panel. What each role can see or do
     * inside it is decided feature by feature.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function requestedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'requester_id');
    }

    public function assignedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'technician_id');
    }

    public function incidentComments(): HasMany
    {
        return $this->hasMany(IncidentComment::class, 'author_id');
    }

    public function closedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'closing_technician_id');
    }

    public function incidentStatusHistory(): HasMany
    {
        return $this->hasMany(IncidentStatusHistory::class, 'actor_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
