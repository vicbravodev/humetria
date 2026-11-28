import { describe, expect, it } from 'vite-plus/test';

import { attentionItems, type AttentionInput } from '@/lib/attention';

const calm = (): AttentionInput => ({
    overdue: { cents: 0, accounts: [] },
    trialsEnding: 0,
    demosWaiting: { new: 0, unattended: 0 },
    rejectedToday: 0,
    dormant: 0,
});

describe('attentionItems', () => {
    it('has nothing to say when nothing needs attention', () => {
        expect(attentionItems(calm())).toEqual([]);
    });

    it('puts money past due first, then what is due soon, then what can wait', () => {
        const items = attentionItems({
            overdue: {
                cents: 1_280_000,
                accounts: [
                    {
                        id: 'acc-1',
                        name: 'Textiles del Norte',
                        cents: 1_280_000,
                        days: 38,
                    },
                ],
            },
            trialsEnding: 4,
            demosWaiting: { new: 3, unattended: 1 },
            rejectedToday: 14,
            dormant: 5,
        });

        expect(items.map(({ key }) => key)).toEqual([
            'overdue:acc-1',
            'rejected',
            'trials',
            'demos',
            'dormant',
        ]);
        expect(items[0]).toMatchObject({
            tone: 'high',
            title: 'Textiles del Norte: $12,800 vencido',
            detail: '38 días de atraso',
            action: 'Cobrar',
        });
    });

    it('speaks of one in the singular', () => {
        const items = attentionItems({
            ...calm(),
            trialsEnding: 1,
            demosWaiting: { new: 1, unattended: 1 },
            rejectedToday: 1,
            dormant: 1,
        });

        expect(items.map(({ title }) => title)).toEqual([
            '1 mensaje rechazado hoy',
            '1 prueba termina en 7 días',
            '1 solicitud de demo lleva más de 48 h sin respuesta',
            '1 empresa sin entrar en 30 días',
        ]);
    });

    it('does not count new demo requests that are still within 48 hours', () => {
        expect(
            attentionItems({
                ...calm(),
                demosWaiting: { new: 3, unattended: 0 },
            }),
        ).toEqual([]);
    });

    it('says one day of delay in the singular', () => {
        const [item] = attentionItems({
            ...calm(),
            overdue: {
                cents: 50_000,
                accounts: [{ id: 'a', name: 'Taller', cents: 50_000, days: 1 }],
            },
        });

        expect(item?.detail).toBe('1 día de atraso');
    });
});
