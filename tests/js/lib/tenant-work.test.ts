import { describe, expect, it } from 'vite-plus/test';
import {
    companyFacts,
    monthlyText,
    ownerLine,
    seatsShare,
    seatsText,
    trialExtensionCopy,
} from '@/lib/tenant-work';

describe('seatsText', () => {
    it('writes the seats in use against what the plan covers', () => {
        expect(seatsText({ used: 96, amount: 150 })).toBe('96 de 150');
    });

    it('says there is no plan when there is no limit', () => {
        expect(seatsText(null)).toBe('Sin plan');
    });
});

describe('trialExtensionCopy', () => {
    it('names how many companies the trial extends', () => {
        expect(trialExtensionCopy(1)).toBe('Extender la prueba de 1 empresa');
        expect(trialExtensionCopy(3)).toBe('Extender la prueba de 3 empresas');
    });
});

describe('companyFacts', () => {
    const company = {
        owner: { name: 'Elena Ruiz', email: 'elena@acme.mx' },
        account_manager: { id: 1, name: 'Victor' },
        created_on: '14 mar 2026',
    };

    it('lists who owns it, who answers for it, its plan and when it renews', () => {
        expect(
            companyFacts(company, {
                name: 'Crecimiento',
                since: '14 mar 2026',
                renews_on: '1 dic 2026',
            }),
        ).toEqual([
            ['Dueño', 'Elena Ruiz'],
            ['Correo', 'elena@acme.mx'],
            ['Responsable', 'Victor'],
            ['Plan', 'Crecimiento'],
            ['Renueva', '1 dic 2026'],
            ['Alta', '14 mar 2026'],
        ]);
    });

    it('says what is missing in words', () => {
        expect(
            companyFacts(
                { ...company, owner: null, account_manager: null },
                null,
            ),
        ).toEqual([
            ['Dueño', 'Sin dueño'],
            ['Correo', '—'],
            ['Responsable', 'Sin asignar'],
            ['Plan', 'Sin plan'],
            ['Renueva', '—'],
            ['Alta', '14 mar 2026'],
        ]);
    });
});

describe('seatsShare', () => {
    it('says what part of its seats a company uses, rounded', () => {
        expect(seatsShare({ used: 96, amount: 150 })).toBe('64 %');
        expect(seatsShare({ used: 1, amount: 3 })).toBe('33 %');
    });

    it('says nothing without a plan, and 0 % when the plan covers no seat', () => {
        expect(seatsShare(null)).toBe('');
        expect(seatsShare({ used: 0, amount: 0 })).toBe('0 %');
    });
});

describe('monthlyText', () => {
    it('writes what a company pays a month, or why it shows no figure', () => {
        expect(
            monthlyText({
                name: 'Integral',
                monthly_cents: 1_420_000,
                renews_on: '15 dic 2026',
            }),
        ).toBe('$14,200');
        expect(
            monthlyText({
                name: 'Integral',
                monthly_cents: null,
                renews_on: '15 dic 2026',
            }),
        ).toBe('No paga aún');
        expect(monthlyText(null)).toBe('—');
    });
});

describe('ownerLine', () => {
    it('names who owns the company under its name, after its RFC', () => {
        expect(
            ownerLine('GEJ0101011A1', {
                name: 'Ana Pérez',
                email: 'ana@acme.mx',
            }),
        ).toBe('GEJ0101011A1 · Dueño: Ana Pérez');
        expect(ownerLine('GEJ0101011A1', null)).toBe('GEJ0101011A1');
    });
});
