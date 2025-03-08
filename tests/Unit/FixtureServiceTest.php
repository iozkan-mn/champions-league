<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FixtureService;
use App\Models\Team;
use App\Models\Game;
use App\Models\Standing;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FixtureServiceTest extends TestCase
{
    use RefreshDatabase;

    private FixtureService $fixtureService;
    private array $teams;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureService = new FixtureService();

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
    public function it_generates_correct_number_of_fixtures()
    {
        $this->fixtureService->generateFixtures();

        $teamCount = count($this->teams);
        $expectedGames = $teamCount * ($teamCount - 1); // Each team plays against every other team twice

        $this->assertEquals($expectedGames, Game::count());
    }

    /** @test */
    public function it_ensures_each_team_plays_equal_home_and_away_games()
    {
        $this->fixtureService->generateFixtures();

        $teams = Team::all();
        foreach ($teams as $team) {
            $homeGames = Game::where('home_team_id', $team->id)->count();
            $awayGames = Game::where('away_team_id', $team->id)->count();

            $this->assertEquals($homeGames, $awayGames);
            $this->assertEquals(count($this->teams) - 1, $homeGames);
        }
    }

    /** @test */
    public function it_simulates_week_with_realistic_scores()
    {
        $this->fixtureService->generateFixtures();
        $this->fixtureService->simulateWeek(1);

        $games = Game::where('week', 1)->get();
        
        foreach ($games as $game) {
            // Scores should be within realistic range (0-4)
            $this->assertGreaterThanOrEqual(0, $game->home_score);
            $this->assertLessThanOrEqual(4, $game->home_score);
            $this->assertGreaterThanOrEqual(0, $game->away_score);
            $this->assertLessThanOrEqual(4, $game->away_score);
            
            // Game should be marked as completed
            $this->assertEquals('completed', $game->status);
        }
    }

    /** @test */
    public function it_updates_standings_correctly_after_simulation()
    {
        $this->fixtureService->generateFixtures();
        $this->fixtureService->simulateWeek(1);

        $games = Game::where('week', 1)->get();
        
        foreach ($games as $game) {
            $homeStanding = Standing::where('team_id', $game->home_team_id)->first();
            $awayStanding = Standing::where('team_id', $game->away_team_id)->first();

            // Check if standings exist
            $this->assertNotNull($homeStanding);
            $this->assertNotNull($awayStanding);

            // Verify goals
            $this->assertEquals($game->home_score, $homeStanding->goals_for);
            $this->assertEquals($game->away_score, $homeStanding->goals_against);
            $this->assertEquals($game->away_score, $awayStanding->goals_for);
            $this->assertEquals($game->home_score, $awayStanding->goals_against);

            // Verify points
            if ($game->home_score > $game->away_score) {
                $this->assertEquals(3, $homeStanding->points);
                $this->assertEquals(0, $awayStanding->points);
            } elseif ($game->home_score < $game->away_score) {
                $this->assertEquals(0, $homeStanding->points);
                $this->assertEquals(3, $awayStanding->points);
            } else {
                $this->assertEquals(1, $homeStanding->points);
                $this->assertEquals(1, $awayStanding->points);
            }
        }
    }

    /** @test */
    public function it_considers_team_strength_in_score_generation()
    {
        // Create a strong team and a weak team
        $strongTeam = Team::create(['name' => 'Strong Team', 'strength' => 100, 'logo' => 'strong.png', 'country' => 'Strong Country']);
        $weakTeam = Team::create(['name' => 'Weak Team', 'strength' => 50, 'logo' => 'weak.png', 'country' => 'Weak Country']);

        // Simulate a week to test score generation
        $game = Game::create([
            'home_team_id' => $strongTeam->id,
            'away_team_id' => $weakTeam->id,
            'week' => 1,
            'status' => 'scheduled',
            'match_date' => now()
        ]);

        // Simulate multiple weeks
        $totalSimulations = 50;
        $strongTeamGoals = 0;
        $weakTeamGoals = 0;

        for ($i = 0; $i < $totalSimulations; $i++) {
            $this->fixtureService->simulateWeek(1);
            $game->refresh();
            
            $strongTeamGoals += $game->home_score;
            $weakTeamGoals += $game->away_score;

            // Reset the game for next simulation
            $game->update(['status' => 'scheduled', 'home_score' => null, 'away_score' => null]);
            Standing::truncate();
        }

        // Strong team should score more goals on average
        $this->assertGreaterThan($weakTeamGoals, $strongTeamGoals);
    }
} 