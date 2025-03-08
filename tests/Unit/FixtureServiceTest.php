<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FixtureService;
use App\Services\ChampionshipService;
use App\Models\Team;
use App\Models\Game;
use App\Models\Standing;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FixtureServiceTest extends TestCase
{
    use RefreshDatabase;

    private FixtureService $fixtureService;
    private ChampionshipService $championshipService;
    private array $teams;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureService = new FixtureService();
        $this->championshipService = new ChampionshipService();

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

        $this->assertEquals(12, Game::count()); // 4 teams = 6 games per half season = 12 total
        $this->assertEquals(6, Game::where('week', '<=', 3)->count()); // First half
        $this->assertEquals(6, Game::where('week', '>', 3)->count()); // Second half
    }

    /** @test */
    public function it_ensures_each_team_plays_equal_home_and_away_games()
    {
        $this->fixtureService->generateFixtures();

        foreach (Team::all() as $team) {
            $homeGames = Game::where('home_team_id', $team->id)->count();
            $awayGames = Game::where('away_team_id', $team->id)->count();
            $this->assertEquals(3, $homeGames, "Team {$team->name} should have 3 home games");
            $this->assertEquals(3, $awayGames, "Team {$team->name} should have 3 away games");
        }
    }

    /** @test */
    public function it_simulates_week_with_realistic_scores()
    {
        $this->fixtureService->generateFixtures();
        $this->fixtureService->simulateWeek(1);

        $games = Game::where('week', 1)->get();
        $this->assertNotEmpty($games);

        foreach ($games as $game) {
            $this->assertEquals('completed', $game->status);
            $this->assertNotNull($game->home_score);
            $this->assertNotNull($game->away_score);
            $this->assertGreaterThanOrEqual(0, $game->home_score);
            $this->assertGreaterThanOrEqual(0, $game->away_score);
            $this->assertLessThanOrEqual(5, $game->home_score);
            $this->assertLessThanOrEqual(5, $game->away_score);
        }
    }

    /** @test */
    public function it_updates_standings_correctly_after_simulation()
    {
        $this->fixtureService->generateFixtures();
        $this->championshipService->ensureAllTeamsHaveStandings();
        
        $this->fixtureService->simulateWeek(1);
        $this->championshipService->recalculateStandings();

        $game = Game::where('week', 1)->first();
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

    /** @test */
    public function it_considers_team_strength_in_score_generation()
    {
        $strongTeam = Team::find(1); // 85 strength
        $weakTeam = Team::find(4);   // 70 strength
        
        $strongTeamWins = 0;
        $totalGames = 100;
        
        for ($i = 0; $i < $totalGames; $i++) {
            $scores = $this->fixtureService->generateScore($strongTeam, $weakTeam);
            if ($scores['home'] > $scores['away']) {
                $strongTeamWins++;
            }
        }
        
        $winRate = $strongTeamWins / $totalGames;
        $this->assertGreaterThan(0.5, $winRate, "Strong team should win more than 50% of games");
    }
} 