<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Team;
use App\Services\ChampionshipService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly ChampionshipService $championshipService
    ) {}

    public function index(): Response
    {
        $teams = Team::with('standing')->get();
        $games = Game::with(['homeTeam', 'awayTeam'])->orderBy('week')->orderBy('match_date')->get();
        $currentWeek = $this->championshipService->getCurrentWeek($games);
        $hasScheduledGames = $this->championshipService->hasScheduledGamesForWeek($games, $currentWeek);
        $predictions = $this->championshipService->calculateChampionshipPredictions($teams);

        return Inertia::render('Home', [
            'games' => $games,
            'teams' => $teams,
            'currentWeek' => $currentWeek,
            'hasScheduledGames' => $hasScheduledGames,
            'predictions' => $predictions,
        ]);
    }
}
