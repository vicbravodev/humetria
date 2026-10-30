import { Building2 } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/** The console without companies, or with none that matches the search and the view. */
export default function TenantsEmpty({ filtered }: { filtered: boolean }) {
    return filtered ? (
        <EmptyState icon={Building2} title="Ninguna empresa coincide">
            Prueba con otro nombre o RFC, o con otro estado.
        </EmptyState>
    ) : (
        <EmptyState icon={Building2} title="Aún no hay empresas">
            Cuando des de alta una empresa, aparecerá aquí con su estado.
        </EmptyState>
    );
}
