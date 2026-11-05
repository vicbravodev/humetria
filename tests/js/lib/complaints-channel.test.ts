import { describe, expect, it } from 'vitest';
import {
    againstWhom,
    channelBadge,
    channelGaps,
    channelSince,
    channelSummary,
    designateWords,
    eligibleFor,
    gapsTitle,
} from '@/lib/complaints/channel';
import type { CoverageByKind } from '@/types';

describe('channelBadge', () => {
    it('gives each state of the channel its words and its tone', () => {
        expect(channelBadge('inactive')).toEqual({
            label: 'Sin activar',
            variant: 'secondary',
        });
        expect(channelBadge('active')).toEqual({
            label: 'Activo',
            variant: 'success',
        });
        expect(channelBadge('paused')).toEqual({
            label: 'En pausa',
            variant: 'warning',
        });
    });
});

describe('channelSummary', () => {
    it('says where the channel stands and what comes next', () => {
        expect(channelSummary('inactive')).toContain('activa el canal');
        expect(channelSummary('active')).toContain('anónima o confidencial');
        expect(channelSummary('paused')).toContain('no recibe quejas nuevas');
    });

    it('says a different thing for each state', () => {
        const words = (['inactive', 'active', 'paused'] as const).map(
            channelSummary,
        );

        expect(new Set(words).size).toBe(3);
    });
});

const row = (
    kind: string,
    names: string[],
    excludedRole: string | null = null,
): CoverageByKind => ({
    kind,
    label: kind,
    excludedRole,
    handlers: names.map((name) => ({ name, roleLabel: 'RH' })),
});

describe('channelGaps', () => {
    it('is nothing to do when every kind of complaint reaches somebody', () => {
        expect(channelGaps([row('coworker', ['Rita'])], 1)).toEqual([]);
    });

    it('asks first for somebody at all when nobody handles complaints', () => {
        expect(channelGaps([row('coworker', [])], 0)).toEqual([
            {
                kind: null,
                title: 'Nadie atiende las quejas todavía',
                text: 'Si hoy alguien presenta una queja, no tendría quién la atienda. Designa a alguien de tu equipo o de la Comisión de seguridad e higiene.',
            },
        ]);
    });

    it('names each kind nobody independent would receive, with who may not', () => {
        expect(
            channelGaps(
                [
                    row('coworker', ['Rita']),
                    row('management', [], 'owner'),
                    row('hr', [], 'hr'),
                ],
                1,
            ),
        ).toEqual([
            {
                kind: 'management',
                title: 'Nadie atiende las quejas contra la dirección o el dueño',
                text: 'Si hoy alguien se queja contra la dirección o el dueño, la queja no tendría quién la atienda sin conflicto y el formulario no la aceptaría. Designa a alguien que no tenga el rol de dueño: alguien de tu equipo o de la Comisión.',
            },
            {
                kind: 'hr',
                title: 'Nadie atiende las quejas contra recursos humanos',
                text: 'Si hoy alguien se queja contra recursos humanos, la queja no tendría quién la atienda sin conflicto y el formulario no la aceptaría. Designa a alguien que no sea de recursos humanos: alguien de tu equipo o de la Comisión.',
            },
        ]);
    });

    it('asks for somebody of the whole company when a kind has nobody and nobody is left out of it', () => {
        expect(channelGaps([row('unknown', [])], 2)[0]?.text).toBe(
            'Si hoy alguien se queja sin decir contra quién, la queja no tendría quién la atienda sin conflicto y el formulario no la aceptaría. Designa a alguien para toda la empresa: hoy solo atienden centros.',
        );
    });
});

describe('againstWhom', () => {
    it('says against whom each kind of complaint is, in a sentence', () => {
        expect(againstWhom('direct_supervisor')).toBe(
            'contra una jefa o jefe inmediato',
        );
        expect(againstWhom('coworker')).toBe(
            'contra una compañera o compañero',
        );
        expect(againstWhom('third_party')).toBe(
            'contra una persona ajena a la empresa',
        );
        expect(againstWhom('nonsense')).toBe('sin decir contra quién');
    });
});

describe('gapsTitle', () => {
    it('counts what is missing for the channel to be safe', () => {
        expect(gapsTitle(1)).toBe('Falta 1 cosa para que el canal sea seguro');
        expect(gapsTitle(3)).toBe(
            'Faltan 3 cosas para que el canal sea seguro',
        );
    });
});

describe('channelSince', () => {
    it('says since when an active channel receives complaints', () => {
        expect(channelSince('active', '2026-11-04')).toBe(
            'Recibe quejas desde el 4 nov',
        );
        expect(channelSince('active', null)).toBe('Activo');
        expect(channelSince('paused', '2026-11-04')).toBe('En pausa');
        expect(channelSince('inactive', null)).toBe('Sin activar');
    });
});

describe('eligibleFor', () => {
    const people = [
        { value: '1', label: 'Olga', role: 'owner', roleLabel: 'Dueño' },
        { value: '2', label: 'Rita', role: 'hr', roleLabel: 'RH' },
        {
            value: '3',
            label: 'Cris',
            role: 'committee',
            roleLabel: 'Comisión de seguridad e higiene',
        },
    ];

    it('splits the team and the committee, and who the conflict leaves out of each', () => {
        expect(eligibleFor(people, 'owner')).toEqual({
            team: [{ value: '2', label: 'Rita · RH' }],
            committee: [{ value: '3', label: 'Cris' }],
            excluded: ['Olga'],
        });
        expect(eligibleFor(people, null).team).toHaveLength(2);
    });
});

describe('designateWords', () => {
    const nobody = { committee: [], excluded: [] };

    it('asks who handles a kind of complaint, and who may not', () => {
        expect(
            designateWords(row('management', [], 'owner'), {
                committee: [{ value: '3', label: 'Cris' }],
                excluded: ['Olga'],
            }),
        ).toEqual({
            title: '¿Quién atiende las quejas contra la dirección o el dueño?',
            lead: 'Debe ser alguien que no tenga el rol de dueño. Solo verá los casos que le lleguen.',
            teamHint: 'Olga no puede: es contra su rol',
            committeeHint: 'Sus integrantes con cuenta',
        });
        expect(designateWords(row('hr', [], 'hr'), nobody).lead).toContain(
            'no sea de recursos humanos',
        );
    });

    it('asks who handles any complaint, when it is for none in particular', () => {
        expect(designateWords(null, nobody)).toEqual({
            title: '¿Quién atiende las quejas?',
            lead: 'Recibe las quejas que le lleguen, nunca una contra su propio rol.',
            teamHint: 'RH, quien administra la empresa o quien atiende quejas',
            committeeHint: 'Nadie de la Comisión tiene cuenta todavía',
        });
    });
});
