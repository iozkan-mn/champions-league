<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    protected $fillable = [
        'home_team_id',
        'away_team_id',
        'home_score',
        'away_score',
        'match_date',
        'status',
        'week'
    ];

    protected $casts = [
        'match_date' => 'datetime',
    ];

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForWeek($query, int $week)
    {
        return $query->where('week', $week);
    }

    public function scopeOrderedByWeek($query)
    {
        return $query->orderBy('week');
    }

    public function scopeOrderedByDate($query)
    {
        return $query->orderBy('match_date');
    }

    public static function getScheduledGamesForWeek(int $week)
    {
        return static::scheduled()
            ->forWeek($week)
            ->get();
    }

    public static function getScheduledGamesOrderedByWeek()
    {
        return static::scheduled()
            ->orderedByWeek()
            ->get();
    }

    public static function getCompletedGamesOrderedByWeek()
    {
        return static::completed()
            ->with(['homeTeam', 'awayTeam'])
            ->orderedByWeek()
            ->get();
    }

    public static function getAllGamesOrdered()
    {
        return static::with(['homeTeam', 'awayTeam'])
            ->orderedByWeek()
            ->orderedByDate()
            ->get();
    }
}
