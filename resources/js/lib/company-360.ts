import { pesos } from '@/lib/overview';
import { countOf } from '@/lib/plural';
import type {
    CompanyDetail,
    CompanyPayer,
    LimitRow,
    ModuleRow,
    OtherUsage,
} from '@/types';

/** The tabs of a company's page (tablero Empresa 360), in their order. */
export const COMPANY_TABS = [
    { id: 'resumen', label: 'Resumen' },
    { id: 'modulos', label: 'Módulos' },
    { id: 'uso', label: 'Uso y límites' },
    { id: 'facturacion', label: 'Facturación' },
    { id: 'miembros', label: 'Miembros' },
    { id: 'invitaciones', label: 'Invitaciones' },
    { id: 'humi', label: 'Humi' },
    { id: 'actividad', label: 'Actividad' },
] as const;

export type CompanyTab = (typeof COMPANY_TABS)[number]['id'];

/** The tab the address asks for (`?tab=`), or the summary. */
export function companyTab(url: string): CompanyTab {
    const asked = new URLSearchParams(url.split('?')[1] ?? '').get('tab');

    return COMPANY_TABS.find((tab) => tab.id === asked)?.id ?? 'resumen';
}

/**
 * Where a module's dialog comes back to after it saves: the modules tab when it opened from its table (no trigger of its own),
 * the summary when it opened from a switch there.
 */
export function returnTo(
    trigger: unknown,
): { query: { tab: 'modulos' } } | undefined {
    return trigger === undefined ? { query: { tab: 'modulos' } } : undefined;
}

/** The line under the company's name: who it is legally, where, since when and who answers for it. */
export function companyLine(company: CompanyDetail): string {
    return [
        company.legal_name,
        company.rfc,
        company.city,
        `Alta: ${company.created_on}`,
        `Responsable: ${company.account_manager?.name ?? 'sin asignar'}`,
    ]
        .filter((part) => part !== null)
        .join(' · ');
}

function owes(payer: CompanyPayer | null): payer is CompanyPayer {
    return payer !== null && payer.overdue_cents > 0;
}

/** The label beside the name while its account owes past due. */
export function overdueBadge(payer: CompanyPayer | null): string | null {
    return owes(payer) ? `Saldo vencido · ${payer.days_past_due} d` : null;
}

/** What the notice says while its account owes past due; a consultant's account owes for every company it pays. */
export function overdueLine(payer: CompanyPayer | null): string | null {
    if (!owes(payer)) {
        return null;
    }

    const amount = pesos(payer.overdue_cents);
    const since = `desde hace ${countOf(payer.days_past_due, 'día', 'días')}`;

    return payer.kind_label === 'Directa'
        ? `Saldo vencido de ${amount} ${since}.`
        : `La cuenta de ${payer.legal_name}, que paga esta empresa, tiene ${amount} vencidos ${since}.`;
}

/** How many modules it has on, what its plan includes and what was added by hand. */
export function moduleSummary(modules: ModuleRow[]): string {
    const on = modules.filter((module) => module.enabled);
    const plan = on.filter((module) => module.source === 'plan').length;
    const granted = on.filter((module) => module.source === 'grant').length;

    const origin = [
        plan > 0 ? `el plan incluye ${plan}` : null,
        granted > 0
            ? `${granted} agregado${granted === 1 ? '' : 's'} a mano`
            : null,
    ].filter((part) => part !== null);
    const count = `${on.length} de ${modules.length} activos`;

    return origin.length === 0 ? count : `${count} · ${origin.join(', ')}`;
}

/** Where one module of the company comes from, under its name. */
export function moduleNote(module: ModuleRow): string {
    if (!module.exists_in_code) {
        return 'Aún no está disponible';
    }

    switch (module.source) {
        case 'plan':
            return 'Incluido en el plan';
        case 'grant':
            return module.ends_on === null
                ? 'Agregado a mano'
                : `Agregado a mano · vence ${module.ends_on}`;
        case 'revoke':
            return 'Quitado a mano';
        case 'legacy':
            return module.source_label ?? 'De antes del plan';
        default:
            return 'No contratado';
    }
}

/** «Cuenta y contacto»: who to talk to, who pays, when it renews and when anyone last came in. */
export function contactFacts(
    company: CompanyDetail,
    renewsOn: string | null,
    payer: CompanyPayer | null,
): [string, string][] {
    return [
        ['Dueño', company.owner?.name ?? 'Sin dueño'],
        ['Correo', company.owner?.email ?? '—'],
        ['Cuenta que paga', payer?.kind_label ?? 'Sin cuenta'],
        ['Próxima renovación', renewsOn ?? 'Sin plan'],
        ['Último acceso', company.last_access ?? 'Nunca'],
    ];
}

export type LimitFigure = {
    key: string;
    label: string;
    used: number;
    of: number;
    /** From 80 % on: worth a look before it runs out. */
    warn: boolean;
};

const WARN_SHARE = 0.8;

function figure(base: Omit<LimitFigure, 'warn'>): LimitFigure {
    return { ...base, warn: base.of > 0 && base.used / base.of >= WARN_SHARE };
}

/** The three figures of the summary: seats and centers of its plan, and the WhatsApp of the month. */
export function limitFigures(
    limits: LimitRow[],
    otherUsage: OtherUsage[],
): LimitFigure[] {
    const whatsApp = otherUsage.find((meter) => meter.meter === 'whatsapp');
    const figures = limits.map((limit) =>
        figure({
            key: limit.resource,
            label: limit.label,
            used: limit.used,
            of: limit.amount,
        }),
    );

    return whatsApp === undefined
        ? figures
        : [
              ...figures,
              figure({
                  key: 'whatsapp',
                  label: 'WhatsApp del mes',
                  used: whatsApp.used,
                  of: whatsApp.quota,
              }),
          ];
}
