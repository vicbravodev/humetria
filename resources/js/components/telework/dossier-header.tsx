import { describeDate } from '@/lib/calendar-date';
import type { DossierHeader } from '@/types';

/** The top of the dossier: whose it is, where, and when and by whom it was generated. */
export default function DossierHeaderBlock({
    header,
}: {
    header: DossierHeader;
}) {
    return (
        <header className="flex flex-col gap-1">
            <p className="text-[15px] leading-[22px] text-muted-foreground">
                Expediente de NOM-037 para la inspección
            </p>
            <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em] text-balance">
                {header.workCenter}
            </h1>
            <p className="text-[15px] leading-[22px]">
                {header.legalName} · RFC {header.rfc}
            </p>
            <p className="text-[15px] leading-[22px]">{header.address}</p>
            <p className="text-[15px] leading-[22px] text-muted-foreground">
                Generado el{' '}
                {describeDate(header.generatedOn) ?? header.generatedOn} por{' '}
                {header.generatedBy}.
            </p>
        </header>
    );
}
