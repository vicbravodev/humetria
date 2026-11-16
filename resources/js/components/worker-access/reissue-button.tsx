import { usePage } from '@inertiajs/react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reissueText } from '@/lib/worker-access/access';
import { reissue } from '@/routes/tenant/worker-access';
import type { AccessPerson, WorkerAccessPageProps } from '@/types';

/** CUJ 7, a lost phone: every key and session of the person stops, and a new link goes to them (or to RH, once). */
export default function ReissueButton({ person }: { person: AccessPerson }) {
    const tenantSlug = useTenantSlug();
    const { linkHours } = usePage<WorkerAccessPageProps>().props;

    return (
        <ConfirmDialog
            trigger={
                <Button variant="outline" size="sm">
                    Reemitir acceso
                </Button>
            }
            title={`¿Reemitir el acceso de ${person.name}?`}
            description={reissueText(person, linkHours)}
            confirmLabel="Reemitir acceso"
            form={reissue.form(tenantSlug)}
            transform={() => ({ employee_id: person.id })}
            preserveScroll
        />
    );
}
