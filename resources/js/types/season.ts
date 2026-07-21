export interface StandingRow {
    name: string;
    isUser: boolean;
    played: number;
    won: number;
    drawn: number;
    lost: number;
    goalsFor: number;
    goalsAgainst: number;
    goalDifference: number;
    points: number;
}

export interface FixtureView {
    id: number;
    homeName: string;
    awayName: string;
    homeGoals: number | null;
    awayGoals: number | null;
    played: boolean;
    isUser: boolean;
    isDerby: boolean;
    reportUrl: string | null;
}

export interface Matchday {
    matchday: number;
    fixtures: FixtureView[];
}

export interface NextFixture {
    opponentName: string;
    home: boolean;
}

export interface SeasonHistory {
    number: number;
    position: number;
    points: number;
    teams: number;
}

export interface SeasonObjective {
    target: number;
    position: number;
    teams: number;
    met: boolean | null;
}
