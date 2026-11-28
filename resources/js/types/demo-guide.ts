/** A tour of the demo session with its progress: `done` counts the steps done or skipped. */
export type DemoGuideTour = {
    key: string;
    title: string;
    done: number;
    total: number;
};

/** The step the presenter is on: the catalog's fixed copy, the page «Llévame» opens and the live sentence, if any. */
export type DemoGuideStep = {
    key: string;
    tour: string;
    title: string;
    todo: string;
    why: string;
    kind: 'do' | 'watch';
    href: string;
    target: string | null;
    ask: string | null;
    live: string | null;
    /** What `live` counts, out of how many (when it says so), and the noun for that count: «3», «5», «respuestas». */
    count: number | null;
    of: number | null;
    noun: string | null;
    index: number;
    total: number;
};

/** The guide of the demo session in course (Demos\Contracts\DemoGuide), shared on every page and polled by the dock. */
export type DemoGuide = {
    tours: DemoGuideTour[];
    current: DemoGuideStep | null;
    finished: boolean;
    justDone: string | null;
};
