export interface Player {
    id: number;
    name: string;
    position: string;
    age: number;
    vision: number;
    passing: number;
    dribbling: number;
    finishing: number;
    tackling: number;
    pace: number;
    value: number;
    fitness: number;
    form: number;
    trait: string | null;
    injuredWeeks: number;
    suspendedWeeks: number;
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
    formation: string;
    mentality: string;
}

export interface TacticOption {
    id: string;
    name: string;
}

export interface SquadProfile {
    meanDecisionGap: number;
    progressivePassShare: number;
    chancesPer90: number;
    goalsPer90: number;
    chancesConcededPer90: number;
    goalsConcededPer90: number;
}

export interface MarginalCell {
    goals: number;
    conceded: number;
}

export interface MarginalRow {
    slot: number;
    name: string;
    attributes: Record<string, MarginalCell>;
}

export interface Marginal {
    delta: number;
    baseline: { goals: number; conceded: number };
    rows: MarginalRow[];
}
