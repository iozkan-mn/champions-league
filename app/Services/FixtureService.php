<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FixtureService
{
    private const SCORE_PROBABILITIES = [
        0 => 0.15,
        1 => 0.25,
        2 => 0.25,
        3 => 0.20,
        4 => 0.10,
        5 => 0.05
    ];

    public function generateFixtures(): void
    {
        $teams = Team::all();
        $totalTeams = $teams->count();
        $totalRounds = $totalTeams - 1;
        $matchesPerRound = $totalTeams / 2;

        // Create array of team IDs
        $teamIds = $teams->pluck('id')->toArray();

        // Generate first half of season
        for ($round = 0; $round < $totalRounds; $round++) {
            $this->generateRoundFixtures($teamIds, $round + 1, $matchesPerRound);

            // Rotate teams for next round (keeping first team fixed)
            $this->rotateTeams($teamIds);
        }

        // Generate second half of season (reverse fixtures)
        $this->generateReturnFixtures($totalRounds);
    }

    private function generateRoundFixtures(array $teamIds, int $round, int $matchesPerRound): void
    {
        $date = Carbon::now()->addWeeks($round - 1);

        for ($i = 0; $i < $matchesPerRound; $i++) {
            $homeTeamId = $teamIds[$i];
            $awayTeamId = $teamIds[count($teamIds) - 1 - $i];

            Game::create([
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'match_date' => $date,
                'week' => $round,
                'status' => 'scheduled'
            ]);
        }
    }

    private function generateReturnFixtures(int $totalRounds): void
    {
        $firstHalfFixtures = Game::where('week', '<=', $totalRounds)->get();

        foreach ($firstHalfFixtures as $fixture) {
            $returnWeek = $fixture->week + $totalRounds;
            $returnDate = Carbon::parse($fixture->match_date)->addWeeks($totalRounds);

            Game::create([
                'home_team_id' => $fixture->away_team_id,
                'away_team_id' => $fixture->home_team_id,
                'match_date' => $returnDate,
                'week' => $returnWeek,
                'status' => 'scheduled'
            ]);
        }
    }

    private function rotateTeams(array &$teamIds): void
    {
        if (count($teamIds) < 2) {
            return;
        }

        $lastElement = array_pop($teamIds);
        array_splice($teamIds, 1, 0, [$lastElement]);
    }

    public function simulateWeek(int $week): void
    {
        $games = Game::where('week', $week)
            ->where('status', 'scheduled')
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        foreach ($games as $game) {
            $scores = $this->generateScore($game->homeTeam, $game->awayTeam);
            $game->update([
                'home_score' => $scores['home'],
                'away_score' => $scores['away'],
                'status' => 'completed'
            ]);
        }
    }

    public function generateScore(Team $homeTeam, Team $awayTeam): array
    {
        $probabilities = self::SCORE_PROBABILITIES;
        $strengthDiff = $homeTeam->strength - $awayTeam->strength;
        $homeAdvantage = 15;
        $totalDiff = $strengthDiff + $homeAdvantage;

        foreach ($probabilities as $score => &$prob) {
            if ($totalDiff > 0) {
                $prob *= (1 + ($totalDiff / 50) * (1 + $score / 5));
            } else {
                $prob *= (1 - (abs($totalDiff) / 50) * (1 + $score / 5));
            }
        }
        unset($prob);

        $total = array_sum($probabilities);
        $normalizedProbs = array_map(fn($p) => $p / $total, $probabilities);

        $homeScore = $this->getRandomScore($normalizedProbs);
        $awayScore = $this->getRandomScore($probabilities);

        return [
            'home' => $homeScore,
            'away' => $awayScore
        ];
    }

    private function getRandomScore(array $probabilities): int
    {
        $rand = mt_rand() / mt_getrandmax();
        $cumulativeProb = 0;

        foreach ($probabilities as $score => $prob) {
            $cumulativeProb += $prob;
            if ($rand <= $cumulativeProb) {
                return $score;
            }
        }

        return array_key_last($probabilities);
    }
}
