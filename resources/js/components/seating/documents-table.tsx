import { FileText } from 'lucide-react';
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
import type { SeatingDocumentRow, Paginated } from '@/types';

/** The PDFs RH keeps for the inspection: what each is, its date, the incisos it covers and the file. */
export default function DocumentsTable({
    documents,
}: {
    documents: Paginated<SeatingDocumentRow>;
}) {
    if (documents.total === 0) {
        return (
            <EmptyState icon={FileText} title="Sin documentos">
                Guarda aquí el reglamento, las actas de la Comisión y el
                diagnóstico y programa donde se integró la Figura 1.
            </EmptyState>
        );
    }

    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Documento</TableHead>
                        <TableHead>Fecha</TableHead>
                        <TableHead>Incisos</TableHead>
                        <TableHead className="w-px">
                            <span className="sr-only">Archivo</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {documents.data.map((document) => (
                        <TableRow key={document.id}>
                            <TableCell className="font-medium whitespace-normal">
                                {document.title}
                                <span className="block text-caption font-normal text-muted-foreground">
                                    {document.kindLabel}
                                </span>
                            </TableCell>
                            <TableCell>{document.documentOn}</TableCell>
                            <TableCell>
                                {document.incisos.length === 0
                                    ? '—'
                                    : document.incisos.join(', ')}
                            </TableCell>
                            <TableCell className="text-right">
                                <a
                                    href={document.fileHref}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="touch-hit rounded-sm text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    Ver PDF
                                    <span className="sr-only">
                                        {' '}
                                        de {document.title}
                                    </span>
                                </a>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={documents}
                label="Páginas de documentos"
                pinned={false}
            />
        </>
    );
}
