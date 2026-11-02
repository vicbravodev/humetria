import { CalendarCheck, ListChecks, Users } from 'lucide-react';
import { describe, expect, it } from 'vite-plus/test';
import { committeeFacts } from '@/lib/telework/committee';
import type { Committee } from '@/types';

const member = {
    id: 1,
    name: 'Ana',
    email: 'ana@example.com',
    role: 'hr',
    active: true,
};

describe('committeeFacts', () => {
    it('says when the act was signed, who sits on it and that the draft is ready', () => {
        const committee: Committee = {
            constitutedOn: '2026-03-02',
            hasAct: true,
            members: [member, member],
        };

        expect(committeeFacts(committee)).toEqual([
            { icon: CalendarCheck, text: 'Acta del 2 de marzo de 2026' },
            { icon: Users, text: '2 integrantes' },
            { icon: ListChecks, text: 'Borrador de la lista listo' },
        ]);
    });

    it('leaves out the act without a valid date, and says nothing before the Comisión exists', () => {
        const committee: Committee = {
            constitutedOn: '',
            hasAct: false,
            members: [member],
        };

        expect(committeeFacts(committee).map(({ text }) => text)).toEqual([
            '1 integrante',
            'Borrador de la lista listo',
        ]);
        expect(committeeFacts(null)).toEqual([]);
    });
});
