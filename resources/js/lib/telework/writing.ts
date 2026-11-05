import type { WritingPublication } from '@/types';

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
