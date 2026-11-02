import { describe, expect, it } from 'vite-plus/test';
import { contextLabel, pageContextOf } from '@/lib/assistant/page-context';

const ID = '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c';

describe('pageContextOf', () => {
    it('is the campaign of its page and of the pages that hang from it', () => {
        expect(pageContextOf(`/admin/acme/campaigns/${ID}`)).toEqual({
            kind: 'campaign',
            id: ID,
        });
        expect(pageContextOf(`/admin/acme/campaigns/${ID}/results`)).toEqual({
            kind: 'campaign',
            id: ID,
        });
        expect(pageContextOf(`/admin/acme/campaigns/${ID}/report`)).toEqual({
            kind: 'campaign',
            id: ID,
        });
    });

    it('is the work center of its NOM-037 page', () => {
        expect(pageContextOf(`/admin/acme/telework/centers/${ID}`)).toEqual({
            kind: 'work_center',
            id: ID,
        });
        expect(
            pageContextOf(`/admin/acme/telework/centers/${ID}/dossier`),
        ).toEqual({ kind: 'work_center', id: ID });
    });

    it('ignores the query and the hash', () => {
        expect(pageContextOf(`/admin/acme/campaigns/${ID}?tab=1#top`)).toEqual({
            kind: 'campaign',
            id: ID,
        });
    });

    it('is nothing on any other page, or when the id is not an id', () => {
        expect(pageContextOf('/admin/acme')).toBeNull();
        expect(pageContextOf('/admin/acme/campaigns')).toBeNull();
        expect(pageContextOf('/admin/acme/campaigns/nueva')).toBeNull();
        expect(pageContextOf('/admin/acme/members')).toBeNull();
        expect(pageContextOf('')).toBeNull();
    });
});

describe('contextLabel', () => {
    it('says what the page is, or that Humi sees the whole compliance', () => {
        expect(contextLabel(null)).toBe('Cumplimiento general');
        expect(contextLabel({ kind: 'campaign', id: ID })).toBe('Esta campaña');
        expect(contextLabel({ kind: 'work_center', id: ID })).toBe(
            'Este centro de trabajo',
        );
    });
});
