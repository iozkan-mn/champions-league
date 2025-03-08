<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\GameController;
use App\Services\FixtureService;
use App\Models\Team;
use App\Models\Game;
use App\Models\Standing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    private GameController $gameController;
    private array $teams;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gameController = new GameController(new FixtureService());

        // Create test teams
        $this->teams = [
            ['name' => 'Team 1', 'strength' => 85, 'logo' => 'logo1.png', 'country' => 'Country 1'],
            ['name' => 'Team 2', 'strength' => 80, 'logo' => 'logo2.png', 'country' => 'Country 2'],
            ['name' => 'Team 3', 'strength' => 75, 'logo' => 'logo3.png', 'country' => 'Country 3'],
            ['name' => 'Team 4', 'strength' => 70, 'logo' => 'logo4.png', 'country' => 'Country 4'],
        ];

        foreach ($this->teams as $team) {
            Team::create($team);
        }
    }

    /** @test */
    public function it_generates_fixtures_successfully()
    {
        $response = $this->gameController->generateFixtures();

        $this->assertTrue(Game::exists());
        $this->assertNotEquals(0, Game::count());
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /** @test */
    public function it_resets_data_successfully()
    {
        // First generate some data
        $this->gameController->generateFixtures();
        $this->gameController->simulateWeek(new Request(['week' => 1]));

        // Then reset
        $response = $this->gameController->resetData();

        $this->assertEquals(0, Game::count());
        $this->assertEquals(0, Standing::count());
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /** @test */
    public function it_simulates_week_successfully()
    {
        $this->gameController->generateFixtures();
        
        $request = new Request(['week' => 1]);
        $response = $this->gameController->simulateWeek($request);

        $games = Game::where('week', 1)->get();
        
        foreach ($games as $game) {
            $this->assertEquals('completed', $game->status);
            $this->assertNotNull($game->home_score);
            $this->assertNotNull($game->away_score);
        }

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /** @test */
    public function it_calculates_championship_predictions_correctly()
    {
        // Generate and simulate some games
        $this->gameController->generateFixtures();
        $this->gameController->simulateWeek(new Request(['week' => 1]));

        $teams = Team::with('standing')->get();
        $predictions = $this->gameController->calculateChampionshipPredictions($teams);

        // Verify predictions
        $this->assertIsArray($predictions);
        $this->assertEquals(count($this->teams), count($predictions));
        
        // Total probability should be 100%
        $totalProbability = array_sum($predictions);
        $this->assertEquals(100, round($totalProbability));

        // Each probability should be between 0 and 100
        foreach ($predictions as $probability) {
            $this->assertGreaterThanOrEqual(0, $probability);
            $this->assertLessThanOrEqual(100, $probability);
        }
    }

    /** @test */
    public function it_handles_tied_teams_in_predictions()
    {
        // Create two teams with equal strength
        $team1 = Team::create(['name' => 'Equal Team 1', 'strength' => 80, 'logo' => 'equal1.png', 'country' => 'Equal Country 1']);
        $team2 = Team::create(['name' => 'Equal Team 2', 'strength' => 80, 'logo' => 'equal2.png', 'country' => 'Equal Country 2']);

        // Create standings with equal points
        Standing::create([
            'team_id' => $team1->id,
            'played' => 1,
            'won' => 1,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 2,
            'goals_against' => 1,
            'points' => 3
        ]);

        Standing::create([
            'team_id' => $team2->id,
            'played' => 1,
            'won' => 1,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 2,
            'goals_against' => 1,
            'points' => 3
        ]);

        $teams = Team::with('standing')->get();
        $predictions = $this->gameController->calculateChampionshipPredictions($teams);

        // Teams with equal points and stats should have similar predictions
        $this->assertEqualsWithDelta($predictions[$team1->id], $predictions[$team2->id], 0.1);
    }

    /** @test */
    public function it_simulates_all_weeks_successfully()
    {
        $this->gameController->generateFixtures();
        $response = $this->gameController->playAllWeeks();

        // All games should be completed
        $this->assertEquals(0, Game::where('status', 'scheduled')->count());
        $this->assertTrue(Game::where('status', 'completed')->exists());
        
        // All teams should have standings
        $this->assertEquals(count($this->teams), Standing::count());
        
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }
} 