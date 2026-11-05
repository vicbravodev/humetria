import { describe, expect, it } from 'vite-plus/test';
import { peopleEmpty } from '@/lib/evaluation';

describe('peopleEmpty', () => {
    const filters = { status: 'answered', guide: null, area: null, q: '' };

    it('says why a view has nobody when nothing was searched or filtered (C7)', () => {
        expect(peopleEmpty(filters)).toEqual({
            title: 'Nadie ha contestado todavía',
            text: 'Aquí verás a quien conteste, nunca lo que contestó.',
        });
        expect(peopleEmpty({ ...filters, status: 'pending' }).title).toBe(
            'Nadie tiene guías pendientes',
        );
        expect(peopleEmpty({ ...filters, status: 'failed' }).title).toBe(
            'Ningún envío falló',
        );
        expect(peopleEmpty({ ...filters, status: 'all' }).title).toBe(
            'Nadie está invitado',
        );
    });

    it('keeps «Nadie coincide» for a search or an area that found nobody', () => {
        const nobody = {
            title: 'Nadie coincide',
            text: 'Cambia la búsqueda o quita un filtro para ver más personas.',
        };

        expect(peopleEmpty({ ...filters, q: ' zeta ' })).toEqual(nobody);
        expect(peopleEmpty({ ...filters, guide: 'nom035.guide-i' }).title).toBe(
            'Nadie ha contestado todavía',
        );
        expect(
            peopleEmpty({
                ...filters,
                area: { value: 'a1', label: 'Calidad' },
            }),
        ).toEqual(nobody);
        expect(peopleEmpty({ ...filters, q: '   ' }).title).toBe(
            'Nadie ha contestado todavía',
        );
    });
});
