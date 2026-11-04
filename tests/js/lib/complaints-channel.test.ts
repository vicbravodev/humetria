import { describe, expect, it } from 'vitest';
import { channelBadge, channelSummary } from '@/lib/complaints/channel';

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
