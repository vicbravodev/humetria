import type { DemoView } from './demos';
import type { TenantView } from './tenancy';

/** The month of WhatsApp so far: messages, what Meta charged, what the companies were charged and the difference, in cents. */
export type WhatsAppMonth = {
    messages: number;
    costCents: number;
    chargedCents: number;
    marginCents: number;
};

export type PlatformOverviewData = {
    companies: Record<TenantView, number>;
    trialsEndingSoon: number;
    revenueCents: number;
    demos: Record<DemoView, number>;
    inactive: number;
    whatsApp: WhatsAppMonth;
};
