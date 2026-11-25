import { describe, expect, it } from 'vite-plus/test';
import { companyFacts, seatsText, trialExtensionCopy } from '@/lib/tenant-work';

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
