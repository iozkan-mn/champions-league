<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Team;
use Carbon\Carbon;

class FixtureService
{
    private const HOME_ADVANTAGE = 15;
    private const FORM_MULTIPLIER = 5;
    private const RANDOM_RANGE = [-8, 8];
    private const HOME_SCORE_MULTIPLIER = 1.2;
    private const AWAY_SCORE_MULTIPLIER = 0.9;

    public function generateFixtures()
    {
        $teams = $this->prepareTeams();
        $teamCount = count($teams);
        $rounds = $teamCount - 1;
        $startDate = Carbon::now()->startOfWeek();

        Game::truncate();

        $firstHalfFixtures = $this->generateFirstHalfFixtures($teams, $teamCount, $rounds, $startDate);
        $this->createFixtures($firstHalfFixtures);
        $this->createSecondHalfFixtures($firstHalfFixtures, $rounds, $startDate);
    }

    private function prepareTeams(): array
    {
        $teams = Team::all()->toArray();
        $teamCount = count($teams);

        if ($teamCount % 2 !== 0) {
            $teams[] = ['id' => null, 'name' => 'BYE'];
        }

        return $teams;
    }

    private function generateFirstHalfFixtures(array $teams, int $teamCount, int $rounds, Carbon $startDate): array
    {
        $matchesPerRound = $teamCount / 2;
        $teamIndexes = range(0, $teamCount - 1);
        $firstHalfFixtures = [];

        for ($round = 0; $round < $rounds; $round++) {
            $roundMatches = $this->generateRoundMatches($teams, $teamIndexes, $teamCount, $matchesPerRound, $round, $startDate);
            $firstHalfFixtures[] = $roundMatches;
            $this->rotateArray($teamIndexes, 1, $teamCount - 1);
        }

        return $firstHalfFixtures;
    }

    private function generateRoundMatches(array $teams, array $teamIndexes, int $teamCount, int $matchesPerRound, int $round, Carbon $startDate): array
    {
        $roundMatches = [];

        for ($match = 0; $match < $matchesPerRound; $match++) {
            $home = $teamIndexes[$match];
            $away = $teamIndexes[$teamCount - 1 - $match];

            if ($teams[$home]['id'] !== null && $teams[$away]['id'] !== null) {
                $roundMatches[] = $this->createMatchData(
                    $round % 2 == 0 ? $teams[$home]['id'] : $teams[$away]['id'],
                    $round % 2 == 0 ? $teams[$away]['id'] : $teams[$home]['id'],
                    $round + 1,
                    $startDate->copy()->addWeeks($round)
                );
            }
        }

        return $roundMatches;
    }

    private function createMatchData(int $homeTeamId, int $awayTeamId, int $week, Carbon $date): array
    {
        return [
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'week' => $week,
            'status' => 'scheduled',
            'match_date' => $date
        ];
    }

    private function createFixtures(array $fixtures): void
    {
        foreach ($fixtures as $roundMatches) {
            foreach ($roundMatches as $match) {
                Game::create($match);
            }
        }
    }

