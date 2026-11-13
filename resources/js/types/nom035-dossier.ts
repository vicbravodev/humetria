/** One row of the NOM-035 dossier of a center: the inspection table's requirement with the evidence that backs it. */
export type Nom035DossierRowData = {
    numeral: string;
    title: string;
    state: 'done' | 'partial' | 'missing' | 'not_applicable';
    detail: string | null;
    /** The printable page of the module that keeps it; null when there is none or the person may not open it. */
    evidence: { label: string; href: string } | null;
};

export type Nom035DossierProps = {
    center: { id: string; name: string; address: string };
    issuedOn: string;
    readyOnly: boolean;
    rows: Nom035DossierRowData[];
};
