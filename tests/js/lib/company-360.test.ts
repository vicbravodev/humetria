import { describe, expect, it } from 'vite-plus/test';
import {
    companyHeaderFacts,
    companyTab,
    contactFacts,
    limitFigures,
    moduleNote,
    moduleSummary,
    overdueBadge,
    overdueLine,
    returnTo,
} from '@/lib/company-360';
import type { CompanyDetail, CompanyPayer, ModuleRow } from '@/types';

const company: CompanyDetail = {
    id: 'c1',
    legal_name: 'Textiles del Norte SA de CV',
    trade_name: 'Textiles del Norte',
    rfc: 'TNO090312AB1',
    slug: 'textiles',
    type: 'company',
    status: 'active',
    status_label: 'Activa',
    restriction_reason: null,
    owner: { name: 'Elena Ruiz', email: 'elena@textilesnorte.mx' },
    account_manager: { id: 1, name: 'Victor' },
    created_on: '14 mar 2026',
    city: 'Monterrey',
    logo_url: null,
    last_access: '24 nov 2026',
};

const payer: CompanyPayer = {
    account_id: 'a1',
    legal_name: 'Textiles del Norte SA de CV',
    kind_label: 'Directa',
    overdue_cents: 1_280_000,
    days_past_due: 38,
};

function module(overrides: Partial<ModuleRow>): ModuleRow {
    return {
        key: 'nom035',
        label: 'NOM-035',
        enabled: true,
        source: 'plan',
        source_label: 'Plan',
        reason: null,
        ends_on: null,
        exists_in_code: true,
        ...overrides,
    };
}

describe('companyTab', () => {
    it('reads the tab from the address, and falls back to the summary', () => {
        expect(companyTab('/admin/companies/c1?tab=miembros')).toBe('miembros');
        expect(companyTab('/admin/companies/c1?members_page=2&tab=humi')).toBe(
            'humi',
        );
        expect(companyTab('/admin/companies/c1')).toBe('resumen');
        expect(companyTab('/admin/companies/c1?tab=otra')).toBe('resumen');
    });
});

describe('returnTo', () => {
    it('sends a change made from the modules table back to its tab, and one made from the summary back to it', () => {
        expect(returnTo(undefined)).toEqual({ query: { tab: 'modulos' } });
        expect(returnTo(() => null)).toBeUndefined();
    });
});

describe('companyHeaderFacts', () => {
    it('lists legal name, RFC, city, registration and who answers for it, as the facts of PageHeader', () => {
        expect(companyHeaderFacts(company).map(({ text }) => text)).toEqual([
            'Textiles del Norte SA de CV',
            'TNO090312AB1',
            'Monterrey',
            'Alta: 14 mar 2026',
            'Responsable: Victor',
        ]);
    });

    it('leaves out the city it does not have and says nobody answers for it yet', () => {
        expect(
            companyHeaderFacts({
                ...company,
                city: null,
                account_manager: null,
            }).map(({ text }) => text),
        ).toEqual([
            'Textiles del Norte SA de CV',
            'TNO090312AB1',
            'Alta: 14 mar 2026',
            'Responsable: sin asignar',
        ]);
    });
});

describe('overdueBadge and overdueLine', () => {
    it('say what is past due and for how long', () => {
        expect(overdueBadge(payer)).toBe('Saldo vencido · 38 d');
        expect(overdueLine(payer)).toBe(
            'Saldo vencido de $12,800 desde hace 38 días.',
        );
    });

    it('say it is the account that owes when a consultant pays several companies', () => {
        expect(
            overdueLine({
                ...payer,
                kind_label: 'Consultor',
                legal_name: 'Consultores Delta',
                days_past_due: 1,
            }),
        ).toBe(
            'La cuenta de Consultores Delta, que paga esta empresa, tiene $12,800 vencidos desde hace 1 día.',
        );
    });

    it('say nothing when nothing is past due or nobody pays', () => {
        expect(overdueBadge({ ...payer, overdue_cents: 0 })).toBeNull();
        expect(overdueLine({ ...payer, overdue_cents: 0 })).toBeNull();
        expect(overdueBadge(null)).toBeNull();
        expect(overdueLine(null)).toBeNull();
    });
});

