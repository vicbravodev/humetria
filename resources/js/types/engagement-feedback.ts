import type { EngagementItem } from './engagement-results';

/** A question about the manager with the share favorable of the group and how it stands against the company. */
export type FeedbackItem = EngagementItem & {
    favorable: number;
    vsCompany: number | null;
};

export type SummaryTheme = { theme: string; count: number };

/** The AI summary of a group of comments: themes with how many comments back each, never a comment. */
export type CommentSummary = {
    status: 'pending' | 'ready' | 'withheld' | 'ai_off' | 'failed';
    themes: { valued: SummaryTheme[]; change: SummaryTheme[] } | null;
};

/** What a manager is told about the group that answered about them. No count of people. */
export type ManagerFeedback = {
    items: FeedbackItem[];
    strengths: FeedbackItem[];
    opportunities: FeedbackItem[];
    /** True when the result is of the whole team below the manager, not only the people who report to them directly. */
    line: boolean;
    summary: CommentSummary | null;
};

export type ManagerRow = { id: string; name: string };

export type ManagerComment = { body: string };

export type ManagerResults = {
    survey: { id: string; name: string; status: 'draft' | 'active' | 'closed' };
    available: boolean;
    hasHidden: boolean;
};
