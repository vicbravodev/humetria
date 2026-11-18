/** A block of «Cómo lo resuelve Humetría», with its real screenshot (public/images/tools). */
export type ToolPageFeature = {
    kicker: string;
    title: string;
    text: string;
    bullets: string[];
    /** The file name without theme nor extension; one ending in `-mobile` is a phone screen. */
    shot: string;
    alt: string;
    caption: string;
};

/** One rule of the law, with the article or numeral it comes from. */
export type ToolPageRule = { text: string; source: string };

/** A figure of the law: when it applies, to whom, the fine. */
export type ToolPageFact = { label: string; value: string; note: string };

export type ToolPageFaq = { question: string; answer: string };

export type ToolPageNorm = {
    title: string;
    lead: string;
    facts: ToolPageFact[];
    rules: ToolPageRule[];
    note: string | null;
};

/** The piece of the page that answers one question by choice: which guides, how many days, how many hours. */
export type ToolPagePick = {
    title: string;
    lead: string;
    question: string;
    options: { label: string; answer: string; detail: string }[];
};

export type ToolPageRelated = {
    key: string;
    why: string;
    name: string;
    href: string;
};

/** The text of a tool's public page (App\Http\ToolPages::props). */
export type ToolPageContent = {
    key: string;
    name: string;
    family: string;
    chip: string;
    h1: string;
    lead: string;
    proof: string;
    heroPhone: string | null;
    phoneAlt: string | null;
    norm: ToolPageNorm | null;
    guideLink: boolean;
    pick: ToolPagePick | null;
    steps: { title: string; text: string }[];
    features: ToolPageFeature[];
    beforeAfterTitle: string;
    beforeAfter: { before: string; after: string }[];
    audience: { who: string; title: string; text: string }[];
    privacy: { title: string; items: { title: string; text: string }[] } | null;
    faqs: ToolPageFaq[];
    related: ToolPageRelated[];
};

export type GuideSection = {
    heading: string;
    paragraphs: string[];
    sources: string[];
};

/** The guide to the workday of 2027 (App\Http\ToolPages::guide). */
export type GuideContent = {
    title: string;
    description: string;
    h1: string;
    lead: string;
    updated: string;
    sections: GuideSection[];
};
