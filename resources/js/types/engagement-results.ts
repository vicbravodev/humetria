import type { Delta } from '@/lib/engagement/format';

export type EngagementItem = {
    key: string;
    text: string;
    /** Null when too few answered the question to show it. */
    favorable: number | null;
};

export type EngagementDriver = {
    key: string;
    label: string;
    /** A driver of two questions: reported question by question, with no score of its own. */
    descriptive: boolean;
    /** The survey asked only some of its questions (a pulse asks the anchor): read question by question too. */
    partial: boolean;
    favorable: number | null;
    delta: Delta | null;
    items: EngagementItem[];
};

export type EngagementBands = {
    detractors: number;
    passives: number;
    promoters: number;
};

/** Whether people have seen the improvements the company presented; `null` where the survey did not ask. */
export type EngagementLoopAction = {
    shown: boolean;
    favorable: number | null;
    vsPrevious: Delta | null;
};

export type EngagementRecommendation = {
    shown: boolean;
    index: number | null;
    bands: EngagementBands | null;
};

export type EngagementPriority = {
    driver: string;
    label: string;
    level: 'high' | 'medium';
    levelLabel: string;
};

export type EngagementGroupDriver = {
    key: string;
    label: string;
    descriptive: boolean;
    favorable: number | null;
    vsCompany: number | null;
};

export type EngagementGroup = {
    key: string;
    name: string;
    responses: number;
    index: number | null;
    vsCompany: number | null;
    recommendation: EngagementRecommendation;
    drivers: EngagementGroupDriver[];
    strengths: EngagementItem[];
    opportunities: EngagementItem[];
};

export type EngagementCut = {
    value: 'work_center' | 'area' | 'tenure' | 'manager';
    label: string;
};

export type EngagementParticipation = {
    invited: number;
    responses: number;
    percent: number;
};

export type EngagementResults = {
    survey: { id: string; name: string; status: 'draft' | 'active' | 'closed' };
    available: boolean;
    cut: EngagementCut['value'] | null;
    cuts: EngagementCut[];
    participation: EngagementParticipation | null;
    index: number | null;
    indexDelta: Delta | null;
    stay: { shown: boolean; favorable: number | null } | null;
    recommendation: EngagementRecommendation | null;
    drivers: EngagementDriver[];
    loopAction: EngagementLoopAction | null;
    highlights: {
        strengths: EngagementItem[];
        opportunities: EngagementItem[];
    } | null;
    priorities: EngagementPriority[];
    comparison: { name: string } | null;
    groups: EngagementGroup[] | null;
    hasHidden: boolean;
};

export type EngagementItemDetail = {
    key: string;
    text: string;
    shown: boolean;
    answered: number | null;
    favorable: number | null;
    neutral: number | null;
    unfavorable: number | null;
    /** The share of each of the five options, from "Totalmente en desacuerdo" to "Totalmente de acuerdo". */
    distribution: number[] | null;
    delta: Delta | null;
};

export type EngagementItemSection = {
    key: string;
    label: string;
    score: number | null;
    note: string | null;
    items: EngagementItemDetail[];
};

export type EngagementItems = {
    survey: EngagementResults['survey'];
    available: boolean;
    sections: EngagementItemSection[];
};
