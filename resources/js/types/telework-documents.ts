export type DocumentItem = { value: string; label: string };

/** A kind of NOM-037 document, with the items of the norm it can cover (only the policy and the process have any). */
export type DocumentKindOption = {
    value: string;
    label: string;
    /** The numeral of the norm it answers to, when it has one. */
    numeral: string | null;
    items: DocumentItem[];
};

/** One version of a document in one work center. `missingItems` are the letters the version does not cover. */
export type DocumentRow = {
    id: string;
    kind: string;
    workCenter: { id: string; name: string };
    version: number;
    /** Y-m-d in the company's time zone. */
    publishedOn: string;
    coveredItems: string[];
    missingItems: string[];
    /** Null for the kinds nobody has to confirm. */
    acknowledgements: AckProgress | null;
};

export type DocumentFilters = {
    center: { value: string; label: string } | null;
    versions: 'latest' | 'all';
};

/** How many of the people a document is for have confirmed it. */
export type AckProgress = { acknowledged: number; expected: number };

export type AckStatus = 'pending' | 'acknowledged';

/** A person who was asked to confirm a document, with how they did. */
export type AckPerson = {
    id: string;
    name: string;
    jobTitle: string;
    status: AckStatus;
    /** How it was confirmed ("Portal", "En papel", "Panel"); null while pending. */
    channelLabel: string | null;
    /** Y-m-d in the company's time zone. */
    acknowledgedOn: string | null;
};

export type AckCommitteeMember = {
    name: string;
    status: AckStatus;
    acknowledgedOn: string | null;
};

/** "Every pending person the search found", chosen without listing them: the server resolves it. */
export type AckAllMatching = { q: string; count: number };

export type AckFilters = { status: 'all' | AckStatus; q: string };

export type AckDocument = {
    id: string;
    title: string;
    workCenter: string;
    publishedOn: string;
};

/** A published document the person has to read and confirm in their portal. */
export type PortalDocument = { id: string; title: string; publishedOn: string };

/** A version of a NOM-037 document written with questions, as people read it. */
export type WrittenDocument = {
    title: string;
    company: string;
    /** "Autopartes Demo · Planta Querétaro". */
    owner: string;
    version: number;
    /** "12 oct 2026". */
    publishedOn: string;
    sections: { heading: string; text: string }[];
};

/** What publishing a written document means (board V2-Politica): who it reaches and the version in force. */
export type WritingPublication = {
    /** Confirmations it asks for; null for a document nobody confirms. */
    audience: number | null;
    centers: number;
    version: { number: number; publishedOn: string } | null;
};
