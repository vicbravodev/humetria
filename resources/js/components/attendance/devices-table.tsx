import { Smartphone } from 'lucide-react';
import DevicePersonRow from '@/components/attendance/device-person-row';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import StackedTable from '@/components/humetria/stacked-table';
import {
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DevicePerson, DeviceView, Paginated } from '@/types';

const EMPTY: Record<DeviceView, { title: string; text: string }> = {
    all: {
        title: 'Aún no hay personas aquí',
        text: 'Da de alta a las personas del centro en Empleados; después envíales su enlace de alta.',
    },
    linked: {
        title: 'Nadie ha vinculado su teléfono',
        text: 'Envía el enlace de alta desde «Sin dispositivo»: la persona lo abre en su teléfono y crea su llave.',
    },
    unlinked: {
        title: 'Todas las personas tienen dispositivo',
        text: 'Cuando des de alta a alguien nuevo, aparecerá aquí para enviarle su enlace.',
    },
    revoked: {
        title: 'Ningún dispositivo revocado',
        text: 'Si alguien pierde o cambia su teléfono, revócalo y envíale un enlace nuevo.',
    },
};

/** The people of one tab with their devices, a page at a time, or why there is nobody. */
export default function DevicesTable({
    people,
    status,
    searching,
    canManage,
}: {
    people: Paginated<DevicePerson>;
    status: DeviceView;
    searching: boolean;
    canManage: boolean;
}) {
    if (people.total === 0) {
        return (
            <EmptyState
                icon={Smartphone}
                title={searching ? 'Nadie con ese nombre' : EMPTY[status].title}
            >
                {searching
                    ? 'Revisa cómo lo escribiste o busca con otra parte del nombre.'
                    : EMPTY[status].text}
            </EmptyState>
        );
    }

    return (
        <>
            <StackedTable>
                <TableHeader>
                    <TableRow>
                        <TableHead>Persona</TableHead>
                        <TableHead>Camino al teléfono</TableHead>
                        <TableHead>Dispositivos</TableHead>
                        <TableHead>Estado</TableHead>
                        {canManage && (
                            <TableHead>
                                <span className="sr-only">Acciones</span>
                            </TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {people.data.map((person) => (
                        <DevicePersonRow
                            key={person.id}
                            person={person}
                            canManage={canManage}
                        />
                    ))}
                </TableBody>
            </StackedTable>
            <Pagination
                page={people}
                label="Páginas de personas"
                pinned={false}
            />
        </>
    );
}
