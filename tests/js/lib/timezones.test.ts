import { describe, expect, it } from 'vitest';
import { suggestTimezone } from '@/lib/timezones';
import type { TimezoneRules } from '@/types';

const rules: TimezoneRules = {
    states: { BCN: 'America/Tijuana', CHH: 'America/Chihuahua' },
    municipalities: { CHH: { juarez: 'America/Ciudad_Juarez' } },
    fallback: 'America/Mexico_City',
};

describe('suggestTimezone', () => {
    it('takes the zone of the state', () => {
        expect(suggestTimezone(rules, 'BCN', 'Mexicali')).toBe(
            'America/Tijuana',
        );
    });

    it('takes the zone of the municipality, whatever its case, accents or spaces', () => {
        expect(suggestTimezone(rules, 'CHH', '  JUÁREZ ')).toBe(
            'America/Ciudad_Juarez',
        );
        expect(suggestTimezone(rules, 'CHH', 'Chihuahua')).toBe(
            'America/Chihuahua',
        );
    });

    it('falls back for a state with no zone of its own or none chosen yet', () => {
        expect(suggestTimezone(rules, 'QUE', 'Querétaro')).toBe(
            'America/Mexico_City',
        );
        expect(suggestTimezone(rules, undefined, '')).toBe(
            'America/Mexico_City',
        );
    });
});
