<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'client_id',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'budget_cents',
        'currency_code',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget_cents' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
