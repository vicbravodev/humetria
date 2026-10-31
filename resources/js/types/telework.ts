/** Someone who can sit on a work center's Comisión, with the role they hold in the company. */
export type CommitteePerson = {
    id: number;
    name: string;
    email: string;
    role: string;
    /** False when they no longer hold a reviewing role: the seat is kept so RH can take it away. */
    active: boolean;
};

export type Committee = {
    /** The day of the constitution act, Y-m-d. */
    constitutedOn: string;
    hasAct: boolean;
    members: CommitteePerson[];
};
