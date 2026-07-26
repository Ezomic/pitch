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
    label: string; // server-authored caption for this frame
}

export interface LineupPlayer {
    s: 0 | 1; // side: 0 home, 1 away
    slot: number; // formation slot id (0 = goalkeeper)
    name: string | null; // home player name; null for the unnamed opponent
    x: number; // 0..1 left to right
    y: number; // 0..1 top to bottom
    gk: boolean;
}

export interface PlayerPositions {
    b: number; // index (in lineups order) of the ball carrier, -1 if none
    p: [number, number][]; // [x, y] per player, lineups order, 0..1
}

export interface MatchReport {
    homeGoals: number;
    awayGoals: number;
    shots: number;
    passesCompleted: number;
    progressivePasses: number;
    moments: MatchMoment[];
    timeline: TimelineFrame[];
    lineups: LineupPlayer[];
    positions: PlayerPositions[];
}

export interface LiveMoment {
    minute: number;
    side: 'home' | 'away';
    kind: string;
    text: string;
}
