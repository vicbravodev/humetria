import type { Auth } from '@/types/auth';
import type { CurrentTenant, TenantSummary } from '@/types/tenancy';

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
            tenant: CurrentTenant | null;
            tenants: TenantSummary[];
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare global {
    // Read by get-nonce (Radix scroll lock) to sign the <style> tags it injects.
    var __webpack_nonce__: string | undefined;
}
