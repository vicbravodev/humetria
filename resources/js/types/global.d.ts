import type { Auth } from '@/types/auth';
import type { EmployeeImportSummary } from '@/types/organization';
import type { ReportLink } from '@/types/results';
import type { CurrentTenant, TenantSummary } from '@/types/tenancy';
import type { FlashToast } from '@/types/ui';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
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
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
            /** What a checked employees CSV would do, or its problems; see the import page. */
            import?: EmployeeImportSummary;
            /** A link to a report just opened, shown once: only its hash is kept. */
            reportLink?: ReportLink;
        };
    }
}

declare global {
    // Read by get-nonce (Radix scroll lock) to sign the <style> tags it injects.
    var __webpack_nonce__: string | undefined;
}
