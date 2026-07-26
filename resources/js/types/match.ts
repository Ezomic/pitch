export interface MatchMoment {
    minute: number;
    kind: string;
    text: string;
}

export interface TimelineFrame {
    m: number; // minute
    s: 0 | 1; // side: 0 home, 1 away
    x: number; // 0..1, left to right
    y: number; // 0..1, top to bottom
    t: string; // 'pass' | 'dribble' | 'shot'
    ok: boolean;
    goal: boolean;
    start: boolean;
    who: string | null;
}

export interface MatchReport {
    homeGoals: number;
    awayGoals: number;
    shots: number;
    passesCompleted: number;
    progressivePasses: number;
    moments: MatchMoment[];
    timeline: TimelineFrame[];
}

export interface LiveMoment {
    minute: number;
    side: 'home' | 'away';
    kind: string;
    text: string;
}
