import { describe, expect, it } from 'vitest';
import { missingMark } from '@/lib/inspection-mark';

describe('missingMark', () => {
    it('is red when the requirement is missing', () => {
        expect(missingMark('missing')).toEqual({
            label: 'Falta',
            className: 'border-risk-alto',
        });
    });

    it('is neutral when nothing is registered to judge it by', () => {
        expect(missingMark('pending')).toEqual({
            label: 'Sin registros',
            className: 'border-muted-foreground',
        });
    });
});
