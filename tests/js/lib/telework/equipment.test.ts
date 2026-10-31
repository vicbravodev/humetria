import { describe, expect, it } from 'vite-plus/test';

import { EQUIPMENT_KINDS, equipmentTone } from '@/lib/telework/equipment';

describe('equipmentTone', () => {
    it('warns while the person has not answered, and tells received from refused', () => {
        expect(equipmentTone('pending')).toBe('warning');
        expect(equipmentTone('received')).toBe('success');
        expect(equipmentTone('refused')).toBe('destructive');
    });
});

describe('EQUIPMENT_KINDS', () => {
    it('lists the five kinds of numeral 5.7', () => {
        expect(EQUIPMENT_KINDS.map((kind) => kind.value)).toEqual([
            'chair',
            'supplies',
            'ergonomic_accessory',
            'computer',
            'other',
        ]);
    });
});
