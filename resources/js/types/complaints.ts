export type ChannelStatus = 'inactive' | 'active' | 'paused';

export type ChannelState = {
    status: ChannelStatus;
    /** The public address of the channel, once it was activated. */
    url: string | null;
    publishedOn: string | null;
    codeRotatedOn: string | null;
};

export type ChannelSettings = {
    procedure: string;
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
    retentionMonths: number;
};

export type Bound = { min: number; max: number };

export type ChannelBounds = {
    acknowledgeWithinDays: Bound;
    resolveWithinDays: Bound;
    retentionMonths: Bound;
};
