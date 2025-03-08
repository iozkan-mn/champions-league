<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Team;
use App\Services\ChampionshipService;
use App\Services\FixtureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GameController extends Controller
{
    public function __construct(
        private readonly FixtureService $fixtureService,
        private readonly ChampionshipService $championshipService
    ) {}

    public function index(): View
    {
        $teams = Team::with('standing')->get();
        $games = Game::with(['homeTeam', 'awayTeam'])->orderBy('week')->orderBy('match_date')->get();
        $currentWeek = $this->championshipService->getCurrentWeek($games);
        $hasScheduledGames = $this->championshipService->hasScheduledGamesForWeek($games, $currentWeek);
        $predictions = $this->championshipService->calculateChampionshipPredictions($teams);

        return view('games.index', compact('teams', 'games', 'currentWeek', 'hasScheduledGames', 'predictions'));
    }

    public function generate(): RedirectResponse
    {
        $this->fixtureService->generateFixtures();
        $this->championshipService->ensureAllTeamsHaveStandings();

        return redirect()->route('home')->with('success', 'Fixtures generated successfully.');
    }

    public function reset(): RedirectResponse
    {
        Game::truncate();
        return redirect()->route('home')->with('success', 'All games have been reset.');
    }

    public function simulateWeek(): RedirectResponse
    {
        $games = Game::with(['homeTeam', 'awayTeam'])->orderBy('week')->orderBy('match_date')->get();
        $currentWeek = $this->championshipService->getCurrentWeek($games);

        if (!$this->championshipService->hasScheduledGamesForWeek($games, $currentWeek)) {
            return redirect()->route('home')->with('error', 'No scheduled games found for the current week.');
        }

        $this->fixtureService->simulateWeek($currentWeek);
        $this->championshipService->recalculateStandings();

        return redirect()->route('home')->with('success', 'Week simulated successfully.');
    }

    public function simulateAll(): RedirectResponse
    {
        $games = Game::where('status', 'scheduled')->orderBy('week')->get();
        $weeks = $games->pluck('week')->unique();

        foreach ($weeks as $week) {
            $this->fixtureService->simulateWeek($week);
        }

        $this->championshipService->recalculateStandings();

        return redirect()->route('home')->with('success', 'All remaining games simulated successfully.');
    }
}
