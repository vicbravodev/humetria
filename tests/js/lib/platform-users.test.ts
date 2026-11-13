import { describe, expect, it } from 'vite-plus/test';

import {
    actionCopy,
    platformRoleLabel,
    userActions,
} from '@/lib/platform-users';
import type { PlatformUserRow } from '@/types';

const user = (over: Partial<PlatformUserRow> = {}): PlatformUserRow => ({
    id: 1,
    name: 'Ana López',
    email: 'ana@acme.mx',
    platformRole: null,
    companies: '',
    twoFactor: false,
    verified: true,
    lastLogin: null,
    ...over,
});

describe('platformRoleLabel', () => {
    it('names each platform role, and who has none', () => {
        expect(platformRoleLabel('superadmin')).toBe('Superadmin');
        expect(platformRoleLabel('support')).toBe('Soporte');
        expect(platformRoleLabel(null)).toBe('Sin rol de plataforma');
    });
});

describe('userActions', () => {
    it('offers a superadmin only the password link', () => {
        expect(
            userActions(user({ platformRole: 'superadmin', twoFactor: true })),
        ).toEqual(['reset']);
    });

    it('offers the two-factor only where it is on', () => {
        expect(userActions(user())).toEqual(['reset', 'grant_support']);
        expect(userActions(user({ twoFactor: true }))).toEqual([
            'reset',
            'two_factor',
            'grant_support',
        ]);
    });

    it('offers to take support away from who has it', () => {
        expect(userActions(user({ platformRole: 'support' }))).toEqual([
            'reset',
            'revoke_support',
        ]);
    });
});

describe('actionCopy', () => {
    it('names the person in every confirmation', () => {
        for (const action of [
            'reset',
            'two_factor',
            'grant_support',
            'revoke_support',
        ] as const) {
            expect(actionCopy(action, user()).title).toContain('Ana López');
        }
    });

    it('marks as destructive what takes something away, and not what gives or sends', () => {
        expect(actionCopy('two_factor', user()).destructive).toBe(true);
        expect(actionCopy('revoke_support', user()).destructive).toBe(true);
        expect(actionCopy('reset', user()).destructive).toBe(false);
        expect(actionCopy('grant_support', user()).destructive).toBe(false);
    });

    it('warns before giving support to someone who belongs to client companies, and asks for the explicit yes', () => {
        const member = actionCopy(
            'grant_support',
            user({ companies: 'Acme (Owner)' }),
        );

        expect(member.description).toContain('Acme (Owner)');
        expect(member.description).toContain('todas las empresas');
        expect(member.confirm).toBe('Sí, dar soporte a esta persona');
        expect(actionCopy('grant_support', user()).confirm).toBe('Dar soporte');
    });
});
