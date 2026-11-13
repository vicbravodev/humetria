import type { Auth } from '@/types/auth';
import type { SalaryImportSummary } from '@/types/compensation';
import type { InCourse } from '@/types/navigation';
import type { LeaveImportSummary } from '@/types/leave-policy';
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
            /** Where to write to Humetria; null while none is configured. */
            support: { email: string | null };
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
            /** What a checked file of opening balances would load, and why each other row cannot; see the vacations import. */
            leave_import?: LeaveImportSummary;
            /** The problems of a salaries CSV that was not imported, shown once; see the tabulator and the individual salaries. */
            salary_import?: SalaryImportSummary;
            /** A link to a report just opened, shown once: only its hash is kept. */
            reportLink?: ReportLink;
            /** A person's new portal link, shown once: only its hash is kept and the previous one stops working. */
            portalLink?: string;
            /** The link of a QR screen just issued, shown once: only its hash is kept, and it opens once. */
            kioskLink?: string;
            /** A person's link to link their phone, shown once to RH only when the person has no email; it works once, 48 h. */
            enrollmentLink?: { name: string; url: string };
        };
    }
}

declare global {
    // Read by get-nonce (Radix scroll lock) to sign the <style> tags it injects.
    var __webpack_nonce__: string | undefined;
}
