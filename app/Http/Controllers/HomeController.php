<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Team;
use App\Http\Controllers\GameController;
use Inertia\Inertia;

class HomeController extends Controller
{
    protected $gameController;

    public function __construct(GameController $gameController)
    {
        $this->gameController = $gameController;
    }

    public function index()
    {
        $games = Game::with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->orderBy('match_date')
            ->get();

        // Eager load teams with their standings
        $teams = Team::with('standing')->get();

        // Find the earliest week that has scheduled games
        $currentWeek = $games->where('status', 'scheduled')->min('week');
        if ($currentWeek === null) {
            $currentWeek = $games->max('week') ?? 1;
        }

        // Check if there are any scheduled games for the current week
        $hasScheduledGames = $games->where('status', 'scheduled')
            ->where('week', $currentWeek)
            ->isNotEmpty();

        // Calculate predictions if there are fixtures
        $predictions = null;
        if ($games->isNotEmpty()) {
            $predictions = $this->gameController->calculateChampionshipPredictions($teams);
        }

        return Inertia::render('Home', [
            'games' => $games,
            'teams' => $teams,
            'currentWeek' => $currentWeek,
            'hasScheduledGames' => $hasScheduledGames,
            'predictions' => $predictions,
        ]);
    }
}
