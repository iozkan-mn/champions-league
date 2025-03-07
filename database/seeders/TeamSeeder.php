<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            [
                'name' => 'Manchester City',
                'country' => 'England',
                'logo' => 'https://upload.wikimedia.org/wikipedia/tr/f/f6/Manchester_City.png',
                'strength' => 95 
            ],
            [
                'name' => 'Real Madrid',
                'country' => 'Spain',
                'logo' => 'https://upload.wikimedia.org/wikipedia/tr/9/98/Real_Madrid.png',
                'strength' => 96 
            ],
            [
                'name' => 'Bayern Munich',
                'country' => 'Germany',
                'logo' => 'https://upload.wikimedia.org/wikipedia/commons/1/1b/FC_Bayern_München_logo_%282017%29.svg',
                'strength' => 94
            ],
            [
                'name' => 'Paris Saint-Germain',
                'country' => 'France',
                'logo' => 'https://upload.wikimedia.org/wikipedia/tr/thumb/f/f4/PSG_logosu.svg/281px-PSG_logosu.svg.png?20210520090551',
                'strength' => 92
            ],
            [
                'name' => 'Chelsea',
                'country' => 'England',
                'logo' => 'https://upload.wikimedia.org/wikipedia/tr/0/0d/Chelsea_FC.png',
                'strength' => 91
            ],
            [
                'name' => 'Barcelona',
                'country' => 'Spain',
                'logo' => 'https://upload.wikimedia.org/wikipedia/tr/thumb/4/47/FC_Barcelona.png/150px-FC_Barcelona.png',
                'strength' => 90
            ],
            [
                'name' => 'Liverpool',
                'country' => 'England',
                'logo' => 'https://upload.wikimedia.org/wikipedia/tr/thumb/3/3f/150px-Liverpool_FC_logo.png/250px-150px-Liverpool_FC_logo.png',
                'strength' => 93
            ],
            [
                'name' => 'Juventus',
                'country' => 'Italy',
                'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a8/Juventus_FC_-_pictogram_black_%28Italy%2C_2017%29.svg/150px-Juventus_FC_-_pictogram_black_%28Italy%2C_2017%29.svg.png',
                'strength' => 88
            ],
            [
                'name' => 'Beşiktaş',
                'country' => 'Turkey',
                'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/20/Logo_of_Be%C5%9Fikta%C5%9F_JK.svg/220px-Logo_of_Be%C5%9Fikta%C5%9F_JK.svg.png',
                'strength' => 80
            ],
            [
                'name' => 'Galatasaray',
                'country' => 'Turkey',
                'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/37/Galatasaray_Star_Logo.png/800px-Galatasaray_Star_Logo.png',
                'strength' => 83
            ],
            [
                'name' => 'Fenerbahçe',
                'country' => 'Turkey',
                'logo' => 'https://upload.wikimedia.org/wikipedia/tr/thumb/8/86/Fenerbah%C3%A7e_SK.png/150px-Fenerbah%C3%A7e_SK.png',
                'strength' => 82
            ],
            [
                'name' => 'Trabzonspor',
                'country' => 'Turkey',
                'logo' => 'https://upload.wikimedia.org/wikipedia/tr/thumb/a/ab/TrabzonsporAmblemi.png/150px-TrabzonsporAmblemi.png',
                'strength' => 79
            ]
        ];

        foreach ($teams as $team) {
            Team::create($team);
        }
    }
} 