<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['site_id', 'code', 'name'])]
class Asset extends Model
{
    use HasFactory;

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function label(): string
    {
        return "{$this->code} · {$this->name} · {$this->site->code} · {$this->site->name}";
    }

    public static function groupedOptions(): array
    {
        return static::query()->with('site')->orderBy('code')->get()->groupBy('site.code')
            ->map(fn ($assets): array => $assets->mapWithKeys(fn (Asset $asset): array => [$asset->id => $asset->label()])->all())->all();
    }
}
