import type { LevelCounts } from '@/types/results';

/** Answers of the guide that measures an evaluation, against its sample or everyone invited. */
export type DashboardProgress = { completed: number; target: number };

/** The evaluation in course that closes soonest. Dates already written for display ("10 nov 2026"). */
export type ActiveEvaluation = DashboardProgress & {
    id: string;
    workCenter: string;
    kindLabel: string;
    /** Short titles, in order: "Guía I", "Guía III". */
    instruments: string[];
    closesOn: string;
    daysLeft: number;
};

/** Something on the panel's "Lo que sigue": a draft to launch, a 7.7 report to complete or people to channel. */
export type NextStep = {
    kind: 'launch' | 'report' | 'screenings';
    campaignId: string;
    workCenter: string;
    /** For a draft: how many people it covers and its guides. */
    workers: number | null;
    instruments: string[];
    /** For screenings: how many require a clinical assessment. */
    people: number | null;
};

/** Where a work center stands in its two-year NOM-035 cycle (num. 7.9). */
export type CycleRow = {
    id: string;
    name: string;
    workers: number;
    instruments: string[];
    standing: 'in_course' | 'up_to_date' | 'overdue' | 'never';
    /** Launched on, while in course; last evaluated on otherwise. */
    from: string | null;
    /** Closes on, while in course; next due on otherwise. */
    until: string | null;
    /** Percent of the span gone by. */
    elapsed: number;
};

export type ClosedEvaluation = DashboardProgress & {
    id: string;
    workCenter: string;
    instruments: string[];
    closedOn: string;
    /** Questionnaires by risk level; null under the minimum group. */
    final: LevelCounts | null;
};

export type DashboardCounts = { active: number; drafts: number };
