import { Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/companies';
import type { TenantFilters } from '@/types';

/** The console without companies, or with none that matches the search and the view. */
export default function TenantsEmpty({
    filtered,
    filters,
}: {
    filtered: boolean;
    filters: TenantFilters;
}) {
    return filtered ? (
        <EmptyState
            icon={Building2}
            title={
                filters.q === ''
                    ? 'Ninguna empresa coincide'
                    : 'Nada coincide con tu búsqueda'
            }
            action={
                <Button asChild variant="outline">
                    <Link href={index()}>Quitar filtros</Link>
                </Button>
            }
        >
            Prueba con otro nombre o RFC, o con otro estado.
        </EmptyState>
    ) : (
        <EmptyState icon={Building2} title="Aún no hay empresas">
            Cuando des de alta una empresa, aparecerá aquí con su estado.
        </EmptyState>
    );
}
