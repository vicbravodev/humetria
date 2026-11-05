import { useState } from 'react';
import MediaRow from '@/components/disclosure/media-row';
import RecordDialog from '@/components/disclosure/record-dialog';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/lib/disclosure';
import type { DisseminationRecordRow, Paginated } from '@/types';

/**
 * «Medios físicos» (board V4-Difusion-Registro): the posters, talks and bulletins recorded, newest first, each with its file to
 * see, and «Registrar otro»: one is chosen, a photo or a PDF goes up and the centers are marked. It takes a minute.
 */
export default function MediaCard({
    records,
    today,
}: {
    records: Paginated<DisseminationRecordRow>;
    today: string;
}) {
    const [medium, setMedium] = useState<string | null>(null);

    return (
        <section
            aria-labelledby="media"
            className="flex flex-col gap-4 card p-5 md:p-7"
        >
            <div className="flex flex-col gap-1">
                <h2 id="media" className="text-xl leading-7 font-semibold">
                    Medios físicos
                </h2>
                <p className="text-sm text-muted-foreground">
                    Carteles, pláticas o boletines con los que informaste.
                </p>
            </div>
            {records.total === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Aún no registras ninguno. Un cartel, un boletín, un folleto
                    o una reunión cuentan como evidencia (numeral 10.2).
                </p>
            ) : (
                <ul className="flex flex-col divide-y rounded-lg border">
                    {records.data.map((record) => (
                        <MediaRow key={record.id} record={record} />
                    ))}
                </ul>
            )}
            <Pagination
                page={records}
                label="Páginas de medios"
                pinned={false}
                previous="Ver más recientes"
                next="Ver más antiguos"
            />
            <div className="flex flex-col gap-2">
                <p className="text-sm font-medium">Registrar otro</p>
                <div className="flex flex-wrap gap-2">
                    {MEDIA.map((option) => (
                        <Button
                            key={option.value}
                            variant="outline"
                            size="sm"
                            className="rounded-full"
                            onClick={() => setMedium(option.value)}
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>
                <p className="text-sm text-muted-foreground">
                    Eliges uno, subes una foto o un PDF y marcas lo que cubrió.
                    Toma un minuto.
                </p>
            </div>
            {medium !== null && (
                <RecordDialog
                    open
                    onOpenChange={(open) => setMedium(open ? medium : null)}
                    today={today}
                    initialMedium={medium}
                />
            )}
        </section>
    );
}
