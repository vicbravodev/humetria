import { describe, expect, it } from 'vitest';
import {
    accountFacts,
    averagePaid,
    companyLine,
    nextMonth,
    paymentNote,
} from '@/lib/billing-account';
import type { AccountDetail, PaymentRow, SubscriptionView } from '@/types';

const account: AccountDetail = {
    id: 'a',
    kind: 'consultant',
    kind_label: 'Consultor',
    legal_name: 'Consultora Equilibra',
    rfc: 'CEQ180601KD4',
    contact_name: 'Ana',
    contact_email: 'cobranza@equilibra.mx',
    tax_regime: '601',
    postal_code: null,
    cfdi_use: 'G03',
    contact_phone: null,
    notes: null,
};

describe('accountFacts', () => {
    it('says who the account is for the SAT and how many companies it pays', () => {
        expect(accountFacts(account, 5)).toBe(
            'CEQ180601KD4 · Régimen 601 · Uso de CFDI G03 · cobranza@equilibra.mx · paga por 5 empresas',
        );
    });

    it('leaves out what the account does not have', () => {
        expect(
            accountFacts({ ...account, tax_regime: null, cfdi_use: null }, 1),
        ).toBe('CEQ180601KD4 · cobranza@equilibra.mx · paga por 1 empresa');
        expect(accountFacts(account, 0)).toBe(
            'CEQ180601KD4 · Régimen 601 · Uso de CFDI G03 · cobranza@equilibra.mx · aún no paga ninguna empresa',
        );
    });
});

describe('averagePaid', () => {
    it('says how many days after or before it was due the account pays', () => {
        expect(averagePaid(12)).toEqual({
            value: '12 días',
            hint: 'después de vencer',
        });
        expect(averagePaid(1)).toEqual({
            value: '1 día',
            hint: 'después de vencer',
        });
        expect(averagePaid(-3)).toEqual({
            value: '3 días',
            hint: 'antes de vencer',
        });
        expect(averagePaid(0)).toEqual({
            value: '0 días',
            hint: 'el día que vence',
        });
    });

    it('has nothing to say until a statement is settled', () => {
        expect(averagePaid(null)).toEqual({
            value: '—',
            hint: 'Sin estados pagados este año',
        });
    });
});

describe('paymentNote', () => {
    const payment: PaymentRow = {
        id: 'p',
        reference: 'SPEI-1',
        paid_on: '29 sep 2026',
        amount: '$10,800',
        applied: '$10,800',
        unapplied: '$0',
        note: null,
        recorded_by: 'Ana',
        reversed: false,
        reversal_reason: null,
        can_apply: false,
        applied_to: ['sep 2026'],
    };

    it('says the day and the months it settled', () => {
        expect(paymentNote(payment)).toBe('29 sep 2026 · aplicado a sep 2026');
        expect(
            paymentNote({ ...payment, applied_to: ['ago 2026', 'sep 2026'] }),
        ).toBe('29 sep 2026 · aplicado a ago 2026 y sep 2026');
    });

    it('says what is left to apply, and why one was reversed', () => {
        expect(
            paymentNote({
                ...payment,
                applied_to: [],
                unapplied: '$500',
                can_apply: true,
            }),
        ).toBe('29 sep 2026 · sin aplicar $500');
        expect(
            paymentNote({
                ...payment,
                reversed: true,
                reversal_reason: 'Rebotó',
            }),
        ).toBe('29 sep 2026 · revertido: Rebotó');
    });
});

describe('nextMonth', () => {
    it('names the month after the day given', () => {
        expect(nextMonth('2026-11-26')).toBe('diciembre');
        expect(nextMonth('2026-12-31')).toBe('enero');
    });
});

describe('companyLine', () => {
    const subscription = {
        plan_label: 'Cumplimiento',
        seats: 61,
        allowed_centers: 2,
    } satisfies Partial<SubscriptionView>;

    it('says the plan, its seats and its centers', () => {
        expect(companyLine({ subscription, union: false })).toBe(
            'Cumplimiento · 61 asientos · 2 centros',
        );
        expect(
            companyLine({
                subscription: { ...subscription, seats: 1, allowed_centers: 1 },
                union: false,
            }),
        ).toBe('Cumplimiento · 1 asiento · 1 centro');
    });

    it('says a union pays a flat fee, and a company with no plan has none', () => {
        expect(companyLine({ subscription, union: true })).toBe(
            'Cumplimiento · cuota fija',
        );
        expect(companyLine({ subscription: null, union: false })).toBe(
            'Sin plan asignado',
        );
    });
});
