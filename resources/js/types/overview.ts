import type { AuditEntryRow } from './audit';
import type { DemoView } from './demos';
import type { TenantView } from './tenancy';

/** The month of WhatsApp so far: messages, what Meta charged, what the companies were charged and the difference, in cents. */
export type WhatsAppMonth = {
    messages: number;
    costCents: number;
    chargedCents: number;
    marginCents: number;
};

/** An account past due, as «Hoy» names it. */
export type OverdueRow = {
    id: string;
    name: string;
    cents: number;
    days: number;
};

export type PlatformOverviewData = {
    companies: Record<TenantView, number>;
    trialsEndingSoon: number;
    revenueCents: number;
    demos: Record<DemoView, number>;
    inactive: number;
    whatsApp: WhatsAppMonth;
    overdue: { cents: number; accounts: OverdueRow[] };
    demosWaiting: { new: number; unattended: number };
    rejectedToday: number;
    /** What was billed before VAT each of the last twelve months, oldest first. */
    billedByMonth: { month: string; cents: number }[];
    /** The five latest entries of the audit trail. */
    activity: AuditEntryRow[];
};
