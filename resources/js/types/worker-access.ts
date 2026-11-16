/** A tool in the person's start, as its module offers it: an address with no credential in it. */
export type WorkerSection = {
    key: string;
    label: string;
    description: string;
    href: string;
    /** How many things wait for the person in it, when the tool counts them. */
    pending: number | null;
};
