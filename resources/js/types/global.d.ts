import type { Auth } from '@/types/auth';
import type { SalaryImportSummary } from '@/types/compensation';
import type { DemoShareLink } from '@/types/demos';
import type { FirstSteps } from '@/types/first-steps';
import type { InCourse } from '@/types/navigation';
import type { LeaveImportSummary } from '@/types/leave-policy';
import type { EmployeeImportSummary } from '@/types/organization';
import type { ReportLink } from '@/types/results';
import type { CurrentTenant, TenantSummary } from '@/types/tenancy';
import type { FlashToast } from '@/types/ui';
import type { ProofStep } from '@/types/worker-access';

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
            /** Humetría's social profiles for the landing footer: only the ones configured, always https. */
            social: {
                network: 'linkedin' | 'facebook' | 'instagram';
                url: string;
            }[];
            auth: Auth;
            /** The superadmin working as auth.user; null outside an impersonation. */
            impersonator: string | null;
            tenant: CurrentTenant | null;
            /** The user's first companies, for the switcher, and how many they have. */
            tenants: { data: TenantSummary[]; total: number };
            /** The company's campaigns in course and how close the soonest is to its last day; null outside a company. */
            inCourse: InCourse | null;
            /** The owner's guided tour of the company; null for anyone else, outside a company, once finished or when switched off. */
            firstSteps: FirstSteps | null;
            /** Whether the company of the page is the demo: then every page, its reports above all, carries the «DEMO» mark. */
            demo: boolean;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: FlashToast;
            /** The id of the demo request just stored, once, on the next landing visit: all the browser reports as a conversion. */
            lead?: { id: string };
            /** What a checked employees CSV would do, or its problems; see the import page. */
            import?: EmployeeImportSummary;
            /** What a checked file of opening balances would load, and why each other row cannot; see the vacations import. */
            leave_import?: LeaveImportSummary;
            /** The problems of a salaries CSV that was not imported, shown once; see the tabulator and the individual salaries. */
            salary_import?: SalaryImportSummary;
            /** A link to a report just opened, shown once: only its hash is kept. */
            reportLink?: ReportLink;
            /** The link of a QR screen just issued, shown once: only its hash is kept, and it opens once. */
            kioskLink?: string;
            /** The demo room's door, just opened: its address and QR, shown once (only its hash is kept). */
            doorLink?: DemoShareLink;
            /** The links of the demo's time-clock screens, one per center, just issued from the demo room, with their QR: shown once. */
            demoKioskLinks?: (DemoShareLink & { center: string })[];
            /** A person's link to enter and create their key, shown once to RH only when the person has no email; it works once, 48 h. */
            accessLink?: { name: string; url: string };
            /** A delicate form of the person was not taken: they prove it is them (this step) and it is sent again. */
            proofRequired?: ProofStep;
        };
    }
}

declare global {
    // Read by get-nonce (Radix scroll lock) to sign the <style> tags it injects.
    var __webpack_nonce__: string | undefined;
}
