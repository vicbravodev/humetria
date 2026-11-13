import { Inbox } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { demoEmptyText } from '@/lib/demo-requests';
import type { DemoView } from '@/types';

export default function DemoRequestsEmpty({ view }: { view: DemoView }) {
    return (
        <EmptyState
            icon={Inbox}
            title={
                view === 'all'
                    ? 'Aún no hay solicitudes'
                    : 'No hay solicitudes en este estado'
            }
        >
            {demoEmptyText(view)}
        </EmptyState>
    );
}
