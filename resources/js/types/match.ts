export interface MatchMoment {
    minute: number;
    kind: string;
    text: string;
}

export interface MatchReport {
    homeGoals: number;
    awayGoals: number;
    shots: number;
    passesCompleted: number;
    progressivePasses: number;
    moments: MatchMoment[];
}

export interface LiveMoment {
    minute: number;
    side: 'home' | 'away';
    kind: string;
    text: string;
}
