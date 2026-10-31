import { Link } from '@inertiajs/react';
import { Download, UserCheck } from 'lucide-react';
import ProgressBar from '@/components/humetria/progress-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { describeDate } from '@/lib/calendar-date';
import { progressText } from '@/lib/telework/acknowledgements';
import { missingLabels } from '@/lib/telework/documents';
import { file } from '@/routes/tenant/telework/documents';
import { index as acknowledgements } from '@/routes/tenant/telework/documents/acknowledgements';
import type { DocumentKindOption, DocumentRow as Row } from '@/types';

/** One version of a document in one work center: its date, what it covers and what it leaves out. */
export default function DocumentRow({
    tenantSlug,
    row,
    kind,
}: {
    tenantSlug: string;
    row: Row;
    kind: DocumentKindOption | undefined;
}) {
    const missing = missingLabels(kind, row.missingItems);
    const items = kind?.items.length ?? 0;

    return (
        <li className="flex flex-col gap-3 rounded-xl border bg-card px-[22px] py-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex min-w-0 flex-col">
                    <p className="font-medium">
                        {kind?.label ?? row.kind} · versión {row.version}
                    </p>
                    <p className="text-[13px] leading-[18px] text-muted-foreground">
                        {row.workCenter.name} · publicada el{' '}
                        {describeDate(row.publishedOn) ?? row.publishedOn}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    {items > 0 && (
                        <Badge
                            variant={
                                missing.length === 0 ? 'success' : 'warning'
                            }
                        >
                            {items - missing.length} de {items} incisos
                        </Badge>
                    )}
                    <Button variant="outline" asChild>
                        <a
                            href={file.url({
                                tenant: tenantSlug,
                                document: row.id,
                            })}
                        >
                            <Download aria-hidden />
                            Descargar
                        </a>
                    </Button>
                </div>
            </div>
            {row.acknowledgements !== null && (
                <div className="flex flex-col gap-2">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm font-medium">
                            Confirmaron que la leyeron:{' '}
                            {progressText(row.acknowledgements)}
                        </p>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={acknowledgements.url({
                                    tenant: tenantSlug,
                                    document: row.id,
                                })}
                            >
                                <UserCheck aria-hidden />
                                Ver confirmaciones
                            </Link>
                        </Button>
                    </div>
                    <ProgressBar
                        value={row.acknowledgements.acknowledged}
                        max={row.acknowledgements.expected}
                        label="Personas que confirmaron"
                    />
                </div>
            )}
            {missing.length > 0 && (
                <div>
                    <p className="text-sm font-medium">Incisos que faltan</p>
                    <ul className="list-inside list-disc text-sm text-muted-foreground">
                        {missing.map((label) => (
                            <li key={label}>{label}</li>
                        ))}
                    </ul>
                </div>
            )}
        </li>
    );
}
