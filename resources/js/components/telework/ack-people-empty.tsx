import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import type { AckFilters } from '@/types';

const EMPTY: Record<AckFilters['status'], { title: string; text: string }> = {
    all: {
        title: 'Nadie tiene que confirmarlo',
        text: 'Cuando haya personas en teletrabajo en el centro, aparecerán aquí.',
    },
    pending: {
        title: 'Nadie tiene pendiente',
        text: 'Todas las personas confirmaron que lo leyeron.',
    },
    acknowledged: {
        title: 'Nadie ha confirmado todavía',
        text: 'Cada persona lo confirma desde su portal, o tú registras su acuse en papel.',
    },
};

/** When the search leaves nobody, or nobody is in the view yet (C7). */
export default function AckPeopleEmpty({ filters }: { filters: AckFilters }) {
    if (filters.q.trim() !== '') {
        return (
            <EmptyState icon={Users} title="Nadie coincide">
                Prueba con otro nombre o cambia de vista.
            </EmptyState>
        );
    }

    const empty = EMPTY[filters.status];

    return (
        <EmptyState icon={Users} title={empty.title}>
            {empty.text}
        </EmptyState>
    );
}
