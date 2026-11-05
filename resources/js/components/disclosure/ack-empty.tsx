import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import type { PolicyAckFilters } from '@/types';

/**
 * The list of confirmations with nobody in it (C7): «Nadie coincide» only for a search that found nobody; a view that is empty
 * says why, and what fills it.
 */
export default function AckEmpty({ filters }: { filters: PolicyAckFilters }) {
    if (filters.q !== '') {
        return (
            <EmptyState icon={Users} title="Nadie coincide">
                Prueba con otro nombre o cambia de vista.
            </EmptyState>
        );
    }

    if (filters.status === 'pending') {
        return (
            <EmptyState icon={Users} title="Nadie tiene pendiente confirmar">
                Todas las personas a quienes se les pidió ya confirmaron que
                leyeron esta versión.
            </EmptyState>
        );
    }

    if (filters.status === 'confirmed') {
        return (
            <EmptyState icon={Users} title="Aún nadie confirma">
                Cada persona confirma desde el enlace que le llega por correo;
                quien no tiene correo firma en papel.
            </EmptyState>
        );
    }

    return (
        <EmptyState icon={Users} title="Aún no hay a quién pedirle que la lea">
            Se le pide a cada persona activa de los centros de esta versión.
            Registra a tus personas en sus centros.
        </EmptyState>
    );
}
