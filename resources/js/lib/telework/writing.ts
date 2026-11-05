import type { SupportingDocument, WritingPublication } from '@/types';

/**
 * What the review of a written NOM-037 document says about publishing it (board V2-Politica): who it reaches, and the
 * button. The policy is «la» política; the other documents, «el» documento.
 */
export function publishCopy(
    policy: boolean,
    publication: WritingPublication,
): { text: string; action: string } {
    const it = policy ? 'la' : 'lo';
    const centers =
        publication.centers === 1
            ? 'tu centro'
            : `tus ${publication.centers} centros`;

    if (publication.audience !== null && publication.audience > 0) {
        const one = publication.audience === 1;
        const people = one
            ? `a la persona de ${centers} para que confirme que ${it} leyó`
            : `a las ${publication.audience} personas de ${centers} para que confirmen que ${it} leyeron`;

        return {
            text: `Al publicar${it}, le llega ${people}.`,
            action: one
                ? 'Publicar para 1 persona'
                : `Publicar para ${publication.audience} personas`,
        };
    }

    return {
        text: `Al publicar${it}, queda vigente en ${centers} y se suma a tu expediente. Las versiones anteriores no se borran.`,
        action: 'Publicar',
    };
}

/** How far the six supporting documents are (board V2-N037-Docs): how many are ready and about how long the rest take. */
export function supportingProgress(documents: SupportingDocument[]): {
    ready: number;
    minutes: number;
} {
    const missing = documents.filter(({ ready }) => !ready);

    return {
        ready: documents.length - missing.length,
        minutes: missing.reduce((sum, { minutes }) => sum + minutes, 0),
    };
}

/**
 * What a supporting document's row says and offers (board V2-N037-Docs): ready, its version with «Ver» and «Nueva
 * versión»; missing, what it is with «Crear con preguntas» (or «Continuar» once started; the first one missing leads) and
 * «Ya lo tengo». Who only reads NOM-037 gets no way to write or upload.
 */
export function supportingRow(
    document: SupportingDocument,
    primary: boolean,
    canManage: boolean,
): {
    detail: string;
    meta: string | null;
    view: string | null;
    write: { label: string; variant: 'default' | 'outline' | 'ghost' } | null;
    upload: boolean;
} {
    const version = document.ready ? document.version : null;

    if (version !== null) {
        return {
            detail: `Listo · versión ${version.number} del ${version.publishedOn}`,
            meta: null,
            view: version.id,
            write: canManage
                ? { label: 'Nueva versión', variant: 'ghost' }
                : null,
            upload: false,
        };
    }

    return {
        detail: document.summary,
        meta: `≈ ${document.minutes} min · numeral ${document.numeral}`,
        view: null,
        write: canManage
            ? {
                  label: document.started ? 'Continuar' : 'Crear con preguntas',
                  variant: primary ? 'default' : 'outline',
              }
            : null,
        upload: canManage,
    };
}
