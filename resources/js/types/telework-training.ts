/** A training given at a work center. `topics` are already worded (the 8.1 labels). */
export type TrainingRow = {
    id: string;
    /** Y-m-d. */
    heldOn: string;
    /** "Remota", "Presencial" or "Mixta". */
    modality: string;
    workCenter: { id: string; name: string };
    topics: string[];
    attendees: number;
    hasEvidence: boolean;
};

export type TrainingStandingValue = 'ok' | 'due_soon' | 'overdue';

/** A person of the roster with the day their yearly training falls due. */
export type TrainingPerson = {
    id: string;
    name: string;
    jobTitle: string;
    workCenter: string;
    /** The last training they attended in their center, Y-m-d; null when none. */
    lastTrainingOn: string | null;
    /** Y-m-d. */
    dueOn: string;
    status: TrainingStandingValue;
    statusLabel: string;
};

export type TrainingFilters = {
    center: { value: string; label: string } | null;
};

/** A topic of numeral 8.1, or a modality, as a choice. */
export type TrainingChoice = { value: string; label: string };
