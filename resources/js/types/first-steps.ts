export type FirstStepKey = 'work_centers' | 'people' | 'evaluation' | 'team' | 'humi';
export type FirstStepTool = 'nom035' | 'nom037' | 'seating' | 'attendance';

export type FirstStep = { key: FirstStepKey; done: boolean; href: string | null; tool: FirstStepTool | null };

/** The guided tour of the company's owner: its steps, whether the welcome was seen and the card hidden, and the tools left for later. */
export type FirstSteps = {
    steps: FirstStep[];
    welcomed: boolean;
    hidden: boolean;
    others: { key: FirstStepTool; href: string }[];
};
