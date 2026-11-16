/** A tool in the person's start, as its module offers it: an address with no credential in it. */
export type WorkerSection = {
    key: string;
    label: string;
    description: string;
    href: string;
    /** How many things wait for the person in it, when the tool counts them. */
    pending: number | null;
};

/**
 * How the person proves it is them before something delicate: their key (`key`, never the code then), a code by email
 * (`code`) when they have no key, or neither (`none`: they ask RH for their access). The addresses the step calls, and the
 * minutes a code lasts.
 */
export type ProofStep = {
    mode: 'key' | 'code' | 'none';
    optionsUrl: string;
    proveUrl: string;
    sendCodeUrl: string;
    checkCodeUrl: string;
    minutes: number;
};
