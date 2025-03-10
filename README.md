# Champions League Simulation

A Laravel-based football championship simulation system that realistically simulates matches and predicts championship chances based on team strengths.

## Requirements

### System Requirements
- PHP >= 8.1
- Node.js >= 16.x
- Composer >= 2.0

### PHP Extensions
- BCMath PHP Extension
- Ctype PHP Extension
- JSON PHP Extension
- Mbstring PHP Extension
- OpenSSL PHP Extension
- PDO PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension

## Assumptions

### League Format
- Minimum 4 teams required
- Maximum 20 teams supported
- Round-robin tournament format
- Each team plays against every other team twice (home and away)
- Season is divided into weeks
- One match per team per week

### Scoring System
- Win: 3 points
- Draw: 1 point
- Loss: 0 points
- Goal difference used as tiebreaker
- Head-to-head results considered in tiebreakers

## Installation

1. Clone the repository
```bash
git clone git@github.com:iozkan-mn/champions-league.git
```

2. Install dependencies
```bash
composer install
npm install
```

3. Set up environment
```bash
cp .env.example .env
php artisan key:generate
```

4. Run migrations
```bash
php artisan migrate:fresh --seed
```

5. Build assets
```bash
composer run dev
```

## Features

### Team Management
- Teams with customizable attributes:
  - Name
  - Strength (0-100)
  - Logo
  - Country
- Balanced fixture generation ensuring equal home/away games
- Support for multiple teams from different countries

### Match Simulation
- Realistic score generation based on:
  - Team strength differences
  - Home advantage (15% boost)
  - Dynamic probability distribution
  - Score range: 0-5 goals
- Advanced scoring algorithm:
  - Base probabilities: 0 (15%), 1 (25%), 2 (25%), 3 (20%), 4 (10%), 5 (5%)
  - Strength impact increases with higher scores
  - Minimum strength bonus for strong teams
  - Maximum score cap at 5 goals

### Championship Management
- Weekly match simulations
- Automatic standings updates:
  - Points (3 for win, 1 for draw)
  - Goals for/against
  - Goal difference
  - Games played/won/drawn/lost
- Championship predictions considering:
  - Current points
  - Remaining matches
  - Team strength
  - Recent form
  - Head-to-head records

### Statistics & Analysis
- Detailed match statistics
- Team performance tracking
- Championship probability calculations
- Form factor analysis
- Tiebreaker rules implementation

## Technical Details

### Services

#### FixtureService
- Generates balanced fixtures for all teams
- Implements round-robin tournament system
- Handles match simulation with realistic scores
- Features:
  - Home/away balance
  - Weekly scheduling
  - Return matches in second half
  - Score generation based on team attributes

#### ChampionshipService
- Manages championship-related operations
- Handles standings and predictions
- Features:
  - Standings management
  - Points calculation
  - Championship predictions
  - Tiebreaker resolution
  - Form factor calculation

### Models
- Team: Stores team information and attributes
- Game: Manages match details and results
- Standing: Tracks team performance and statistics

### Testing
Comprehensive test suite covering:
- Fixture generation
- Match simulation
- Standings calculation
- Team strength impact
- Score distribution
- Championship predictions

## Running Tests

1. Run all tests:
```bash
php artisan test
```

### Test Scenarios

1. Fixture Generation Tests:
   - Verify each team plays against all other teams
   - Ensure each team plays only one match per week
   - Check equal distribution of home/away matches

2. Match Simulation Tests:
   - Validate score generation based on team strengths
   - Test home advantage calculation
   - Verify match results update standings correctly

3. Standings Tests:
   - Check correct calculation of win/draw/loss points
   - Verify goal difference calculation
   - Test proper application of ranking criteria

## Usage

1. Generate fixtures using the "Generate" button
2. Simulate matches week by week or all at once
3. View standings and predictions updated in real-time
