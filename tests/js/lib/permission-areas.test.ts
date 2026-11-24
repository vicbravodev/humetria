import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';
import {
    AREAS,
    areasFor,
    handOut,
    floorOf,
    levelOf,
    withLevel,
} from '@/lib/permission-areas';
import { area, groups, toggle } from '../support/permission-areas';

/**
 * The areas, written down once for both sides: the screen draws them from here, and PermissionAreasTest (PHP) holds the same
 * file to the catalog and the templates, so neither moves alone.
 */
const fixture: unknown = JSON.parse(
    readFileSync(
        new URL('../../Fixtures/permissions/areas.json', import.meta.url),
        'utf8',
    ),
);

describe('the areas', () => {
    it('are the ones written in tests/Fixtures/permissions/areas.json, which PermissionAreasTest holds to the catalog', () => {
        expect(
            AREAS.map(({ key, section, levels, extras }) => ({
                key,
                section,
                levels: Object.fromEntries(
                    levels.map((level) => [level.key, level.permissions]),
                ),
                extras: extras.map((extra) => extra.permission),
            })),
        ).toEqual(fixture);
    });

    it('show only those the account can hand out, locked with the reason where the plan lacks the tool', () => {
        const shown = areasFor(groups);

        expect(shown.map((item) => item.area.key)).toEqual([
            'dashboard',
            'assistant',
            'nom035',
            'complaints',
            'attendance',
            'vacations',
        ]);
        expect(
            shown.find((item) => item.area.key === 'attendance')?.reason,
        ).toBe('Tu plan no incluye Asistencia.');
        expect(
            shown.find((item) => item.area.key === 'vacations')?.reason,
        ).toBeNull();
    });

    it('leave out an extra the account cannot hand out', () => {
        const nom035 = areasFor([
            {
                module: 'nom035',
                label: 'NOM-035',
                permissions: [toggle('view-nom035-route')],
            },
        ])[0];

        expect(nom035?.area.extras).toEqual([]);
    });
});

describe('the level of an area', () => {
    it('is the one whose permissions are exactly those held there', () => {
        expect(levelOf(area('vacations'), [])).toBe('none');
        expect(
            levelOf(area('vacations'), ['view-vacations', 'view-dashboard']),
        ).toBe('view');
        expect(
            levelOf(area('vacations'), ['manage-vacations', 'view-vacations']),
        ).toBe('manage');
        expect(levelOf(area('complaints'), ['handle-complaints'])).toBe(
            'handle',
        );
    });

    it('ignores the extras of the area', () => {
        expect(levelOf(area('nom035'), ['view-trauma-screenings'])).toBe(
            'none',
        );
    });

    it('is none of them for a set no level matches, never «Sin acceso»', () => {
        expect(levelOf(area('vacations'), ['manage-vacations'])).toBeNull();
        expect(levelOf(area('nom035'), ['view-nom035-route'])).toBeNull();
    });

    it('has as floor the highest level wholly held', () => {
        expect(floorOf(area('vacations'), [])).toBe(0);
        expect(floorOf(area('vacations'), ['view-vacations'])).toBe(1);
        expect(
            floorOf(area('vacations'), ['view-vacations', 'manage-vacations']),
        ).toBe(2);
        expect(floorOf(area('vacations'), ['manage-vacations'])).toBe(0);
    });
});

describe('choosing a level', () => {
    it("swaps the area's permissions for the level's and keeps the rest", () => {
        expect(
            withLevel({
                selected: ['view-dashboard', 'view-vacations'],
                area: area('vacations'),
                level: 'manage',
            }),
        ).toEqual(['view-dashboard', 'view-vacations', 'manage-vacations']);
        expect(
            withLevel({
                selected: [
                    'view-dashboard',
                    'view-vacations',
                    'manage-vacations',
                ],
                area: area('vacations'),
                level: 'none',
            }),
        ).toEqual(['view-dashboard']);
    });

    it('keeps the extras of the area and what no area knows', () => {
        expect(
            withLevel({
                selected: ['view-trauma-screenings', 'some-legacy'],
                area: area('nom035'),
                level: 'view',
            }),
        ).toEqual([
            'view-trauma-screenings',
            'some-legacy',
            'view-nom035-route',
            'view-interventions',
            'view-disclosure',
        ]);
    });
});

describe('starting from a template', () => {
    it('keeps only what the account can hand out now', () => {
        expect(
            handOut(
                [
                    'view-vacations',
                    'view-attendance',
                    'manage-members',
                    'view-dashboard',
                ],
                groups,
            ),
        ).toEqual(['view-vacations', 'view-dashboard']);
    });
});
