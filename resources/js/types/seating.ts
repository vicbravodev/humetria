export type SectorValue = 'services' | 'commerce' | 'analogous' | 'industrial';

/** A work center on the Ley Silla list, with the sector it declared (null while it has not). */
export type SeatingCenterCard = {
    id: string;
    name: string;
    activity: string;
    sector: SectorValue | null;
    sectorLabel: string | null;
};

export type SectorOption = { value: SectorValue; label: string };

/** What a work center declared for Ley Silla: its sector and whether nobody works standing there. */
export type SeatingProfile = {
    sector: SectorValue;
    sectorLabel: string;
    noStandingPositions: boolean;
    declaredOn: string;
    note: string | null;
};

export type PlacementValue = 'at_workstation' | 'nearby' | 'within_center';

/** The result of Figura 1 (A, B or C) with the DOF's words; all null while a position has none (industrial, nature does not allow). */
export type PlacementResult = {
    placement: PlacementValue | null;
    placementLetter: 'A' | 'B' | 'C' | null;
    placementText: string | null;
};

/** A standing position on the list of a center. */
export type PositionRow = PlacementResult & {
    id: string;
    name: string;
    typeLabel: string;
    people: number;
};

export type AreaConditions = Partial<
    Record<
        | 'noise'
        | 'lighting'
        | 'ventilation'
        | 'temperature'
        | 'vibration'
        | 'chemicals',
        string | null
    >
>;

export type StandingPosition = PlacementResult & {
    id: string;
    name: string;
    tasks: string;
    type: string;
    continuousMinutes: number;
    canWorkSeated: boolean;
    hasSpaceForSeat: boolean;
    needsToBeNearby: boolean;
    figureOneApplies: boolean;
    maxPeopleAtOnce: number;
    areaConditions: AreaConditions;
    tools: string | null;
    physicalLoad: string | null;
    attentionLevel: string | null;
    defaultStandingTime: number | null;
    defaultPostureChange: number | null;
    defaultSurface: number | null;
    defaultMovementSpace: number | null;
    defaultPauses: number | null;
    natureAllows: boolean | null;
    natureReason: string | null;
};

export type TypeOption = { value: string; label: string };

/** One condition of Quinta D with the DOF's three options, for the defaults a position gives its people. */
export type ConditionOption = {
    key: string;
    letter: string;
    title: string;
    options: { points: number; text: string }[];
};

/** A person currently holding a position. */
export type PositionPerson = {
    assignmentId: string;
    employeeId: string;
    name: string;
    area: string;
    startsOn: string;
};

export type SeatOption = { value: string; label: string };

/** A seat on the inventory list; the photo link is null for who may not see photos or when there is none. */
export type SeatRow = {
    id: string;
    kindLabel: string;
    useLabel: string;
    quantity: number;
    location: string | null;
    positionName: string | null;
    condition: string;
    conditionLabel: string;
    signposted: boolean;
    features: number;
    photoHref: string | null;
};

/** A seat opened in the form; `features` says which of the nine characteristics it has, by name. */
export type SeatEditing = {
    id: string;
    kind: string;
    use: string;
    quantity: number;
    location: string | null;
    positionId: string | null;
    positionName: string | null;
    features: Record<string, boolean>;
    condition: string;
    signposted: boolean;
    hasPhoto: boolean;
};

export type SeatFeatureOption = SeatOption & { hint: string };

/** A position with fewer seats than people at once. */
export type Shortfall = {
    positionId: string;
    name: string;
    seats: number;
    maxPeopleAtOnce: number;
};

/** One condition of the portal form: its title and the DOF's option text by points (1 to 3). */
export type PortalCondition = {
    title: string;
    options: Record<string, string>;
};

export type AssessmentState = 'none' | 'draft' | 'due' | 'signed';

/** A person of a center with the state of their assessment; `level` is that of the latest signed one. */
export type AssessmentPerson = {
    employeeId: string;
    name: string;
    positionName: string;
    state: AssessmentState;
    stateLabel: string;
    level: 'low' | 'medium' | 'high' | null;
    levelLabel: string | null;
    signedOn: string | null;
    assessmentId: string | null;
};

/** One item (I to XI) of the registry of Quinta C. */
export type AssessmentFact = { numeral: string; label: string; value: string };

/** One of the seven conditions of Quinta D with the DOF's three options. */
export type AssessmentCondition = {
    key: string;
    letter: string;
    title: string;
    options: { points: number; text: string }[];
};

export type AssessmentRecord = {
    id: string;
    status: 'draft' | 'signed';
    statusLabel: string;
    workCenterId: string;
    scores: Record<string, number>;
    verifiedOn: string | null;
    otherAspects: string | null;
    total: number | null;
    level: 'low' | 'medium' | 'high' | null;
    levelLabel: string | null;
    signedOn: string | null;
    reassessmentDue: boolean;
    personProvidedHealthData: boolean;
    version: string | null;
};

/** What Humetría suggests for a level, labelled as a suggestion (the Disposiciones tie no measure to a level). */
export type MeasureSuggestions = {
    label: string;
    measures: { value: string; label: string }[];
};

/** What the person is asked to confirm in their portal: their current signed assessment, until they do. */
export type PortalAcknowledgment = {
    assessmentId: string;
    level: 'low' | 'medium' | 'high' | null;
    levelLabel: string | null;
    signedOn: string | null;
    measures: string[];
};

/** A report of a damaged seat; none names the person who sent it. */
export type SeatReportRow = {
    id: string;
    description: string;
    positionName: string | null;
    reportedOn: string;
    resolution: string | null;
    resolvedOn: string | null;
};

export type Referral = {
    id: string;
    referred: boolean;
    referredOn: string;
    note: string | null;
};
