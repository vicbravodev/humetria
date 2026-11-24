import type { Seo } from '@/types/seo';

/** The families whose color dresses a tool (`.family-*` in app.css). */
export type BlogFamily = 'compliance' | 'listening' | 'operations';

/** An article as the list and the suggestions show it. */
export type BlogCardData = {
    slug: string;
    title: string;
    description: string;
    url: string;
    /** Y-m-d, for the machine; `publishedOn` is the same day written out. */
    publishedAt: string;
    publishedOn: string;
    readingMinutes: number;
    tool: string;
    toolName: string;
    family: BlogFamily;
    cover: string;
};

export type BlogHeading = { id: string; text: string };

/** The whole article: its body is HTML the server rendered from Markdown. */
export type BlogArticleData = BlogCardData & {
    coverAlt: string;
    toolUrl: string;
    updatedOn: string | null;
    reviewedOn: string | null;
    headings: BlogHeading[];
    html: string;
    sources: string[];
};

export type BlogIndexProps = {
    seo: Seo;
    articles: BlogCardData[];
    pagination: { previous: string | null; next: string | null };
};

export type BlogShowProps = {
    seo: Seo;
    article: BlogArticleData;
    related: BlogCardData[];
};
