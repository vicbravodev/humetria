import { Megaphone } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { kindTone, stateTone } from '@/lib/announcements';
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
            <div className="overflow-x-auto card p-0">
                <Table className="min-w-[720px]">
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
                            <TableRow key={row.id}>
                                <TableCell className="max-w-[320px] whitespace-normal">
                                    <div className="font-medium">
                                        {row.title}
                                    </div>
                                    <div className="line-clamp-2 text-meta text-muted-foreground">
                                        {row.message}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant={kindTone(row.kind)}>
                                        {row.kind_label}
                                    </Badge>
                                </TableCell>
                                <TableCell>{row.audience_label}</TableCell>
                                <TableCell className="text-muted-foreground tabular-nums">
                                    {row.when}
                                </TableCell>
                                <TableCell>
                                    <Badge variant={stateTone(row.state)}>
                                        {row.state_label}
                                    </Badge>
                                </TableCell>
                                <TableCell className="text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => onEdit(row)}
                                        aria-label={`Editar «${row.title}»`}
                                    >
                                        Editar
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
            <Pagination
                previous="Ver anteriores"
                next="Ver siguientes"
                page={announcements}
                label="Páginas de avisos"
            />
        </div>
    );
}
