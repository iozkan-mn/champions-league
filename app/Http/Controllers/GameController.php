<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Team;
use App\Models\Standing;
use App\Services\FixtureService;
use Illuminate\Http\Request;

class GameController extends Controller
{
    protected $fixtureService;

    public function __construct(FixtureService $fixtureService)
    {
        $this->fixtureService = $fixtureService;
    }

    public function generateFixtures()
    {
        $this->fixtureService->generateFixtures();
        return redirect()->route('home')->with('success', 'Fixtures generated successfully');
    }

    /**
     * Reset all data and start fresh
     */
    public function resetData()
    {
        // Clear all games and standings
        Game::truncate();
        Standing::truncate();

        return redirect()->route('home')->with('success', 'All data has been reset');
    }

    /**
     * Simulate all remaining weeks at once
     */
    public function playAllWeeks()
    {
        $games = Game::where('status', 'scheduled')
            ->orderBy('week')
            ->get();

        if ($games->isEmpty()) {
            return redirect()->route('home')
                ->with('error', 'There are no scheduled games to simulate.');
        }

        // Group games by week
        $gamesByWeek = $games->groupBy('week');

        // Simulate each week in order
        foreach ($gamesByWeek as $week => $weekGames) {
            $this->fixtureService->simulateWeek($week);
        }

        return redirect()->route('home')
            ->with('success', 'All weeks have been simulated successfully');
    }

