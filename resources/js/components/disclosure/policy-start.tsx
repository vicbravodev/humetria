import { useForm } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/disclosure/policy/draft';

/** Before there is a draft: what the policy is for and the one step that starts it (pantalla Eval-Borrador). */
export default function PolicyStart({ canManage }: { canManage: boolean }) {
    const tenantSlug = useTenantSlug();
    const form = useForm({});

    return (
        <EmptyState
            icon={FileText}
            title="Aún no hay un borrador"
            action={
                canManage ? (
                    <Button
                        disabled={form.processing}
                        onClick={() => form.post(store.url(tenantSlug))}
                    >
                        {form.processing && <Spinner />}
                        Redactar a partir de la plantilla
                    </Button>
                ) : undefined
            }
        >
            La norma pide una política de prevención por escrito (numeral 5.1).
            Parte del ejemplo de la Guía de referencia IV: lo editas, quitas lo
            que no aplique y la publicas para tus centros.
        </EmptyState>
    );
}
