<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Team;
use App\Models\Standing;
use Illuminate\Support\Collection;

class ChampionshipService
{
    private const PREDICTION_WEIGHTS = [
        'points' => 0.35,
        'position' => 0.25,
        'form' => 0.20,
        'strength' => 0.20
    ];

    public function ensureAllTeamsHaveStandings(): void
    {
        Team::all()->each(function ($team) {
            Standing::updateOrCreate(
                ['team_id' => $team->id],
                [
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'points' => 0
                ]
            );
        });
    }

    public function recalculateStandings(): void
    {
        Standing::query()->update([
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'points' => 0
        ]);

        Game::getCompletedGamesOrderedByWeek()->each(function ($game) {
            $this->updateGameStandings($game);
        });
    }

    public function getCurrentWeek(Collection $games): int
    {
        return $games->where('status', 'scheduled')->min('week') 
            ?? $games->max('week') 
            ?? 1;
    }

    public function hasScheduledGamesForWeek(Collection $games, int $week): bool
    {
        return $games->where('status', 'scheduled')
            ->where('week', $week)
            ->isNotEmpty();
    }

    public function calculateChampionshipPredictions($teams)
    {
        $completedGames = Game::where('status', 'completed')->get();
        $scheduledGames = Game::where('status', 'scheduled')->get();

        if ($completedGames->isEmpty()) {
            return $this->calculateInitialPredictions($teams);
        }

        if ($scheduledGames->isEmpty()) {
            return $this->calculateFinalStandings($teams, $completedGames);
        }

        return $this->calculateOngoingPredictions($teams, $scheduledGames);
    }

    private function updateGameStandings(Game $game): void
    {
        $homeStanding = Standing::where('team_id', $game->home_team_id)->first();
        $awayStanding = Standing::where('team_id', $game->away_team_id)->first();

        $this->updateTeamStats($homeStanding, $game->home_score, $game->away_score, true);
        $this->updateTeamStats($awayStanding, $game->away_score, $game->home_score, false);

        $homeStanding->save();
        $awayStanding->save();
    }

    private function updateTeamStats(Standing $standing, int $goalsFor, int $goalsAgainst, bool $isHome): void
    {
        $standing->played++;
        $standing->goals_for += $goalsFor;
        $standing->goals_against += $goalsAgainst;

        if ($goalsFor > $goalsAgainst) {
            $standing->won++;
            $standing->points += 3;
        } elseif ($goalsFor === $goalsAgainst) {
            $standing->drawn++;
            $standing->points++;
        } else {
            $standing->lost++;
        }
    }

    private function calculateInitialPredictions($teams): array
    {
        $totalStrength = $teams->sum('strength');
        $predictions = [];

        foreach ($teams as $team) {
            $predictions[$team->id] = round(($team->strength / $totalStrength) * 100, 1);
        }

        return $predictions;
    }

    private function calculateFinalStandings($teams, $completedGames): array
    {
        $standings = $this->getStandingsData($teams);
        $leaderPoints = $standings[0]['points'];
        $champions = $standings->filter(fn($s) => $s['points'] === $leaderPoints);

        if ($champions->count() === 1) {
            return $this->createSingleChampionPredictions($teams, $leaderPoints);
        }

        return $this->resolveTiebreaker($teams, $champions->pluck('team_id')->toArray(), $completedGames);
    }

    private function createSingleChampionPredictions($teams, $leaderPoints): array
    {
        $predictions = [];
        foreach ($teams as $team) {
            $predictions[$team->id] = ($team->standing && $team->standing->points === $leaderPoints) ? 100 : 0;
        }
        return $predictions;
    }

    private function resolveTiebreaker($teams, $championIds, $completedGames): array
    {
        $comparisonResults = $this->calculateTiebreakerStats($championIds, $completedGames);
        $sortedChampions = $this->sortByTiebreakers($championIds, $comparisonResults);
        
        $predictions = [];
        foreach ($teams as $team) {
            $predictions[$team->id] = ($team->id === $sortedChampions[0]) ? 100 : 0;
        }
        
        return $predictions;
    }

    private function calculateTiebreakerStats($championIds, $completedGames): array
    {
        $results = [];
        foreach ($championIds as $teamId) {
            $team = Team::find($teamId);
            $h2hStats = $this->calculateHeadToHeadStats($teamId, $championIds, $completedGames);
            
            $results[$teamId] = array_merge([
                'goalDiff' => $team->standing->goals_for - $team->standing->goals_against,
                'goalsScored' => $team->standing->goals_for
            ], $h2hStats);
        }
        return $results;
    }

    private function calculateHeadToHeadStats($teamId, $opposingIds, $completedGames): array
    {
        $stats = ['h2hPoints' => 0, 'h2hGoalDiff' => 0, 'h2hGoalsScored' => 0];

        foreach ($opposingIds as $opposingId) {
            if ($teamId === $opposingId) continue;

            $h2hGames = $this->getHeadToHeadGames($teamId, $opposingId, $completedGames);
            foreach ($h2hGames as $game) {
                $this->updateHeadToHeadStats($stats, $game, $teamId);
            }
        }

        return $stats;
    }

    private function getHeadToHeadGames($teamId, $opposingId, $completedGames): Collection
    {
        return $completedGames->filter(function ($game) use ($teamId, $opposingId) {
            return ($game->home_team_id === $teamId && $game->away_team_id === $opposingId) ||
                ($game->away_team_id === $teamId && $game->home_team_id === $opposingId);
        });
    }

