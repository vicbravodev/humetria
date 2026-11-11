import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    Armchair,
    CalendarDays,
    CircleDollarSign,
    Clock,
    EyeOff,
    Laptop,
    Layers,
    MessageSquareText,
    Network,
    ShieldCheck,
} from 'lucide-react';
import {
    TOOLS,
    type LandingTool,
    type ToolKey,
} from '@/components/landing/landing-tools';

export type FamilyKey = 'compliance' | 'listening' | 'operations';

/** A group of tools that answer the same need, so nine tools read as three choices. */
export type LandingFamily = {
    key: FamilyKey;
    name: string;
    /** The name where a phone has no room for the whole one. */
    short: string;
    hint: string;
    icon: LucideIcon;
    tools: readonly [ToolKey, ...ToolKey[]];
};

const COMPLIANCE: LandingFamily = {
    key: 'compliance',
    name: 'Cumple la norma',
    short: 'Normas',
    hint: 'Instrumentos oficiales de la STPS',
    icon: ShieldCheck,
    tools: ['nom_035', 'nom_037', 'ley_silla', 'attendance'],
};

export const FAMILIES: readonly LandingFamily[] = [
    COMPLIANCE,
    {
        key: 'listening',
        name: 'Escucha a tu gente',
        short: 'Escucha',
        hint: 'Confidencial por diseño',
        icon: EyeOff,
        tools: ['complaints', 'engagement'],
    },
    {
        key: 'operations',
        name: 'Ordena tu operación',
        short: 'Operación',
        hint: 'Personas, saldos y costos',
        icon: Layers,
        tools: ['leave', 'org_chart', 'compensation'],
    },
];

/** The family's tools, in the family's order. */
export function familyTools(family: LandingFamily): LandingTool[] {
    return family.tools.flatMap((key) =>
        TOOLS.filter((tool) => tool.key === key),
    );
}

/** The family a tool belongs to. */
export function familyOf(tool: ToolKey): LandingFamily {
    return FAMILIES.find((family) => family.tools.includes(tool)) ?? COMPLIANCE;
}

export const TOOL_ICONS: Record<ToolKey, LucideIcon> = {
    nom_035: ShieldCheck,
    nom_037: Laptop,
    ley_silla: Armchair,
    attendance: Clock,
    complaints: MessageSquareText,
    engagement: Activity,
    leave: CalendarDays,
    org_chart: Network,
    compensation: CircleDollarSign,
};
