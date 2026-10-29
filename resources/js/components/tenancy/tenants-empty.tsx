import { Building2 } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

export default function TenantsEmpty() {
    return (
        <EmptyState icon={Building2} title="Aún no hay empresas">
            Cuando des de alta una empresa, aparecerá aquí con su estado.
        </EmptyState>
    );
}
