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
