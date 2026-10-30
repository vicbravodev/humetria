import { Factory } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import type { WorkCentersView } from '@/types';

export default function WorkCentersEmpty({ view }: { view: WorkCentersView }) {
    return view === 'active' ? (
        <EmptyState icon={Factory} title="Aún no hay centros de trabajo">
            Da de alta cada lugar donde trabaja tu gente: la NOM-035 se aplica
            por centro de trabajo.
        </EmptyState>
    ) : (
        <EmptyState icon={Factory} title="No hay centros archivados">
            Cuando archives un centro de trabajo, aparecerá aquí con su
            historia.
        </EmptyState>
    );
}
