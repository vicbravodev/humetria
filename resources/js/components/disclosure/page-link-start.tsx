import { useForm } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { issue } from '@/routes/tenant/disclosure/page';

/** Before there is a link: what the page is for and the one step that creates it. */
export default function PageLinkStart({ icon }: { icon: LucideIcon }) {
    const tenantSlug = useTenantSlug();
    const form = useForm({});

    return (
        <EmptyState
            icon={icon}
            title="Aún no hay enlace"
            action={
                <Button
                    disabled={form.processing}
                    onClick={() => form.post(issue.url(tenantSlug))}
                >
                    {form.processing && <Spinner />}
                    Crear el enlace
                </Button>
            }
        >
            Con un solo enlace, las personas de todos tus centros consultan lo
            que la norma pide que conozcan. Lo imprimes en un cartel y lo puedes
            desactivar o reemplazar cuando quieras.
        </EmptyState>
    );
}
