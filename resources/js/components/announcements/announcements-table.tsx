import { Megaphone } from 'lucide-react';
import AnnouncementTableRow from '@/components/announcements/announcement-table-row';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AnnouncementRow, Paginated } from '@/types';

/** One view of the announcements, a page at a time, each with what it is, to whom, when and where it stands. */
export default function AnnouncementsTable({
    announcements,
    history,
    onEdit,
}: {
    announcements: Paginated<AnnouncementRow>;
    history: boolean;
    onEdit: (announcement: AnnouncementRow) => void;
}) {
    if (announcements.data.length === 0) {
        return (
            <EmptyState
                icon={Megaphone}
                title={
                    history
                        ? 'Todavía no termina ningún aviso'
                        : 'No hay avisos programados'
                }
            >
                {history
                    ? 'Aquí quedan los avisos cuando pasa su fecha de fin.'
                    : 'Un aviso nuevo aparece en el panel de las empresas entre su inicio y su fin.'}
            </EmptyState>
        );
    }

    return (
        <div className="grid gap-4">
            <section className="overflow-hidden card [&_[data-slot=table-container]]:rounded-none [&_[data-slot=table-container]]:border-0">
                <header className="border-b px-6 py-4">
                    <h2 className="text-base font-semibold">Avisos</h2>
                </header>
                <Table className="min-w-[640px] [&_td]:px-3 [&_th]:px-3">
                    <TableCaption className="sr-only">
                        {history
                            ? 'Avisos terminados'
                            : 'Avisos programados y activos'}
                    </TableCaption>
                    <TableHeader>
                        <TableRow className="bg-muted hover:bg-muted">
                            <TableHead>Aviso</TableHead>
                            <TableHead>Tipo</TableHead>
                            <TableHead>A quién</TableHead>
                            <TableHead>Cuándo</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>
                                <span className="sr-only">Acciones</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {announcements.data.map((row) => (
                            <AnnouncementTableRow
                                key={row.id}
                                row={row}
                                onEdit={onEdit}
                            />
                        ))}
                    </TableBody>
                </Table>
            </section>
            <Pagination
                previous="Ver anteriores"
                next="Ver siguientes"
                page={announcements}
                label="Páginas de avisos"
            />
        </div>
    );
}
