import { beforeEach, describe, expect, it } from 'vitest';
import { maskAttribute, maskText, rememberData } from '@/lib/recording-mask';

/** An Inertia page of a company's panel with its data. */
function panel(component: string, props: object = {}) {
    return {
        component,
        props: {
            auth: { user: { id: 7, name: 'Ana López' } },
            tenant: { slug: 'acme', name: 'Acme' },
            impersonator: null,
            ...props,
        },
    };
}

/** A text node's parent, as rrweb hands it: only `closest` is read. */
function inside(...selectors: string[]) {
    return {
        closest: (selector: string) =>
            selectors.some((found) => selector.split(', ').includes(found))
                ? {}
                : null,
    };
}

describe('maskAttribute', () => {
    it.each([
        ['href', '/admin/acme/employees/42'],
        ['action', '/admin/acme/org-chart'],
        ['aria-label', 'Asignar jefe a Ana López'],
        ['title', 'Ana López'],
        ['alt', 'Foto de Ana López'],
        ['placeholder', 'ana@acme.mx'],
        ['value', 'Ana López'],
        ['data-name', 'Ana López'],
        ['src', 'https://cdn.example.com/avatar.png'],
    ])('masks %s', (name, value) => {
        expect(maskAttribute(name, value)).toBe('*');
    });

    it.each([
        ['class', 'flex gap-2'],
        ['style', 'width: 40px'],
        ['role', 'button'],
        ['type', 'button'],
        ['data-state', 'open'],
        ['aria-expanded', 'true'],
        ['viewBox', '0 0 24 24'],
        ['d', 'M3 3h18'],
        ['href', '/build/assets/app-1a2b.css'],
        ['src', '/images/tools/nom-035-01-campaigns-light.webp'],
        ['src', '/brand/logo-dark.svg'],
        // rrweb's own: the page's CSS inlined (without it the player paints the page unstyled), scroll, sizes, media.
        ['_cssText', '.flex{display:flex}'],
        ['rr_scrollTop', '120'],
        ['rr_width', '320px'],
    ])('keeps %s, which says how it looks and not who', (name, value) => {
        expect(maskAttribute(name, value)).toBe(value);
    });
});

describe('maskText', () => {
    beforeEach(() =>
        rememberData(
            panel('organization/employees', {
                employees: {
                    data: [
                        {
                            name: 'Ana López',
                            email: 'ana@acme.mx',
                            note: 'Turno nocturno',
                        },
                    ],
                },
            }),
        ),
    );

    it.each([
        'Panel',
        'Empleados',
        'Cumple la norma',
        'Asignar jefe',
        'Guardar cambios',
        '4 directos',
        'Jefe actualizado.',
    ])('shows %s: the interface is the same for every company', (text) => {
        expect(maskText(text, inside())).toBe(text);
    });

    it.each([
        ['a name from the page data', 'Ana López'],
        ['the name inside a sentence', 'Asignar jefe a Ana López'],
        ['the company of the session', 'Acme'],
        ['the signed-in person', 'Ana López'],
        ['a note from the data', 'Turno nocturno'],
        ['an email', 'escribe a rh@empresa.mx'],
        ['a phone', '55 1234 5678'],
        ['an amount', '$12,500.00'],
        ['a CURP', 'LOAA800101MDFPNN09'],
        ['an RFC', 'LOAA800101AB1'],
    ])('hides %s', (_name, text) => {
        expect(maskText(text, inside())).toBe(text.replace(/\S/g, '*'));
    });

    it.each(['[role=option]', '[role=combobox]', '.ph-mask'])(
        'hides whatever is inside %s, data fetched apart or marked',
        (selector) => {
            expect(maskText('Paola Gómez', inside(selector))).toBe(
                '***** *****',
            );
        },
    );

    it('forgets the data of the page before when the next one comes', () => {
        rememberData(panel('organization/org-chart', { people: [] }));

        expect(maskText('Turno nocturno', inside())).toBe('Turno nocturno');
    });
});
