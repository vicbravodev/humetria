/** One line of a section of the dossier: what exists and, when it has one, its date (Y-m-d). */
export type DossierLine = { text: string; date: string | null };

export type SeatingDossierRow = {
    key: string;
    number: string;
    label: string;
    state: 'met' | 'warning' | 'missing' | 'pending';
    stateLabel: string;
    lines: DossierLine[];
};

export type SeatingDossierHeader = {
    legalName: string;
    rfc: string;
    workCenter: string;
    address: string;
    generatedOn: string;
    generatedBy: string;
};

/** What stays inside the application: how many there are and whether the person can open it. */
export type DossierLink = {
    key: string;
    label: string;
    count: number;
    open: boolean;
};

export type SeatingDossierDocument = {
    kindLabel: string;
    title: string;
    documentOn: string;
    incisos: string[];
};
