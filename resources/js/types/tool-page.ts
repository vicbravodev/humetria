/** A block of «Cómo lo resuelve Humetría». */
export type ToolPageFeature = { title: string; text: string };

/** One rule of the law, with the article or numeral it comes from. */
export type ToolPageRule = { text: string; source: string };

/** A figure of the law: when it applies, to whom, the fine. */
export type ToolPageFact = { label: string; value: string };

export type ToolPageFaq = { question: string; answer: string };

export type ToolPageNorm = {
    title: string;
    lead: string;
    rules: ToolPageRule[];
    facts: ToolPageFact[];
};

/** The text of a tool's public page (App\Http\ToolPages::props). */
export type ToolPageContent = {
    key: string;
    name: string;
    h1: string;
    lead: string;
    norm: ToolPageNorm | null;
    features: ToolPageFeature[];
    audience: { company: string; consultant: string };
    privacy: string | null;
    faqs: ToolPageFaq[];
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