    private function updateHeadToHeadStats(array &$stats, Game $game, int $teamId): void
    {
        if ($game->home_team_id === $teamId) {
            $goalDiff = $game->home_score - $game->away_score;
            $stats['h2hGoalsScored'] += $game->home_score;
        } else {
            $goalDiff = $game->away_score - $game->home_score;
            $stats['h2hGoalsScored'] += $game->away_score;
        }

        $stats['h2hGoalDiff'] += $goalDiff;
        $stats['h2hPoints'] += $this->calculatePoints($goalDiff);
    }

    private function calculatePoints(int $goalDiff): int
    {
        if ($goalDiff > 0) return 3;
        if ($goalDiff === 0) return 1;
        return 0;
    }

    private function sortByTiebreakers($championIds, $comparisonResults): array
    {
        usort($championIds, function($a, $b) use ($comparisonResults) {
            $tiebreakers = ['h2hPoints', 'h2hGoalDiff', 'h2hGoalsScored', 'goalDiff', 'goalsScored'];
            
            foreach ($tiebreakers as $tiebreaker) {
                $diff = $comparisonResults[$b][$tiebreaker] - $comparisonResults[$a][$tiebreaker];
                if ($diff !== 0) return $diff;
            }
            
            return 0;
        });

        return $championIds;
    }

    private function calculateOngoingPredictions($teams, $scheduledGames): array
    {
        $standings = $this->getStandingsData($teams);
        $leaderPoints = $standings[0]['points'];
        $predictions = [];

        foreach ($teams as $team) {
            $predictions[$team->id] = $this->calculateTeamPrediction(
                $team,
                $standings->firstWhere('team_id', $team->id),
                $leaderPoints,
                $scheduledGames
            );
        }

        return $this->normalizePredictions($predictions);
    }

    private function calculateTeamPrediction($team, $standing, $leaderPoints, $scheduledGames): float
    {
        $currentPoints = $standing['points'];
        $pointsFromLeader = $leaderPoints - $currentPoints;
        $remainingGames = $this->getRemainingGames($team->id, $scheduledGames);

        if ($this->cannotCatchUp($currentPoints, $remainingGames, $leaderPoints)) {
            return 0;
        }

        return $this->calculateWeightedScore($team, $standing, $pointsFromLeader, $remainingGames);
    }

    private function getRemainingGames($teamId, $scheduledGames): int
    {
        return $scheduledGames->filter(function ($game) use ($teamId) {
            return $game->home_team_id === $teamId || $game->away_team_id === $teamId;
        })->count();
    }

    private function cannotCatchUp($currentPoints, $remainingGames, $leaderPoints): bool
    {
        return ($currentPoints + ($remainingGames * 3)) < $leaderPoints;
    }

    private function calculateWeightedScore($team, $standing, $pointsFromLeader, $remainingGames): float
    {
        $factors = [
            'points' => $standing['points'] / ($standing['points'] ?: 1),
            'position' => 1 - ($pointsFromLeader / (($remainingGames * 3) ?: 1)),
            'form' => $standing['form'],
            'strength' => $team->strength / 100
        ];

        $weightedScore = 0;
        foreach ($factors as $factor => $value) {
            $weightedScore += $value * self::PREDICTION_WEIGHTS[$factor];
        }

        $catchupPenalty = max(0.1, 1 - ($pointsFromLeader / (($remainingGames * 3) ?: 1)));
        return $weightedScore * $catchupPenalty * 100;
    }

    private function normalizePredictions(array $predictions): array
    {
        $total = array_sum($predictions);
        if ($total <= 0) return $predictions;

        return array_map(function ($probability) use ($total) {
            return round(($probability / $total) * 100, 1);
        }, $predictions);
    }

    private function getStandingsData($teams): Collection
    {
        return collect($teams)->map(function ($team) {
            return [
                'team_id' => $team->id,
                'points' => $team->standing ? $team->standing->points : 0,
                'goalDiff' => $team->standing ? ($team->standing->goals_for - $team->standing->goals_against) : 0,
                'goalsScored' => $team->standing ? $team->standing->goals_for : 0,
                'strength' => $team->strength,
                'form' => $this->calculateFormFactor($team)
            ];
        })->sortByDesc('points')->values();
    }

    private function calculateFormFactor($team): float
    {
        if (!$team->standing) return 0;
        if ($team->standing->played === 0) return 0.5;

        $winRatio = $team->standing->won / $team->standing->played;
        $goalDifferencePerGame = ($team->standing->goals_for - $team->standing->goals_against) / $team->standing->played;
        $goalFactor = max(-0.5, min(0.5, $goalDifferencePerGame / 3));

        return ($winRatio + $goalFactor + 1) / 2;
    }

    private function generateScore(Team $team): int
    {
        // Base score between 0-3
        $baseScore = random_int(0, 3);
        
        // Add strength bonus (0-3 extra goals based on strength)
        $strengthBonus = floor($team->strength / 20); // Increased strength impact
        
        // Always apply some strength bonus for strong teams
        $minBonus = floor($team->strength / 50); // Minimum bonus based on strength
        $extraBonus = $strengthBonus - $minBonus;
        
        // Apply minimum bonus always, and extra bonus with probability
        $score = $baseScore + $minBonus;
        
        // 80% chance to apply extra bonus for strong teams
        $strengthChance = min(80, floor($team->strength / 1.5));
        if (random_int(1, 100) <= $strengthChance) {
            $score += $extraBonus;
        }
        
        return min(5, $score);
    }
} 