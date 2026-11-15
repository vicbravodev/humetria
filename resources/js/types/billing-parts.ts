/** The tools (by name) a plan switches on today and the ones it takes away, from `removedOn`. */
export type ModulesChange = {
    added: string[];
    removed: string[];
    removedOn: string | null;
};

/** One entry of a SAT catalog: its code (what is stored) and its label with the name. */
export type SatOption = { value: string; label: string };
