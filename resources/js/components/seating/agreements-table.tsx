import { shortDate } from '@/lib/calendar-date';
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
import AgreementMissingRow from '@/components/seating/agreement-missing-row';
import type { AgreementRow, Paginated } from '@/types';

/**
 * The agreements on pauses with the people of each position, the latest first; the earlier ones stay as history. Under them
 * (board LS-Plan), the positions whose seat goes apart and still have none, with the way to register it.
 */
export default function AgreementsTable({
    agreements,
    missing,
    action,
}: {
    agreements: Paginated<AgreementRow>;
    /** The positions without an agreement, by name. */
    missing: string[];
    /** Where an agreement is registered; null for who may not. */
    action: string | null;
}) {
    return (
        <section
            aria-label="Acuerdos de pausas"
            className="flex flex-col gap-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                    Acuerdos de pausas
                </h2>
                {action !== null && (
                    <a
                        href={action}
                        className="touch-hit inline-flex h-10 items-center rounded-md border border-input bg-card px-4 font-medium hover:bg-muted"
                    >
                        Registrar acuerdo
                    </a>
                )}
            </div>
            {agreements.total === 0 && missing.length === 0 ? (
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
                                    <TableCell>
                                        {shortDate(agreement.agreedOn)}
                                    </TableCell>
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
                            {missing.map((name) => (
                                <AgreementMissingRow
                                    key={name}
                                    name={name}
                                    action={action}
                                />
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
