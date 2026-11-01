export type RoundStatus = 'open' | 'playing' | 'resolved' | 'failed';

export interface PreviousRound {
    matchday: number;
    status: RoundStatus;
}
