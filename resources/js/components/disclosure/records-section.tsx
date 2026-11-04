import { Megaphone, Plus } from 'lucide-react';
import { useState } from 'react';
import RecordDialog from '@/components/disclosure/record-dialog';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
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
import type { DisseminationRecordRow, Paginated } from '@/types';

/** The physical means recorded as evidence of the disclosure, the newest first, a page at a time (numeral 10.2). */
export default function RecordsSection({
    records,
    today,
}: {
    records: Paginated<DisseminationRecordRow>;
    today: string;
}) {
    const [recording, setRecording] = useState(false);

    return (
        <section aria-label="Medios físicos" className="flex flex-col gap-3.5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-lg leading-[26px] font-semibold">
                    Medios físicos
                </h2>
                <Button variant="outline" onClick={() => setRecording(true)}>
                    <Plus aria-hidden />
                    Registrar un medio
                </Button>
            </div>
            {records.total === 0 ? (
                <EmptyState
                    icon={Megaphone}
                    title="Aún no registras ningún medio"
                >
                    Un cartel, un boletín, un folleto o una reunión cuentan como
                    evidencia de que las personas conocen la política y lo demás
                    que pide el numeral 5.7.
                </EmptyState>
            ) : (
                <>
                    <Table>
                        <TableCaption className="sr-only">
                            Medios físicos de difusión registrados
                        </TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Día</TableHead>
                                <TableHead>Centro</TableHead>
                                <TableHead>Medio</TableHead>
                                <TableHead>Cubrió</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {records.data.map((record) => (
                                <TableRow key={record.id}>
                                    <TableCell>{record.doneOn}</TableCell>
                                    <TableCell className="whitespace-normal">
                                        {record.workCenter}
                                    </TableCell>
                                    <TableCell>{record.mediumLabel}</TableCell>
                                    <TableCell>
                                        {record.items.join(', ')}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination
                        page={records}
                        label="Páginas de medios"
                        pinned={false}
                        previous="Ver más recientes"
                        next="Ver más antiguos"
                    />
                </>
            )}
            {recording && (
                <RecordDialog
                    open={recording}
                    onOpenChange={setRecording}
                    today={today}
                />
            )}
        </section>
    );
}
