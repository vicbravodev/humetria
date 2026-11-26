import Initials from '@/components/humetria/initials';
import TenantPeekBody from '@/components/tenancy/tenant-peek-body';
import type { TenantRow } from '@/types';

/** The quick view docked beside the table on a wide screen (tablero Empresas): the company chosen, always in sight. */
export default function TenantPeekPanel({ tenant }: { tenant: TenantRow }) {
    return (
        <aside
            aria-labelledby={`peek-${tenant.id}`}
            className="sticky top-6 w-[340px] shrink-0 card"
        >
            <div className="flex items-center gap-3 border-b px-6 pt-5 pb-4">
                <Initials name={tenant.trade_name} kind="company" />
                <div className="grid min-w-0 gap-0.5">
                    <h2 id={`peek-${tenant.id}`} className="font-semibold">
                        {tenant.trade_name}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Vista rápida
                    </p>
                </div>
            </div>
            <div className="px-6 py-5">
                <TenantPeekBody tenant={tenant} />
            </div>
        </aside>
    );
}
