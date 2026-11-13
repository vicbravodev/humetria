/** The tools (by name) a plan switches on today and the ones it takes away, from `removedOn`. */
export type ModulesChange = {
    added: string[];
    removed: string[];
    removedOn: string | null;
};
