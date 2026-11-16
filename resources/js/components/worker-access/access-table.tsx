import { KeyRound } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import StackedTable from '@/components/humetria/stacked-table';
import {
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AccessRow from '@/components/worker-access/access-row';
import type { AccessPerson, AccessView, Paginated } from '@/types';

const EMPTY: Record<AccessView, { title: string; text: string }> = {
    all: {
        title: 'Aún no hay personas aquí',
        text: 'Da de alta a las personas del centro en Empleados; después invítalas a crear su llave.',
    },
    with_key: {
        title: 'Nadie ha creado su llave',
        text: 'Invita a las personas desde «Sin invitar»: abren el enlace en su teléfono y crean su llave.',
    },
    invited: {
        title: 'Ninguna invitación en espera',
        text: 'Cuando invites a alguien, aparecerá aquí hasta que cree su llave o venza su enlace.',
    },
    not_invited: {
        title: 'Todas las personas tienen llave o invitación',
        text: 'Cuando des de alta a alguien nuevo, aparecerá aquí para invitarle.',
    },
};

/** The people of one tab with their keys, a page at a time, or why there is nobody. */
export default function AccessTable({
    people,
    status,
    searching,
    canManage,
}: {
    people: Paginated<AccessPerson>;
    status: AccessView;
    searching: boolean;
    canManage: boolean;
}) {
    if (people.total === 0) {
        return (
            <EmptyState
                icon={KeyRound}
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
                        <TableHead>Invitación</TableHead>
                        <TableHead>Llaves</TableHead>
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
                        <AccessRow
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
