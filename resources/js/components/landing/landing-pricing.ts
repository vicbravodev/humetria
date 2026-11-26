/**
 * What the landing says Humetría costs, in pesos before VAT, and what each plan includes. The figures and the modules are
 * the commercial catalog's (config/billing.php, its current version): a test pins them to it (tests/Feature/LandingPricingMatchesCatalogTest.php),
 * so change both or neither.
 */
export type PricingPlan = {
    id: string;
    name: string;
    /** Per person per month. */
    rate: number;
    /** The least a company pays a month, whatever its headcount. */
    minimum: number;
    centers: number;
    featured: boolean;
    /** The catalog's module keys the plan includes. */
    modules: readonly string[];
};

/** From this many people up the catalog has no table: the price is quoted by hand. */
export const QUOTE_ABOVE = 100;

/** The headcount the calculator starts at: a mid-size company, where the plans already differ. */
export const DEFAULT_PEOPLE = 50;

export const PRICING_PLANS: readonly PricingPlan[] = [
    {
        id: 'jornada',
        name: 'Jornada',
        rate: 39,
        minimum: 1990,
        centers: 1,
        featured: false,
        modules: ['attendance', 'vacations', 'org_chart'],
    },
    {
        id: 'cumplimiento',
        name: 'Cumplimiento',
        rate: 49,
        minimum: 2990,
        centers: 2,
        featured: false,
        modules: [
            'nom035',
            'nom037',
            'seating',
            'complaints',
            'pulse',
            'climate',
        ],
    },
    {
        id: 'integral',
        name: 'Integral',
        rate: 99,
        minimum: 4990,
        centers: 3,
        featured: true,
        modules: [
            'attendance',
            'vacations',
            'org_chart',
            'nom035',
            'nom037',
            'seating',
            'complaints',
            'pulse',
            'climate',
            'compensation',
            'sso',
        ],
    },
];

/** A line of the comparison: what a plan has, by the catalog's modules. */
export type PricingRow = { label: string; keys: readonly string[] };

export const PRICING_ROWS: readonly PricingRow[] = [
    { label: 'Asistencia y registro de jornada', keys: ['attendance'] },
    { label: 'Vacaciones y permisos', keys: ['vacations'] },
    { label: 'Organigrama', keys: ['org_chart'] },
    { label: 'NOM-035', keys: ['nom035'] },
    { label: 'NOM-037 (teletrabajo)', keys: ['nom037'] },
    { label: 'Ley Silla', keys: ['seating'] },
    { label: 'Buzón de quejas', keys: ['complaints'] },
    { label: 'Clima y pulso', keys: ['climate', 'pulse'] },
    { label: 'Compensación', keys: ['compensation'] },
    { label: 'Inicio de sesión único (SSO)', keys: ['sso'] },
];

/** Whether the plan has what the line names. */
export function planHas(plan: PricingPlan, row: PricingRow): boolean {
    return row.keys.every((key) => plan.modules.includes(key));
}

export const PRICING_INTRO =
    'Pagas por persona al mes, con un mínimo por plan. Todos los precios van en pesos mexicanos (MXN) más IVA.';

export const PRICING_NOTES = [
    'Cada centro de trabajo adicional al incluido en tu plan cuesta $149 MXN al mes.',
    'Compensación también se contrata sola: te cotizamos según tu plantilla.',
    'Con más de 100 personas te cotizamos a tu medida.',
    'La implementación asistida y el acompañamiento mensual se cotizan aparte.',
] as const;
