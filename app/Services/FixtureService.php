<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Team;
use Carbon\Carbon;

class FixtureService
{
    public function generateFixtures()
    {
        $teams = Team::all()->toArray();
        $teamCount = count($teams);

        // If odd number of teams, add a dummy team
        if ($teamCount % 2 !== 0) {
            $teams[] = ['id' => null, 'name' => 'BYE'];
            $teamCount++;
        }

        $startDate = Carbon::now()->startOfWeek();
        $rounds = $teamCount - 1;
        $matchesPerRound = $teamCount / 2;
        $firstHalfFixtures = [];

        // Clear existing fixtures
        Game::truncate();

        // Create array of team indexes
        $teamIndexes = range(0, $teamCount - 1);

        // First team stays fixed, others rotate around it
        for ($round = 0; $round < $rounds; $round++) {
            $roundMatches = [];

            // Generate matches for this round
            for ($match = 0; $match < $matchesPerRound; $match++) {
                $home = $teamIndexes[$match];
                $away = $teamIndexes[$teamCount - 1 - $match];

                // Skip matches with dummy team
                if ($teams[$home]['id'] !== null && $teams[$away]['id'] !== null) {
                    // Alternate home/away for better distribution
                    if ($round % 2 == 0) {
                        $roundMatches[] = [
                            'home_team_id' => $teams[$home]['id'],
                            'away_team_id' => $teams[$away]['id'],
                            'week' => $round + 1,
                            'status' => 'scheduled',
                            'match_date' => $startDate->copy()->addWeeks($round)
                        ];
                    } else {
                        $roundMatches[] = [
                            'home_team_id' => $teams[$away]['id'],
                            'away_team_id' => $teams[$home]['id'],
                            'week' => $round + 1,
                            'status' => 'scheduled',
                            'match_date' => $startDate->copy()->addWeeks($round)
                        ];
                    }
                }
            }

            $firstHalfFixtures[] = $roundMatches;

            // Rotate teams (except first team)
            $this->rotateArray($teamIndexes, 1, $teamCount - 1);
        }

        // Create first half fixtures
        foreach ($firstHalfFixtures as $roundMatches) {
            foreach ($roundMatches as $match) {
                Game::create($match);
            }
        }

        // Create second half fixtures (reverse home/away)
        foreach ($firstHalfFixtures as $round => $roundMatches) {
            foreach ($roundMatches as $match) {
                Game::create([
                    'home_team_id' => $match['away_team_id'],
                    'away_team_id' => $match['home_team_id'],
                    'week' => $round + $rounds + 1,
                    'status' => 'scheduled',
                    'match_date' => $startDate->copy()->addWeeks($round + $rounds)
                ]);
            }
        }
    }

    private function rotateArray(&$array, $start, $end)
    {
        $last = $array[$end];
        for ($i = $end; $i > $start; $i--) {
            $array[$i] = $array[$i - 1];
        }
        $array[$start] = $last;
    }

    public function simulateWeek(int $week)
    {
        $games = Game::where('week', $week)
            ->where('status', 'scheduled')
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        foreach ($games as $game) {
            $homeStrength = $game->homeTeam->strength;
            $awayStrength = $game->awayTeam->strength;

            // Calculate base probabilities with increased home advantage
            $homeAdvantage = 15; // Increased from 10 to 15

            // Add form-based bonus (using win ratio from standings)
            $homeForm = 0;
            $awayForm = 0;

            if ($game->homeTeam->standing) {
                $homeGames = $game->homeTeam->standing->played;
                if ($homeGames > 0) {
                    $homeForm = ($game->homeTeam->standing->won / $homeGames) * 5;
                }
            }

            if ($game->awayTeam->standing) {
                $awayGames = $game->awayTeam->standing->played;
                if ($awayGames > 0) {
                    $awayForm = ($game->awayTeam->standing->won / $awayGames) * 5;
                }
            }

            // Calculate adjusted strengths with randomness and form
            $randomFactor = mt_rand(-8, 8);
            $homeStrengthAdjusted = $homeStrength + $homeAdvantage + $homeForm + $randomFactor;
            $awayStrengthAdjusted = $awayStrength + $awayForm + mt_rand(-8, 8);

            // Apply additional home bias to scoring probabilities
            $totalStrength = $homeStrengthAdjusted + $awayStrengthAdjusted;
            $homeScoreProb = ($homeStrengthAdjusted / $totalStrength) * 1.2; // 20% bonus for home team
            $awayScoreProb = ($awayStrengthAdjusted / $totalStrength) * 0.9; // 10% penalty for away team

            // Ensure probabilities are within reasonable bounds
            $homeScoreProb = min(0.8, max(0.2, $homeScoreProb));
            $awayScoreProb = min(0.7, max(0.1, $awayScoreProb));

            // Generate scores
            $homeScore = $this->generateScore($homeScoreProb, $homeStrengthAdjusted);
            $awayScore = $this->generateScore($awayScoreProb, $awayStrengthAdjusted);

            $game->update([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'status' => 'completed'
            ]);

            $this->updateStandings($game);
        }
    }

    private function generateScore($chance, $strength)
    {
        // Base score probabilities (0-5 goals)
        $scoreProbs = [
            0 => 0.20,  // 20% chance
            1 => 0.35,  // 35% chance
            2 => 0.25,  // 25% chance
            3 => 0.15,  // 15% chance
            4 => 0.05   // 5% chance
        ];

        // Adjust probabilities based on team strength and chance
        $strengthFactor = $strength / 100;
        $total = 0;

        // Calculate adjusted probabilities and total in one loop
        foreach ($scoreProbs as $score => &$prob) {
            if ($score === 0) {
                $prob *= (2 - $chance * $strengthFactor); // Less likely to score 0 for strong teams
            } else {
                $prob *= ($chance * $strengthFactor); // More likely to score for strong teams
            }
            $total += $prob;
        }
        unset($prob);

        // Generate random number once
        $random = mt_rand(0, 100) / 100;
        $cumulative = 0;

        // Find the score based on adjusted probabilities
        foreach ($scoreProbs as $score => $prob) {
            $cumulative += ($prob / $total); // Normalize on the fly
            if ($random <= $cumulative) {
                return $score;
            }
        }

        return 0; // Fallback
    }

    private function updateStandings($game)
    {
        $homeTeam = $game->homeTeam;
        $awayTeam = $game->awayTeam;

        $homeStanding = $homeTeam->standing;
        $awayStanding = $awayTeam->standing;

        if (!$homeStanding) {
            $homeStanding = $homeTeam->standing()->create([
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        if (!$awayStanding) {
            $awayStanding = $awayTeam->standing()->create([
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        $homeStanding->played++;
        $awayStanding->played++;

        $homeStanding->goals_for += $game->home_score;
        $homeStanding->goals_against += $game->away_score;
        $awayStanding->goals_for += $game->away_score;
        $awayStanding->goals_against += $game->home_score;

        if ($game->home_score > $game->away_score) {
            $homeStanding->won++;
            $homeStanding->points += 3;
            $awayStanding->lost++;
        } elseif ($game->home_score < $game->away_score) {
            $awayStanding->won++;
            $awayStanding->points += 3;
            $homeStanding->lost++;
        } else {
            $homeStanding->drawn++;
            $awayStanding->drawn++;
            $homeStanding->points++;
            $awayStanding->points++;
        }

        $homeStanding->save();
        $awayStanding->save();
    }
}
