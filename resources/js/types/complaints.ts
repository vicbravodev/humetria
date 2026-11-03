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

/** Where else a person may go with a complaint: the federal labor inspection. */
export type ExternalAlternative = {
    name: string;
    email: string;
    phone: string;
    note: string;
};

export type ComplaintTypeOption = {
    value: string;
    label: string;
    hint: string;
};

export type ComplaintKindOption = {
    value: string;
    label: string;
    available: boolean;
};

export type ComplaintCategoryOption = {
    value: string;
    label: string;
    type: string;
};

/** What the public form needs from the server, besides the channel's words. */
export type PublicFormProps = {
    code: string;
    ticket: string;
    types: ComplaintTypeOption[];
    kinds: ComplaintKindOption[];
    categories: ComplaintCategoryOption[];
    descriptionLimits: { min: number; max: number };
    /** Today in the company's time zone, Y-m-d: the last day something can have happened. */
    today: string;
};
