import type { DossierNotices } from '@/types';

/** 5.11: how many notices of each kind, and how many were reviewed. What they say stays in the application. */
export default function DossierNoticesBlock({
    notices,
}: {
    notices: DossierNotices;
}) {
    const total = notices.reviewed + notices.pending;

    return (
        <div className="flex flex-col gap-1 text-[15px] leading-[22px]">
            <p>
                {total === 0
                    ? 'Nadie ha enviado un aviso.'
                    : `${total} avisos: ${notices.reviewed} revisados y ${notices.pending} por revisar.`}
            </p>
            {notices.byKind.length > 0 && (
                <p>
                    {notices.byKind
                        .map((kind) => `${kind.label}: ${kind.count}`)
                        .join('; ')}
                    .
                </p>
            )}
            <p className="text-muted-foreground">
                Los exámenes médicos del 5.11 no los cubre Humetría: se llevan
                por separado.
            </p>
        </div>
    );
}
