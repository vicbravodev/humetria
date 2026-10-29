import { Inbox } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

export default function DemoRequestsEmpty() {
    return (
        <EmptyState icon={Inbox} title="Aún no hay solicitudes">
            Cuando alguien llene el formulario de la landing, su solicitud
            aparecerá aquí.
        </EmptyState>
    );
}
