<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'site_id'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function label(): string
    {
        return $this->code.' — '.$this->name.' ('.$this->site->name.')';
    }
}
