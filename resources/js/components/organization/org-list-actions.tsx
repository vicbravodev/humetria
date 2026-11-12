import { Link } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create as importPage } from '@/routes/tenant/employees/import';
import { exportMethod } from '@/routes/tenant/org-chart/quality';

/** On the list: the way to bring the people in with a CSV, and to take the list out as one. */
export default function OrgListActions() {
    const tenantSlug = useTenantSlug();

    return (
        <>
            <Button variant="ghost" asChild>
                <Link href={importPage(tenantSlug)}>
                    <Upload />
                    Subir CSV de empleados
                </Link>
            </Button>
            <Button variant="outline" asChild>
                {/* A file, not a page: a plain link, so the browser downloads it. */}
                <a href={exportMethod.url(tenantSlug)}>
                    <Download />
                    Descargar la lista (CSV)
                </a>
            </Button>
        </>
    );
}
