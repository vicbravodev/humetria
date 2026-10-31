import type { Auth } from '@/types/auth';
import type { InCourse } from '@/types/navigation';
import type { EmployeeImportSummary } from '@/types/organization';
import type { ReportLink } from '@/types/results';
import type { CurrentTenant, TenantSummary } from '@/types/tenancy';
import type { FlashToast } from '@/types/ui';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }

    // Custom properties (`--sidebar-width`) are valid inline styles: typed here, never asserted.
    interface CSSProperties {
        [variable: `--${string}`]: string | number | undefined;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** The superadmin working as auth.user; null outside an impersonation. */
            impersonator: string | null;
            tenant: CurrentTenant | null;
            /** The user's first companies, for the switcher, and how many they have. */
            tenants: { data: TenantSummary[]; total: number };
            /** The company's campaigns in course and how close the soonest is to its last day; null outside a company. */
            inCourse: InCourse | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
            /** What a checked employees CSV would do, or its problems; see the import page. */
            import?: EmployeeImportSummary;
            /** A link to a report just opened, shown once: only its hash is kept. */
            reportLink?: ReportLink;
            /** A person's new portal link, shown once: only its hash is kept and the previous one stops working. */
            portalLink?: string;
        };
    }
}

declare global {
    // Read by get-nonce (Radix scroll lock) to sign the <style> tags it injects.
    var __webpack_nonce__: string | undefined;
}
