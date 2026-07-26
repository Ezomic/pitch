export interface MatchMoment {
    minute: number;
    kind: string;
    text: string;
}

export interface TimelineFrame {
    m: number; // minute
    s: 0 | 1; // side: 0 home, 1 away
    x1: number; // ball origin, 0..1 left to right
    y1: number; // ball origin, 0..1 top to bottom
    x2: number; // ball destination (receiver or goal)
    y2: number;
    t: string; // 'pass' | 'dribble' | 'shot'
    ok: boolean;
    goal: boolean;
    start: boolean;
    actor: string | null; // player on the ball
    target: string | null; // receiver, or null for a shot
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
