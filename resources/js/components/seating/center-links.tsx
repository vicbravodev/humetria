import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { plan, survey } from '@/routes/tenant/seating';
import { index as assessments } from '@/routes/tenant/seating/assessments';
import { index as documents } from '@/routes/tenant/seating/documents';
import { show as regulation } from '@/routes/tenant/seating/regulation';
import { index as reports } from '@/routes/tenant/seating/reports';
import { index as seats } from '@/routes/tenant/seating/seats';

/** The ways into the parts of a center (pantalla Eval-Activa): seats for everyone who sees the center, the rest for who assesses, the survey for who manages. */
export default function CenterLinks({
    workCenterId,
    canAssess,
    canManage,
}: {
    workCenterId: string;
    canAssess: boolean;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const route = { tenant: tenantSlug, workCenter: workCenterId };
    const links = [
        { title: 'Evaluaciones', href: assessments(route), assess: true },
        { title: 'Plan de acción', href: plan(route), assess: true },
        { title: 'Reglamento', href: regulation(route), assess: true },
        { title: 'Documentos', href: documents(route), assess: true },
        { title: 'Reportes', href: reports(route), assess: true },
        { title: 'Encuesta', href: survey(route), manage: true },
        { title: 'Asientos', href: seats(route) },
    ];

    return (
        <>
            {links
                .filter(
                    (link) =>
                        (link.assess !== true || canAssess) &&
                        (link.manage !== true || canManage),
                )
                .map((link) => (
                    <Button key={link.title} variant="outline" asChild>
                        <Link href={link.href}>{link.title}</Link>
                    </Button>
                ))}
        </>
    );
}