describe('moduleSummary', () => {
    it('counts the active ones, what the plan includes and what was added by hand', () => {
        expect(
            moduleSummary([
                module({}),
                module({ key: 'pulse' }),
                module({ key: 'seating', source: 'grant' }),
                module({ key: 'climate', enabled: false, source: null }),
            ]),
        ).toBe('3 de 4 activos · el plan incluye 2, 1 agregado a mano');
    });

    it('says only the count without a plan or grants', () => {
        expect(
            moduleSummary([
                module({ source: 'legacy' }),
                module({ key: 'pulse', enabled: false, source: null }),
            ]),
        ).toBe('1 de 2 activos');
    });
});

describe('moduleNote', () => {
    it('says where each module comes from', () => {
        expect(moduleNote(module({}))).toBe('Incluido en el plan');
        expect(
            moduleNote(module({ source: 'grant', ends_on: '31 dic 2026' })),
        ).toBe('Agregado a mano · vence 31 dic 2026');
        expect(moduleNote(module({ source: 'grant' }))).toBe('Agregado a mano');
        expect(
            moduleNote(
                module({ source: 'revoke', enabled: false, reason: 'x' }),
            ),
        ).toBe('Quitado a mano');
        expect(
            moduleNote(module({ source: 'legacy', source_label: 'Antes' })),
        ).toBe('Antes');
        expect(moduleNote(module({ enabled: false, source: null }))).toBe(
            'No contratado',
        );
        expect(moduleNote(module({ exists_in_code: false }))).toBe(
            'Aún no está disponible',
        );
    });
});

describe('contactFacts', () => {
    it('lists the owner, the paying account, the renewal and the last access', () => {
        expect(contactFacts(company, '1 dic 2026', payer)).toEqual([
            ['Dueño', 'Elena Ruiz'],
            ['Correo', 'elena@textilesnorte.mx'],
            ['Cuenta que paga', 'Directa'],
            ['Próxima renovación', '1 dic 2026'],
            ['Último acceso', '24 nov 2026'],
        ]);
    });

    it('fills what is missing with words', () => {
        expect(
            contactFacts(
                { ...company, owner: null, last_access: null },
                null,
                null,
            ),
        ).toEqual([
            ['Dueño', 'Sin dueño'],
            ['Correo', '—'],
            ['Cuenta que paga', 'Sin cuenta'],
            ['Próxima renovación', 'Sin plan'],
            ['Último acceso', 'Nunca'],
        ]);
    });
});

describe('limitFigures', () => {
    it('puts the seats, the centers and the WhatsApp of the month side by side, warning from 80 %', () => {
        expect(
            limitFigures(
                [
                    {
                        resource: 'seats',
                        label: 'Asientos',
                        used: 96,
                        amount: 150,
                        remaining: 54,
                        over: false,
                    },
                    {
                        resource: 'centers',
                        label: 'Centros de trabajo',
                        used: 3,
                        amount: 5,
                        remaining: 2,
                        over: false,
                    },
                ],
                [
                    {
                        meter: 'email_campaign',
                        label: 'Correo',
                        unit: 'correos',
                        used: 1,
                        quota: 10,
                        remaining: 9,
                    },
                    {
                        meter: 'whatsapp',
                        label: 'Mensajes de WhatsApp',
                        unit: 'mensajes',
                        used: 842,
                        quota: 1000,
                        remaining: 158,
                    },
                ],
            ),
        ).toEqual([
            { key: 'seats', label: 'Asientos', used: 96, of: 150, warn: false },
            {
                key: 'centers',
                label: 'Centros de trabajo',
                used: 3,
                of: 5,
                warn: false,
            },
            {
                key: 'whatsapp',
                label: 'WhatsApp del mes',
                used: 842,
                of: 1000,
                warn: true,
            },
        ]);
    });

    it('shows nothing without limits nor a WhatsApp meter', () => {
        expect(limitFigures([], [])).toEqual([]);
    });
});
