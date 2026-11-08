import { Head, setLayoutProps } from '@inertiajs/react';
import PolicyForm from '@/components/attendance/policy-form';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/attendance';
import type { AttendancePolicyProps } from '@/types';

/** The company's policy of the registro (pantalla Ajustes: one setting per row, its icon, what it does, the save at the end). */
export default function AttendancePolicyPage({
    policy,
    saved,
    canManage,
}: AttendancePolicyProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Asistencia', href: index(tenantSlug) },
            { title: 'Política' },
        ],
    });

    return (
        <>
            <Head title="Política · Asistencia" />
            <div className="mx-auto flex w-full max-w-[960px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Toda la empresa"
                    title="Política"
                    status={
                        saved ? undefined : (
                            <Badge variant="secondary">Valores de inicio</Badge>
                        )
                    }
                    description={
                        canManage
                            ? 'Cómo marcan las personas de todos tus centros y cuánto se conserva su registro. Cada cambio queda en la bitácora.'
                            : 'Cómo marcan las personas de todos los centros y cuánto se conserva su registro. Solo el dueño y RH la cambian.'
                    }
                />
                <PolicyForm policy={policy} canManage={canManage} />
            </div>
        </>
    );
}
