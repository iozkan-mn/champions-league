<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\SimulationController;
use App\Services\FixtureService;
use App\Services\ChampionshipService;
use App\Models\Team;
use App\Models\Game;
use App\Models\Standing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;

class SimulationControllerTest extends TestCase
{
    use RefreshDatabase;

    private SimulationController $simulationController;
    private FixtureService $fixtureService;
    private ChampionshipService $championshipService;
    private array $teams;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureService = new FixtureService();
        $this->championshipService = new ChampionshipService();
        $this->simulationController = new SimulationController($this->fixtureService, $this->championshipService);

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
        $response = $this->simulationController->generate();

        // Check if fixtures are generated
        $this->assertTrue(Game::exists());
        $this->assertEquals(12, Game::count()); // 4 teams = 6 games per half season = 12 total
        
        // Check if all teams have standings
        $this->assertEquals(4, Standing::count());
        $this->assertEquals(0, Standing::sum('points')); // All standings should start at 0
        
        // Check if games are properly scheduled
        $this->assertEquals('scheduled', Game::first()->status);
        $this->assertNotNull(Game::first()->match_date);
        
        // Check if each team has correct number of home and away games
        foreach (Team::all() as $team) {
            $homeGames = Game::where('home_team_id', $team->id)->count();
            $awayGames = Game::where('away_team_id', $team->id)->count();
            $this->assertEquals(3, $homeGames, "Team {$team->name} should have 3 home games");
            $this->assertEquals(3, $awayGames, "Team {$team->name} should have 3 away games");
        }
        
