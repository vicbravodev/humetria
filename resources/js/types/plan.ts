export type PlanUse = { used: number; limit: number | null };

export type PlanTool = {
    label: string;
    active: boolean;
    endsOn: string | null;
};

export type CompanyPlan = {
    name: string;
    since: string;
    until: string;
} | null;

/** A channel of the company ("Canales de comunicación"): on or off, its place, the owner's monthly cap and the words accepted. */
export type CompanyChannel = {
    channel: 'mail' | 'whatsapp';
    enabled: boolean;
    preference: number;
    monthlyCap: number | null;
    consentVersion: string | null;
};

/** What the page of the channels is given. */
export type CompanyChannelsPage = {
    channels: CompanyChannel[];
    whatsAppAvailable: boolean;
    hasPlan: boolean;
    consent: { version: string; text: string };
    costNotice: string;
    whatsAppQuota: { used: number; quota: number };
};