    /**
     * Simulate matches for a specific week
     */
    public function simulateWeek(Request $request)
    {
        $week = $request->input('week');

        // Get all games for the specified week
        $games = Game::where('week', $week)
            ->where('status', 'scheduled')
            ->get();

        if ($games->isEmpty()) {
            return redirect()->route('home')
                ->with('error', 'There are no scheduled games for this week.');
        }

        // Simulate the games for this week
        $this->fixtureService->simulateWeek($week);

        // Get all teams and ensure they have a standing record
        $teams = Team::all();
        foreach ($teams as $team) {
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
        }

        // Recalculate all standings from completed games
        $completedGames = Game::where('status', 'completed')
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->get();

        foreach ($completedGames as $game) {
            // Update home team standing
            $homeStanding = Standing::where('team_id', $game->home_team_id)->first();
            $homeStanding->played++;
            $homeStanding->goals_for += $game->home_score;
            $homeStanding->goals_against += $game->away_score;

            if ($game->home_score > $game->away_score) {
                $homeStanding->won++;
                $homeStanding->points += 3;
            } elseif ($game->home_score === $game->away_score) {
                $homeStanding->drawn++;
                $homeStanding->points++;
            } else {
                $homeStanding->lost++;
            }
            $homeStanding->save();

            // Update away team standing
            $awayStanding = Standing::where('team_id', $game->away_team_id)->first();
            $awayStanding->played++;
            $awayStanding->goals_for += $game->away_score;
            $awayStanding->goals_against += $game->home_score;

            if ($game->away_score > $game->home_score) {
                $awayStanding->won++;
                $awayStanding->points += 3;
            } elseif ($game->away_score === $game->home_score) {
                $awayStanding->drawn++;
                $awayStanding->points++;
            } else {
                $awayStanding->lost++;
            }
            $awayStanding->save();
        }

        // Get fresh data
        $games = Game::with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->orderBy('match_date')
            ->get();

        $teams = Team::with('standing')->get();

        // Calculate current week and other data
        $currentWeek = $games->where('status', 'scheduled')->min('week');
        if ($currentWeek === null) {
            $currentWeek = $games->max('week') ?? 1;
        }

        $hasScheduledGames = $games->where('status', 'scheduled')
            ->where('week', $currentWeek)
            ->isNotEmpty();

        // Calculate predictions
        $predictions = null;
        if ($completedGames->isNotEmpty()) {
            $predictions = $this->calculateChampionshipPredictions($teams);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'games' => $games,
                'teams' => $teams,
                'currentWeek' => $currentWeek,
                'hasScheduledGames' => $hasScheduledGames,
                'predictions' => $predictions,
                'message' => 'Week ' . $week . ' matches simulated successfully'
            ]);
        }

        return redirect()->route('home')
            ->with('success', 'Week ' . $week . ' matches simulated successfully');
    }

    public function calculateChampionshipPredictions($teams)
    {
        $predictions = [];

        // Get completed and scheduled games
        $completedGames = Game::where('status', 'completed')->get();
        $scheduledGames = Game::where('status', 'scheduled')->get();

        // Calculate total weeks based on team count

        // If no games have been played yet, calculate based on team strengths only
        if ($completedGames->isEmpty()) {
            $totalStrength = $teams->sum('strength');
            foreach ($teams as $team) {
                $predictions[$team->id] = round(($team->strength / $totalStrength) * 100, 1);
            }
            return $predictions;
        }

        // Get current week and remaining weeks

        // Get current standings sorted by points
        $standings = collect($teams)->map(function ($team) {
            return [
                'team_id' => $team->id,
                'points' => $team->standing ? $team->standing->points : 0,
                'goalDiff' => $team->standing ? ($team->standing->goals_for - $team->standing->goals_against) : 0,
                'goalsScored' => $team->standing ? $team->standing->goals_for : 0,
                'strength' => $team->strength,
                'form' => $this->calculateFormFactor($team)
            ];
        })->sortByDesc('points')->values();

        // Get leader's points
        $leaderPoints = $standings[0]['points'];

        // If season is finished (no scheduled games)
        if ($scheduledGames->isEmpty()) {
            // Find teams tied for first
            $champions = $standings->filter(fn($s) => $s['points'] === $leaderPoints);

            // If only one champion, they get 100%
            if ($champions->count() === 1) {
                foreach ($teams as $team) {
                    $predictions[$team->id] = ($team->standing && $team->standing->points === $leaderPoints) ? 100 : 0;
                }
                return $predictions;
            }

            // If multiple teams tied, use tiebreakers
            $championIds = $champions->pluck('team_id')->toArray();
            $comparisonResults = [];

            foreach ($championIds as $teamId) {
                $team = $teams->firstWhere('id', $teamId);
                $goalDiff = $team->standing->goals_for - $team->standing->goals_against;
                $goalsScored = $team->standing->goals_for;

                // Calculate head-to-head stats
                $h2hPoints = 0;
                $h2hGoalDiff = 0;
                $h2hGoalsScored = 0;

                foreach ($championIds as $opposingTeamId) {
                    if ($teamId === $opposingTeamId) {
                        continue;
                    }

                    $h2hGames = $completedGames->filter(function ($game) use ($teamId, $opposingTeamId) {
                        return ($game->home_team_id === $teamId && $game->away_team_id === $opposingTeamId) ||
                               ($game->away_team_id === $teamId && $game->home_team_id === $opposingTeamId);
                    });

                    foreach ($h2hGames as $game) {
                        if ($game->home_team_id === $teamId) {
                            $h2hGoalDiff += $game->home_score - $game->away_score;
                            $h2hGoalsScored += $game->home_score;
                            if ($game->home_score > $game->away_score) {
                                $h2hPoints += 3;
                            } elseif ($game->home_score === $game->away_score) {
                                $h2hPoints += 1;
                            }
                        } else {
                            $h2hGoalDiff += $game->away_score - $game->home_score;
                            $h2hGoalsScored += $game->away_score;
                            if ($game->away_score > $game->home_score) {
                                $h2hPoints += 3;
                            } elseif ($game->away_score === $game->home_score) {
                                $h2hPoints += 1;
                            }
                        }
                    }
                }

                $comparisonResults[$teamId] = [
                    'h2hPoints' => $h2hPoints,
                    'h2hGoalDiff' => $h2hGoalDiff,
                    'h2hGoalsScored' => $h2hGoalsScored,
                    'goalDiff' => $goalDiff,
                    'goalsScored' => $goalsScored
                ];
            }

            // Sort champions by tiebreakers
            usort($championIds, function($a, $b) use ($comparisonResults) {
                // 1. Head-to-head points
                if ($comparisonResults[$a]['h2hPoints'] !== $comparisonResults[$b]['h2hPoints']) {
                    return $comparisonResults[$b]['h2hPoints'] - $comparisonResults[$a]['h2hPoints'];
                }
                // 2. Head-to-head goal difference
                if ($comparisonResults[$a]['h2hGoalDiff'] !== $comparisonResults[$b]['h2hGoalDiff']) {
                    return $comparisonResults[$b]['h2hGoalDiff'] - $comparisonResults[$a]['h2hGoalDiff'];
                }
                // 3. Head-to-head goals scored
                if ($comparisonResults[$a]['h2hGoalsScored'] !== $comparisonResults[$b]['h2hGoalsScored']) {
                    return $comparisonResults[$b]['h2hGoalsScored'] - $comparisonResults[$a]['h2hGoalsScored'];
                }
                // 4. Overall goal difference
                if ($comparisonResults[$a]['goalDiff'] !== $comparisonResults[$b]['goalDiff']) {
                    return $comparisonResults[$b]['goalDiff'] - $comparisonResults[$a]['goalDiff'];
                }
                // 5. Overall goals scored
                return $comparisonResults[$b]['goalsScored'] - $comparisonResults[$a]['goalsScored'];
            });

            foreach ($teams as $team) {
                $predictions[$team->id] = ($team->id === $championIds[0]) ? 100 : 0;
            }
            return $predictions;
        }

        // Season is ongoing - calculate chances for each team
        foreach ($teams as $team) {
            $standing = $standings->firstWhere('team_id', $team->id);
            $currentPoints = $standing['points'];
            $pointsFromLeader = $leaderPoints - $currentPoints;

            // Calculate remaining possible points
            $remainingGames = $scheduledGames->filter(function ($game) use ($team) {
                return $game->home_team_id === $team->id || $game->away_team_id === $team->id;
            })->count();

            $maxPossiblePoints = $currentPoints + ($remainingGames * 3);

            // If team can't mathematically catch up
            if ($maxPossiblePoints < $leaderPoints) {
                $predictions[$team->id] = 0;
                continue;
            }

            // Calculate various factors
            $pointsFactor = $currentPoints / ($leaderPoints ?: 1); // Current points relative to leader
            $positionFactor = 1 - ($pointsFromLeader / (($remainingGames * 3) ?: 1)); // Position factor considering remaining games
            $formFactor = $standing['form']; // Recent form (0 to 1)
            $strengthFactor = $team->strength / 100; // Team strength (0 to 1)

            // Weight the factors
            $weightedScore = (
                ($pointsFactor * 0.35) + // 35% weight for current points
                ($positionFactor * 0.25) + // 25% weight for position and remaining games
                ($formFactor * 0.20) + // 20% weight for recent form
                ($strengthFactor * 0.20) // 20% weight for team strength
            );

            // Apply catch-up penalty
            $catchupPenalty = max(0.1, 1 - ($pointsFromLeader / (($remainingGames * 3) ?: 1)));
            $predictions[$team->id] = $weightedScore * $catchupPenalty * 100;
        }

        // Normalize probabilities
        $total = array_sum($predictions);
        if ($total > 0) {
            foreach ($predictions as &$probability) {
                $probability = round(($probability / $total) * 100, 1);
            }
        }

        return $predictions;
    }

    /**
     * Calculate a form factor based on recent results
     */
    private function calculateFormFactor($team)
    {
        if (!$team->standing) {
            return 0;
        }

        $gamesPlayed = $team->standing->played;
        if ($gamesPlayed === 0) {
            return 0.5; // Neutral form for teams that haven't played
        }

        // Calculate win ratio
        $winRatio = $team->standing->won / $gamesPlayed;

        // Calculate goal difference per game
        $goalDifference = $team->standing->goals_for - $team->standing->goals_against;
        $goalDifferencePerGame = $goalDifference / $gamesPlayed;

        // Normalize goal difference to a factor between -0.5 and 0.5
        $goalFactor = max(-0.5, min(0.5, $goalDifferencePerGame / 3));

        // Combine win ratio and goal difference factor
        return ($winRatio + $goalFactor + 1) / 2;
    }
}
