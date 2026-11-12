import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { fixedDaysLine } from '@/lib/leave/policy';
import { countOf } from '@/lib/plural';
import { archive, edit } from '@/routes/tenant/vacations/policies';
import type { LeavePolicyCard } from '@/types';

/** One policy of the company: what it gives and whom it is assigned to. A viewer reads it; owner and hr change or archive it. */
export default function PolicyCard({
    policy,
    canManage,
}: {
    policy: LeavePolicyCard;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const target = { tenant: tenantSlug, policy: policy.id };

    return (
        <article className="flex min-w-0 flex-col gap-3 card p-[22px] transition-colors duration-150 ease-out hover:border-input">
            <div className="flex flex-wrap items-center gap-2">
                <h2 className="min-w-0 flex-1 text-lg leading-[26px] font-semibold">
                    {policy.name}
                </h2>
                {policy.archived ? (
                    <Badge variant="secondary">Archivada</Badge>
                ) : (
                    <Badge
                        variant={policy.assigned > 0 ? 'default' : 'outline'}
                    >
                        {policy.assigned > 0
                            ? `Asignada a ${countOf(policy.assigned, 'grupo', 'grupos')}`
                            : 'Sin asignar'}
                    </Badge>
                )}
            </div>
            <p className="text-sm text-muted-foreground">
                {countOf(policy.firstYearDays, 'día', 'días')} el primer año ·
                prima {policy.premiumPercent} %
                {policy.fixedDays > 0 &&
                    ` · ${fixedDaysLine(policy.fixedDays)}`}
            </p>
            {!policy.archived && (
                <div className="mt-auto flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={edit(target)}>
                            <Pencil />
                            {canManage ? 'Editar' : 'Ver'}
                        </Link>
                    </Button>
                    {canManage && policy.assigned === 0 && (
                        <ConfirmDialog
                            trigger={<Button variant="ghost">Archivar</Button>}
                            title={`¿Archivar ${policy.name}?`}
                            description="Dejará de ofrecerse. Los periodos ya otorgados conservan sus términos."
                            confirmLabel="Archivar"
                            form={archive.form(target)}
                            preserveScroll
                        />
                    )}
                </div>
            )}
        </article>
    );
}
