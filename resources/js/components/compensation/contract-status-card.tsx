import {
    HeartHandshake,
    ShieldCheck,
    SlidersHorizontal,
    UsersRound,
} from 'lucide-react';
import AccuracyNotice from '@/components/compensation/accuracy-notice';
import StatusTile from '@/components/compensation/status-tile';
import { useIsUnion } from '@/hooks/use-is-union';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ContractStatus } from '@/lib/compensation/start';
import { statusItems } from '@/lib/compensation/start';
import { index as benefits } from '@/routes/tenant/compensation/benefits';
import { index as tabulator } from '@/routes/tenant/compensation/tabulator';
import { index as employees } from '@/routes/tenant/employees';

/** «Los datos de tu contrato» (board V4-Comp-Inicio): the notice of accuracy and each datum as a tile that opens it. */
export default function ContractStatusCard({
    status,
}: {
    status: ContractStatus;
}) {
    const tenantSlug = useTenantSlug();
    const union = useIsUnion();
    const places = {
        roster: { icon: UsersRound, href: employees(tenantSlug) },
        tabulator: { icon: SlidersHorizontal, href: tabulator(tenantSlug) },
        benefits: { icon: HeartHandshake, href: benefits(tenantSlug) },
        overrides: { icon: UsersRound, href: tabulator(tenantSlug) },
        risk_premium: { icon: ShieldCheck, href: benefits(tenantSlug) },
    };

    return (
        <section className="grid gap-5 card p-6">
            <AccuracyNotice
                accuracy={status.accuracy}
                title="Los datos de tu contrato"
            />
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {statusItems(status, union, false).map((item) => (
                    <StatusTile
                        key={item.key}
                        item={item}
                        icon={places[item.key].icon}
                        href={places[item.key].href}
                    />
                ))}
            </div>
        </section>
    );
}
