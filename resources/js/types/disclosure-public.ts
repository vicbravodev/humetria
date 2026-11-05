import type { Paginated } from './demos';
import type { Choice } from './interventions';
import type { RiskLevelValue } from './results';
import type { BoardRequirement } from './disclosure';
import type { PolicyTextSection } from './disclosure';

/** A work center as the public page lists it: its name, nothing else. */
export type PublicCenter = { id: string; name: string };

export type PublicCentersProps = {
    token: string;
    company: string;
    centers: Paginated<PublicCenter>;
    filters: { q: string };
};

export type PublicCenterProps = {
    token: string;
    company: string;
    center: PublicCenter;
    /** Whether the company has another center to choose from. */
    others: boolean;
    /** What the company published for b, c, d and f in the center, by inciso; null while there is nothing. */
    blocks: Record<string, PublicBlockText | null>;
    /** The measures of the Programa RH chose to show, by inciso (b and c): the whole center, never people or areas. */
    measures: Record<string, PublicMeasure[]>;
    /** The channel for complaints as the channel says it; null while it takes nothing. */
    channel: PublicChannel | null;
    /** The results block (5.7 e); null where 7.8 does not apply, a center of up to 15 people. */
    results: PublicResults | null;
    /** The policy in force in the center; null while it has none. */
    policy: {
        version: number;
        publishedOn: string;
        sections: PolicyTextSection[];
    } | null;
};

/** What the company published for a block of 5.7 in a center: the words and the day. */
export type PublicBlockText = {
    text: string;
    /** Its parts under their headings; the one part of a text written by hand has no heading. */
    sections: { heading: string | null; text: string }[];
    publishedOn: string;
};

/** A category or a domain in the results of a center: how many questionnaires fall in each level. */
export type PublicRiskGroup = {
    key: string;
    label: string;
    levels: number[];
};

/** The results the company published for a center, of the whole center and with the minimum applied. */
export type PublicResults = {
    title: string;
    published:
        | { state: 'below_minimum'; closedOn: string }
        | {
              state: 'available';
              closedOn: string;
              guide: 'ii' | 'iii';
              answered: number;
              /** What most needs attention, in words: three domains at most. */
              themes: string[];
              levels: { value: RiskLevelValue; label: string }[];
              final: number[];
              categories: PublicRiskGroup[];
              domains: PublicRiskGroup[];
          }
        | null;
};

/** A measure of the Programa as the public page shows it: what, which level, where it stands and by when. */
export type PublicMeasure = {
    title: string;
    level: number;
    status: string;
    statusLabel: string;
    dueOn: string;
};

/** The channel for complaints and reports: where to go, whether it takes anonymous ones and what it takes. */
export type PublicChannel = {
    url: string;
    anonymousAllowed: boolean;
    categories: string[];
};

export type PosterProps = {
    company: string;
    /** The center whose poster it is; null for the company's, whose QR leads to the list of centers. */
    center: { id: string; name: string } | null;
    url: string;
    /** The QR of the address, an SVG data URI drawn on the server. */
    qr: string;
    /** Whether the page opens; a poster of an inactive link says so on screen, never on paper. */
    active: boolean;
    /** Whether a complaint can be made from the page: only then the poster says so. */
    channel: boolean;
    /** Today as Y-m-d, in the display time zone: the latest day a means can be recorded on. */
    today: string;
};

export type DossierSource = { origin: string; publishedOn: string | null };

/** The disclosure of one work center as it is printed for an inspection: what is recorded, with counts and no names. */
export type DossierProps = {
    header: {
        company: string;
        legalName: string;
        workCenter: string;
        issuedOn: string;
    };
    requirements: BoardRequirement[];
    policy: { version: number; publishedOn: string; covers: string[] } | null;
    acknowledgments: { active: number; confirmed: number } | null;
    records: {
        mediumLabel: string;
        items: string[];
        doneOn: string;
        note: string | null;
    }[];
    link: {
        state: Choice<'active' | 'disabled' | 'expired'>;
        createdOn: string;
        expiresOn: string | null;
    } | null;
    contents: { inciso: string; label: string; sources: DossierSource[] }[];
    results: {
        closedOn: string | null;
        publishedOn: string;
        state: string;
    } | null;
    note: string;
};
