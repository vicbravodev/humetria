import { Timer } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AgreementRow, Paginated } from '@/types';

/** The agreements on pauses with the people of each position, the latest first; the earlier ones stay as history. */
export default function AgreementsTable({
    agreements,
}: {
    agreements: Paginated<AgreementRow>;
}) {
    return (
        <section
            aria-label="Acuerdos de pausas"
            className="flex flex-col gap-4"
        >
            <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                Acuerdos de pausas
            </h2>
            {agreements.total === 0 ? (
                <EmptyState icon={Timer} title="Sin acuerdos de pausas">
                    Registra qué pausas se acordaron con las personas de cada
                    puesto y cómo se llegó al acuerdo.
                </EmptyState>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Puesto</TableHead>
                                <TableHead>Pausas</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Cómo se acordó</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {agreements.data.map((agreement) => (
                                <TableRow key={agreement.id}>
                                    <TableCell className="font-medium">
                                        {agreement.positionName ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {agreement.pausesPerShift} por turno de{' '}
                                        {agreement.minutesEach} min
                                    </TableCell>
                                    <TableCell>{agreement.agreedOn}</TableCell>
                                    <TableCell className="max-w-sm whitespace-normal">
                                        {agreement.howAgreed}
                                        {agreement.evidenceHref !== null && (
                                            <a
                                                href={agreement.evidenceHref}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="touch-hit ml-2 rounded-sm text-primary underline-offset-4 hover:underline"
                                            >
                                                Evidencia
                                            </a>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination
                        page={agreements}
                        label="Páginas de acuerdos"
                        pinned={false}
                    />
                </>
            )}
        </section>
    );
}
