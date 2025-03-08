import React from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Table from '@/Components/Table';
import TableRow from '@/Components/TableRow';
import TableCell from '@/Components/TableCell';
import axios from 'axios';

export default function Home({ games = [], teams = [], currentWeek = 1, hasScheduledGames = false, predictions = null }) {
    const generateFixtures = () => {
        axios.post(route('games.generate-fixtures'))
            .then(response => {
                window.location.reload();
            })
            .catch(error => {
                console.error('Error generating fixtures:', error);
            });
    };

    const simulateWeek = () => {
        axios.post(route('games.simulate-week'))
            .then(response => {
                window.location.reload();
            })
            .catch(error => {
                console.error('Error simulating week:', error);
            });
    };

    const simulateAll = () => {
        axios.post(route('games.play-all-weeks'))
            .then(response => {
                window.location.reload();
            })
            .catch(error => {
                console.error('Error simulating all weeks:', error);
            });
    };

    const resetData = () => {
        axios.post(route('games.reset-data'))
            .then(response => {
                window.location.reload();
            })
            .catch(error => {
                console.error('Error resetting data:', error);
            });
    };

    const tableHeaders = [
        'Week',
        'Date',
        'Home Team',
        'Score',
        'Away Team',
        'Status'
    ];

    return (
        <AppLayout title="Championship League Simulation">
            <div className="space-y-6">
                {games.length > 0 && (
                    <div className="mt-8">
                        <h2 className="text-2xl font-bold mb-4">Championship Predictions</h2>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            {teams.map(team => (
                                <div key={team.id} className="bg-white p-4 rounded-lg shadow">
                                    <div className="flex items-center space-x-2 mb-2">
                                        <img 
                                            src={team.logo} 
                                            alt={team.name} 
                                            className="w-8 h-8 object-contain"
                                        />
                                        <span className="font-semibold">{team.name}</span>
                                    </div>
                                    <div className="text-2xl font-bold text-indigo-600">
                                        {predictions ? predictions[team.id] : 0}%
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {teams.length > 0 && (
                    <div className="mt-8">
                        <h2 className="text-2xl font-bold mb-4">Standings</h2>
                        <Table headers={['Team', 'P', 'W', 'D', 'L', 'GF', 'GA', 'GD', 'Pts']}>
                            {teams
                                .sort((a, b) => {
                                    // If both teams have standings, sort by points and other criteria
                                    if (a.standing && b.standing) {
                                        if (b.standing.points !== a.standing.points) {
                                            return b.standing.points - a.standing.points;
                                        }
                                        const aGD = a.standing.goals_for - a.standing.goals_against;
                                        const bGD = b.standing.goals_for - b.standing.goals_against;
                                        if (bGD !== aGD) {
                                            return bGD - aGD;
                                        }
                                        return b.standing.goals_for - a.standing.goals_for;
                                    }
                                    // If no standings or only one team has standings, sort alphabetically
                                    return a.name.localeCompare(b.name);
                                })
                                .map(team => (
                                    <TableRow key={team.id}>
                                        <TableCell>
                                            <div className="flex items-center space-x-2">
                                                <img 
                                                    src={team.logo} 
                                                    alt={team.name} 
                                                    className="w-6 h-6 object-contain"
                                                />
                                                <span>{team.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>{team.standing ? team.standing.played : 0}</TableCell>
                                        <TableCell>{team.standing ? team.standing.won : 0}</TableCell>
                                        <TableCell>{team.standing ? team.standing.drawn : 0}</TableCell>
                                        <TableCell>{team.standing ? team.standing.lost : 0}</TableCell>
                                        <TableCell>{team.standing ? team.standing.goals_for : 0}</TableCell>
                                        <TableCell>{team.standing ? team.standing.goals_against : 0}</TableCell>
                                        <TableCell>
                                            {team.standing ? team.standing.goals_for - team.standing.goals_against : 0}
                                        </TableCell>
                                        <TableCell className="font-bold">
                                            {team.standing ? team.standing.points : 0}
                                        </TableCell>
                                    </TableRow>
                                ))}
                        </Table>
                    </div>
                )}

                <div className="flex justify-between items-center">
                    <div className="space-x-4">
                        {games.length === 0 && (
                            <Button onClick={generateFixtures}>
                                Generate Fixtures
                            </Button>
                        )}
                        {hasScheduledGames && (
                            <>
                                <Button onClick={simulateWeek}>
                                    Simulate Week {currentWeek}
                                </Button>
                                <Button onClick={simulateAll}>
                                    Play All Weeks
                                </Button>
                            </>
                        )}
                        {games.length > 0 && (
                            <Button onClick={resetData} className="bg-red-600 hover:bg-red-700">
                                Reset Data
                            </Button>
                        )}
                    </div>
                </div>

                {games.length > 0 && (
                    <Table headers={tableHeaders}>
                        {games.map(game => (
                            <TableRow key={game.id}>
                                <TableCell>{game.week}</TableCell>
                                <TableCell>{new Date(game.match_date).toLocaleDateString()}</TableCell>
                                <TableCell>
                                    <div className="flex items-center space-x-2">
                                        <img 
                                            src={game.home_team.logo} 
                                            alt={game.home_team.name} 
                                            className="w-6 h-6 object-contain"
                                        />
                                        <span>{game.home_team.name}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {game.status === 'completed' ? (
                                        <span className="font-bold">
                                            {game.home_score} - {game.away_score}
                                        </span>
                                    ) : (
                                        <span>vs</span>
                                    )}
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center space-x-2">
                                        <img 
                                            src={game.away_team.logo} 
                                            alt={game.away_team.name} 
                                            className="w-6 h-6 object-contain"
                                        />
                                        <span>{game.away_team.name}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span className={`px-2 py-1 rounded-full text-xs ${
                                        game.status === 'completed' ? 'bg-green-100 text-green-800' :
                                        game.status === 'live' ? 'bg-red-100 text-red-800' :
                                        'bg-gray-100 text-gray-800'
                                    }`}>
                                        {game.status}
                                    </span>
                                </TableCell>
                            </TableRow>
                        ))}
                    </Table>
                )}                
            </div>
        </AppLayout>
    );
} 