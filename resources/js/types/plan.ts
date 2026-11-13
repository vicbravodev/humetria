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
