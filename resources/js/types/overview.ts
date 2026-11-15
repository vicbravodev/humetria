import type { DemoView } from './demos';
import type { TenantView } from './tenancy';

export type PlatformOverviewData = {
    companies: Record<TenantView, number>;
    trialsEndingSoon: number;
    revenueCents: number;
    demos: Record<DemoView, number>;
    inactive: number;
};