        // Check response
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('home'), $response->getTargetUrl());
        $this->assertTrue($response->getSession()->has('success'));
    }

    /** @test */
    public function it_resets_data_successfully()
    {
        // First generate some data
        $this->simulationController->generate();
        $this->championshipService->ensureAllTeamsHaveStandings();
        $this->simulationController->simulateWeek();

        // Verify we have data
        $this->assertTrue(Game::exists());
        $this->assertTrue(Standing::exists());

        // Then reset
        $response = $this->simulationController->reset();

        // Check if games and standings are deleted
        $this->assertEquals(0, Game::count());
        $this->assertEquals(0, Standing::count());
        
        // Check response
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('home'), $response->getTargetUrl());
        $this->assertTrue($response->getSession()->has('success'));
    }

    /** @test */
    public function it_simulates_week_successfully()
    {
        $this->simulationController->generate();
        $response = $this->simulationController->simulateWeek();

        // Check if games for week 1 are completed
        $games = Game::where('week', 1)->get();
        $this->assertNotEmpty($games);
        
        foreach ($games as $game) {
            $this->assertEquals('completed', $game->status);
            $this->assertNotNull($game->home_score);
            $this->assertNotNull($game->away_score);
            $this->assertGreaterThanOrEqual(0, $game->home_score);
            $this->assertGreaterThanOrEqual(0, $game->away_score);
            $this->assertLessThanOrEqual(5, $game->home_score); // Max score should be 5
            $this->assertLessThanOrEqual(5, $game->away_score);
        }

        // Check if standings are updated
        $this->assertTrue(Standing::sum('points') > 0);
        $this->assertEquals($games->count() * 2, Standing::sum('played')); // Each game counts for 2 teams
        
        // Check response
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('home'), $response->getTargetUrl());
        $this->assertTrue($response->getSession()->has('success'));
    }

    /** @test */
    public function it_handles_no_scheduled_games_for_week()
    {
        $this->simulationController->generate();
        
        // Simulate all weeks
        $this->simulationController->simulateAll();
        
        // Try to simulate again when no games are scheduled
        $response = $this->simulationController->simulateWeek();
        
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('home'), $response->getTargetUrl());
        $this->assertTrue($response->getSession()->has('error'));
    }

    /** @test */
    public function it_simulates_all_weeks_successfully()
    {
        $this->simulationController->generate();
        $response = $this->simulationController->simulateAll();

        // Check if all games are completed
        $this->assertEquals(0, Game::where('status', 'scheduled')->count());
        $this->assertEquals(12, Game::where('status', 'completed')->count());
        
        // Check if standings are complete
        $this->assertEquals(4, Standing::count());
        $this->assertTrue(Standing::sum('points') > 0);
        $this->assertEquals(24, Standing::sum('played')); // Each team plays 6 games = 24 total played
        
        // Verify standings calculations
        Standing::all()->each(function ($standing) {
            // Points calculation
            $this->assertEquals(
                $standing->won * 3 + $standing->drawn,
                $standing->points,
                "Points calculation is incorrect for team {$standing->team_id}"
            );
            
            // Games played calculation
            $this->assertEquals(
                $standing->won + $standing->drawn + $standing->lost,
                $standing->played,
                "Games played calculation is incorrect for team {$standing->team_id}"
            );
            
            // Each team should play 6 games
            $this->assertEquals(
                6,
                $standing->played,
                "Team {$standing->team_id} should have played 6 games"
            );
        });
        
        // Check response
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('home'), $response->getTargetUrl());
        $this->assertTrue($response->getSession()->has('success'));
    }

    /** @test */
    public function it_maintains_game_order_when_simulating()
    {
        $this->simulationController->generate();
        $this->simulationController->simulateWeek();

        // Check if games are simulated in order
        $completedGames = Game::where('status', 'completed')->get();
        $scheduledGames = Game::where('status', 'scheduled')->get();

        // All completed games should be from earlier weeks than scheduled games
        $maxCompletedWeek = $completedGames->max('week');
        $minScheduledWeek = $scheduledGames->min('week');
        $this->assertLessThanOrEqual($minScheduledWeek, $maxCompletedWeek);

        // Check if games within the same week are all completed
        $completedWeeks = $completedGames->pluck('week')->unique();
        foreach ($completedWeeks as $week) {
            $weekGames = Game::where('week', $week)->get();
            $this->assertEquals(
                'completed',
                $weekGames->pluck('status')->unique()->first(),
                "All games in week {$week} should be completed"
            );
        }
    }

    /** @test */
    public function it_updates_standings_correctly_after_simulation()
    {
        $this->simulationController->generate();
        $this->simulationController->simulateWeek();

        // Get a completed game
        $game = Game::where('status', 'completed')->first();
        
        // Get standings for both teams
        $homeStanding = Standing::where('team_id', $game->home_team_id)->first();
        $awayStanding = Standing::where('team_id', $game->away_team_id)->first();

        // Verify standings are updated correctly
        if ($game->home_score > $game->away_score) {
            $this->assertEquals(3, $homeStanding->points);
            $this->assertEquals(0, $awayStanding->points);
            $this->assertEquals(1, $homeStanding->won);
            $this->assertEquals(1, $awayStanding->lost);
        } elseif ($game->home_score < $game->away_score) {
            $this->assertEquals(0, $homeStanding->points);
            $this->assertEquals(3, $awayStanding->points);
            $this->assertEquals(1, $homeStanding->lost);
            $this->assertEquals(1, $awayStanding->won);
        } else {
            $this->assertEquals(1, $homeStanding->points);
            $this->assertEquals(1, $awayStanding->points);
            $this->assertEquals(1, $homeStanding->drawn);
            $this->assertEquals(1, $awayStanding->drawn);
        }

        // Verify goal statistics
        $this->assertEquals($game->home_score, $homeStanding->goals_for);
        $this->assertEquals($game->away_score, $homeStanding->goals_against);
        $this->assertEquals($game->away_score, $awayStanding->goals_for);
        $this->assertEquals($game->home_score, $awayStanding->goals_against);
    }

    /** @test */
    public function it_considers_team_strength_in_score_generation()
    {
        // First, ensure we have a strong team
        $strongTeam = Team::where('strength', 85)->first();
        $this->assertNotNull($strongTeam, 'Strong team with strength 85 not found');
        
        $strongTeamWins = 0;
        $strongTeamDraws = 0;
        $totalGames = 0;
        $iterations = 10;

        for ($i = 0; $i < $iterations; $i++) {
            $this->simulationController->generate();
            $this->simulationController->simulateAll();
            
            // Get all games involving the strong team
            $games = Game::where('home_team_id', $strongTeam->id)
                        ->orWhere('away_team_id', $strongTeam->id)
                        ->get();
            
            foreach ($games as $game) {
                $strongTeamScore = $game->home_team_id == $strongTeam->id ? 
                    $game->home_score : $game->away_score;
                $weakTeamScore = $game->home_team_id == $strongTeam->id ? 
                    $game->away_score : $game->home_score;
                
                if ($strongTeamScore > $weakTeamScore) {
                    $strongTeamWins++;
                } elseif ($strongTeamScore == $weakTeamScore) {
                    $strongTeamDraws++;
                }
                $totalGames++;
            }
            
            Game::truncate();
            Standing::truncate();
        }
        
        $this->assertGreaterThan(0, $totalGames, 'No games were played');
        
        // Calculate win rate including half of draws
        $winRate = ($strongTeamWins + ($strongTeamDraws * 0.5)) / $totalGames;
        $this->assertGreaterThan(0.5, $winRate, 
            "Strong team should win more than 50% of games. " .
            "Actual rate: " . round($winRate * 100, 2) . "% " .
            "($strongTeamWins wins, $strongTeamDraws draws in $totalGames games)"
        );
    }
} 