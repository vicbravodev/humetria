import type { SurveySection } from '@/lib/engagement/survey-flow';
import type { SurveyUnavailable } from '@/types/campaigns';

/** What the link of a climate or pulse survey opens: the questions, or why it does not open. */
export type EngagementSurveyProps = {
    token: string | null;
    unavailable: SurveyUnavailable | null;
    company: string | null;
    closesOn: string | null;
    kind: 'climate' | 'pulse' | null;
    minutes: number | null;
    minGroup: number | null;
    managerName: string | null;
    agreementLabels: string[];
    openNotice: string;
    sections: SurveySection[];
};
