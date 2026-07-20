export interface Player {
    id: number;
    name: string;
    position: string;
    vision: number;
    passing: number;
    dribbling: number;
    finishing: number;
    tackling: number;
    pace: number;
    value: number;
}

export interface PoolPlayer extends Player {
    slot: number | null;
}

export interface SquadSlot {
    slot: number;
    zone: { x: number; y: number };
    position: string;
    player: Player | null;
}

export interface Squad {
    id: number;
    name: string;
    slots: SquadSlot[];
    budget: number;
    spent: number;
    remaining: number;
}

export interface SquadProfile {
    meanDecisionGap: number;
    progressivePassShare: number;
    chancesPer90: number;
    goalsPer90: number;
    chancesConcededPer90: number;
    goalsConcededPer90: number;
}
