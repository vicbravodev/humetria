import { describe, expect, it } from 'vitest';
import { publishCopy } from '@/lib/telework/writing';

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
