import { describe, expect, it } from 'vitest';
import {
    publishCopy,
    supportingProgress,
    supportingRow,
} from '@/lib/telework/writing';

describe('publishCopy', () => {
    it('says how many people of the centers are asked to confirm the policy', () => {
        expect(
            publishCopy(true, { audience: 30, centers: 2, version: null }),
        ).toEqual({
            text: 'Al publicarla, le llega a las 30 personas de tus 2 centros para que confirmen que la leyeron.',
            action: 'Publicar para 30 personas',
        });
        expect(
            publishCopy(false, { audience: 1, centers: 1, version: null }),
        ).toEqual({
            text: 'Al publicarlo, le llega a la persona de tu centro para que confirme que lo leyó.',
            action: 'Publicar para 1 persona',
        });
    });

    it('says it stays in force when nobody has to confirm it, or nobody is there yet', () => {
        expect(
            publishCopy(false, { audience: null, centers: 2, version: null }),
        ).toEqual({
            text: 'Al publicarlo, queda vigente en tus 2 centros y se suma a tu expediente. Las versiones anteriores no se borran.',
            action: 'Publicar',
        });
        expect(
            publishCopy(true, { audience: 0, centers: 1, version: null })
                .action,
        ).toBe('Publicar');
    });
});

describe('supportingProgress', () => {
    const document = (ready: boolean, minutes: number) => ({
        kind: 'k',
        name: 'n',
        summary: 's',
        numeral: '5.3',
        minutes,
        ready,
        started: false,
        version: null,
    });

    it('counts the documents ready and adds up the minutes of the rest', () => {
        expect(
            supportingProgress([
                document(true, 3),
                document(false, 5),
                document(false, 7),
            ]),
        ).toEqual({ ready: 1, minutes: 12 });
        expect(supportingProgress([])).toEqual({ ready: 0, minutes: 0 });
    });
});

describe('supportingRow', () => {
    const missing = {
        kind: 'risk_information',
        name: 'Riesgos de trabajar desde casa',
        summary: 'Qué riesgos tiene el teletrabajo.',
        numeral: '5.3',
        minutes: 5,
        ready: false,
        started: false,
        version: null,
    };
    const ready = {
        ...missing,
        ready: true,
        version: { id: 'd1', number: 1, publishedOn: '12 oct 2026' },
    };

    it('offers to write a missing one, leading with the first, or to upload it', () => {
        expect(supportingRow(missing, true, true)).toEqual({
            detail: 'Qué riesgos tiene el teletrabajo.',
            meta: '≈ 5 min · numeral 5.3',
            view: null,
            write: { label: 'Crear con preguntas', variant: 'default' },
            upload: true,
        });
        expect(
            supportingRow({ ...missing, started: true }, false, true).write,
        ).toEqual({ label: 'Continuar', variant: 'outline' });
    });

    it('shows a ready one with its version, to read or to write anew', () => {
        expect(supportingRow(ready, false, true)).toEqual({
            detail: 'Listo · versión 1 del 12 oct 2026',
            meta: null,
            view: 'd1',
            write: { label: 'Nueva versión', variant: 'ghost' },
            upload: false,
        });
    });

    it('gives who only reads NOM-037 where it stands and nothing to change', () => {
        expect(supportingRow(missing, true, false)).toMatchObject({
            write: null,
            upload: false,
        });
        expect(supportingRow(ready, false, false)).toMatchObject({
            view: 'd1',
            write: null,
        });
    });
});
