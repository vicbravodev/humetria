import { describe, expect, it } from 'vite-plus/test';
import { mobileTabs } from '@/lib/mobile-tabs';
import { can } from '../support/tenant-can';

type Role = 'owner' | 'hr' | 'viewer' | 'committee' | 'complaints';

const acme = (role: Role, allowed = true, type?: 'union') => ({
    slug: 'acme',
    role,
    can: can(allowed, allowed),
    ...(type === undefined ? {} : { type }),
});

const shape = (tabs: ReturnType<typeof mobileTabs>) =>
    tabs.map((tab) =>
        tab.key === 'compliance'
            ? [tab.title, tab.items.map((item) => item.title)]
            : tab.title,
    );

describe('mobileTabs', () => {
    it('folds the menu of whoever runs the company into Panel, Cumplimiento, Quejas and Más (board 40)', () => {
        expect(shape(mobileTabs(acme('owner'), false))).toEqual([
            'Panel',
            ['Cumplimiento', ['NOM-035', 'NOM-037', 'Ley Silla']],
            'Quejas',
            'Más',
        ]);
    });

    it('leaves Quejas out for whoever neither handles complaints nor manages the channel', () => {
        expect(shape(mobileTabs(acme('viewer', false), false))).toEqual([
            'Panel',
            ['Cumplimiento', ['NOM-035', 'NOM-037', 'Ley Silla']],
            'Más',
        ]);
    });

    it('gives the Comisión its two standards and the outside handler its cases, as their menus do', () => {
        expect(shape(mobileTabs(acme('committee', false), false))).toEqual([
            ['Cumplimiento', ['NOM-037', 'Ley Silla']],
            'Más',
        ]);
        expect(shape(mobileTabs(acme('complaints'), false))).toEqual([
            'Quejas',
            'Más',
        ]);
    });

    it('keeps a union and someone outside any company to Panel and Más', () => {
        expect(shape(mobileTabs(acme('owner', true, 'union'), false))).toEqual([
            'Panel',
            'Más',
        ]);
        expect(shape(mobileTabs(null, false))).toEqual(['Panel', 'Más']);
        expect(shape(mobileTabs(null, true))).toEqual(['Más']);
    });
});
