export interface Prospect {
    id: number;
    name: string;
    position: string;
    age: number;
    overall: number;
    potential: number;
    promotable: boolean;
    trainingFocus: string | null;
    attributes: Record<string, number>;
}

export interface YouthFixture {
    id: number;
    opponent: string;
    played: boolean;
    goalsFor: number | null;
    goalsAgainst: number | null;
}
