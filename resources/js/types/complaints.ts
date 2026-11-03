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

export type HandlerRow = {
    id: string;
    name: string;
    roleLabel: string;
    /** "Toda la empresa" or the name of the work center. */
    scope: string;
    designatedOn: string;
};

/** How many people would receive a complaint against this kind of person. */
export type CoverageRow = { kind: string; label: string; count: number };