    private function createSecondHalfFixtures(array $firstHalfFixtures, int $rounds, Carbon $startDate): void
    {
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

    public function simulateWeek(int $week)
    {
        $games = $this->getScheduledGames($week);

        foreach ($games as $game) {
            $this->simulateGame($game);
        }
    }

    private function getScheduledGames(int $week)
    {
        return Game::where('week', $week)
            ->where('status', 'scheduled')
            ->with(['homeTeam', 'awayTeam'])
            ->get();
    }

    private function simulateGame($game)
    {
        $homeStrengthAdjusted = $this->calculateAdjustedStrength($game->homeTeam, true);
        $awayStrengthAdjusted = $this->calculateAdjustedStrength($game->awayTeam, false);

        $scores = $this->calculateScores($homeStrengthAdjusted, $awayStrengthAdjusted);

        $game->update([
            'home_score' => $scores['home'],
            'away_score' => $scores['away'],
            'status' => 'completed'
        ]);

        $this->updateStandings($game);
    }

    private function calculateAdjustedStrength($team, bool $isHome): float
    {
        $baseStrength = $team->strength;
        $formBonus = $this->calculateFormBonus($team);
        $randomFactor = mt_rand(...self::RANDOM_RANGE);

        return $baseStrength +
            ($isHome ? self::HOME_ADVANTAGE : 0) +
            $formBonus +
            $randomFactor;
    }

    private function calculateFormBonus($team): float
    {
        if (!$team->standing || $team->standing->played === 0) {
            return 0;
        }

        return ($team->standing->won / $team->standing->played) * self::FORM_MULTIPLIER;
    }

    private function calculateScores(float $homeStrengthAdjusted, float $awayStrengthAdjusted): array
    {
        $totalStrength = $homeStrengthAdjusted + $awayStrengthAdjusted;

        $homeScoreProb = $this->calculateScoringProbability(
            $homeStrengthAdjusted,
            $totalStrength,
            self::HOME_SCORE_MULTIPLIER
        );

        $awayScoreProb = $this->calculateScoringProbability(
            $awayStrengthAdjusted,
            $totalStrength,
            self::AWAY_SCORE_MULTIPLIER
        );

        return [
            'home' => $this->generateScore($homeScoreProb, $homeStrengthAdjusted),
            'away' => $this->generateScore($awayScoreProb, $awayStrengthAdjusted)
        ];
    }

    private function calculateScoringProbability(float $strength, float $totalStrength, float $multiplier): float
    {
        $probability = ($strength / $totalStrength) * $multiplier;
        return $multiplier === self::HOME_SCORE_MULTIPLIER
            ? min(0.8, max(0.2, $probability))
            : min(0.7, max(0.1, $probability));
    }

    private function generateScore($chance, $strength)
    {
        $scoreProbs = $this->getBaseScoreProbabilities();
        $strengthFactor = $strength / 100;
        $adjustedProbs = $this->adjustProbabilities($scoreProbs, $chance, $strengthFactor);

        return $this->selectScoreFromProbabilities($adjustedProbs);
    }

    private function getBaseScoreProbabilities(): array
    {
        return [
            0 => 0.20,
            1 => 0.35,
            2 => 0.25,
            3 => 0.15,
            4 => 0.05
        ];
    }

    private function adjustProbabilities(array $probabilities, float $chance, float $strengthFactor): array
    {
        $total = 0;
        $adjusted = [];

        foreach ($probabilities as $score => $prob) {
            $adjusted[$score] = $this->adjustProbability($score, $prob, $chance, $strengthFactor);
            $total += $adjusted[$score];
        }

        return array_map(fn($prob) => $prob / $total, $adjusted);
    }

    private function adjustProbability(int $score, float $prob, float $chance, float $strengthFactor): float
    {
        return $score === 0
            ? $prob * (2 - $chance * $strengthFactor)
            : $prob * ($chance * $strengthFactor);
    }

    private function selectScoreFromProbabilities(array $probabilities): int
    {
        $random = mt_rand(0, 100) / 100;
        $cumulative = 0;

        foreach ($probabilities as $score => $prob) {
            $cumulative += $prob;
            if ($random <= $cumulative) {
                return $score;
            }
        }

        return 0;
    }

    private function rotateArray(&$array, $start, $end)
    {
        $last = $array[$end];
        for ($i = $end; $i > $start; $i--) {
            $array[$i] = $array[$i - 1];
        }
        $array[$start] = $last;
    }

    private function updateStandings($game)
    {
        $homeTeam = $game->homeTeam;
        $awayTeam = $game->awayTeam;

        $homeStanding = $this->getOrCreateStanding($homeTeam);
        $awayStanding = $this->getOrCreateStanding($awayTeam);

        $this->updateMatchStatistics($homeStanding, $awayStanding, $game);
        $this->updatePoints($homeStanding, $awayStanding, $game);

        $homeStanding->save();
        $awayStanding->save();
    }

    private function getOrCreateStanding($team)
    {
        return $team->standing ?? $team->standing()->create([
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'points' => 0,
        ]);
    }

    private function updateMatchStatistics($homeStanding, $awayStanding, $game)
    {
        $homeStanding->played++;
        $awayStanding->played++;

        $homeStanding->goals_for += $game->home_score;
        $homeStanding->goals_against += $game->away_score;
        $awayStanding->goals_for += $game->away_score;
        $awayStanding->goals_against += $game->home_score;
    }

    private function updatePoints($homeStanding, $awayStanding, $game)
    {
        if ($game->home_score > $game->away_score) {
            $this->applyWinLoss($homeStanding, $awayStanding);
        } elseif ($game->home_score < $game->away_score) {
            $this->applyWinLoss($awayStanding, $homeStanding);
        } else {
            $this->applyDraw($homeStanding, $awayStanding);
        }
    }

    private function applyWinLoss($winner, $loser)
    {
        $winner->won++;
        $winner->points += 3;
        $loser->lost++;
    }

    private function applyDraw($standing1, $standing2)
    {
        $standing1->drawn++;
        $standing2->drawn++;
        $standing1->points++;
        $standing2->points++;
    }
}
