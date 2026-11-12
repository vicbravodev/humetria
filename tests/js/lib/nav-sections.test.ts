import { describe, expect, it } from 'vite-plus/test';

import { navSections } from '@/lib/nav-sections';
import { can } from '../support/tenant-can';

type Role = 'owner' | 'hr' | 'viewer' | 'committee' | 'complaints';

const acme = (role: Role = 'owner', allowed = true, type?: 'union') => ({
    slug: 'acme',
    role,
    can: can(allowed, allowed),
    ...(type === undefined ? {} : { type }),
});

// The outside handler of complaints may only handle them: the server shares nothing else with it.
const outsideHandler = {
    ...acme('complaints', false),
    can: { ...can(false, false), handle_complaints: true },
};

const shape = (sections: ReturnType<typeof navSections>) =>
    sections.map((section) => [
        section.label,
        section.items.map((item) => item.title),
    ]);

describe('navSections', () => {
    it('groups the panel by the three families the landing offers, after the company pages', () => {
        expect(
            shape(
                navSections({
                    tenant: acme(),
                    isStaff: false,
                    home: 'Acme',
                }),
            ),
        ).toEqual([
            ['Acme', ['Panel', 'Centros de trabajo', 'Empleados', 'Usuarios']],
            [
                'Cumple la norma',
                ['NOM-035', 'NOM-037', 'Ley Silla', 'Asistencia'],
            ],
            ['Escucha a tu gente', ['Quejas', 'Clima', 'Pulso']],
            [
                'Ordena tu operación',
                ['Vacaciones', 'Organigrama', 'Compensación'],
            ],
        ]);
    });

    it('marks the company pages as the section without a heading', () => {
        expect(
            navSections({ tenant: acme(), isStaff: false }).map(
                (section) => section.key,
            ),
        ).toEqual(['main', 'compliance', 'listening', 'operations']);
    });

    it('leaves out a family the role has nothing to open in', () => {
        expect(
            shape(
                navSections({
                    tenant: acme('viewer', false),
                    isStaff: false,
                }),
            ),
        ).toEqual([
            ['Humetría', ['Panel', 'Centros de trabajo']],
            ['Cumple la norma', ['NOM-035', 'NOM-037', 'Ley Silla']],
        ]);
    });

    it('gives the committee its two standards, the outside handler its cases and a union Compensación', () => {
        expect(
            shape(
                navSections({
                    tenant: acme('committee', false),
                    isStaff: false,
                }),
            ),
        ).toEqual([['Cumple la norma', ['NOM-037', 'Ley Silla']]]);
        expect(
            navSections({
                tenant: outsideHandler,
                isStaff: false,
            }).map((section) => section.key),
        ).toEqual(['main']);
        expect(
            shape(
                navSections({
                    tenant: acme('owner', true, 'union'),
                    isStaff: false,
                }),
            ),
        ).toEqual([
            ['Humetría', ['Panel', 'Agremiados', 'Usuarios']],
            ['Ordena tu operación', ['Compensación']],
        ]);
    });

    it('adds the platform for the superadmin, and counts the evaluations in course on NOM-035', () => {
        const sections = navSections({
            tenant: acme(),
            isStaff: true,
            inCourse: 2,
        });

        expect(sections.at(-1)?.label).toBe('Plataforma');
        expect(
            sections.find((section) => section.key === 'compliance')?.items[0]
                ?.badge,
        ).toBe(2);
    });
});
