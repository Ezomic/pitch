export interface Scout {
    id: number;
    name: string;
    rating: number;
    cost: number;
    status: string;
    statusLabel: string;
    nextDelivery: string | null;
}
