import { describe, expect, it } from 'vitest';
import {
    answersText,
    channelLabel,
    hiddenAnswersText,
    tallyText,
} from '@/lib/channel-reach';
import type { ChannelTally } from '@/types';

const tally = (over: Partial<ChannelTally>): ChannelTally => ({
    channel: 'whatsapp',
    total: 12,
    delivered: 9,
    undelivered: 3,
    undeliveredBelowMinimum: false,
    ...over,
});

describe('channelLabel', () => {
    it('names the two channels', () => {
        expect(channelLabel('mail')).toBe('Correo');
        expect(channelLabel('whatsapp')).toBe('WhatsApp');
    });
});

describe('tallyText', () => {
    it('shows everything when both numbers reach the minimum', () => {
        expect(
            tallyText(tally({ total: 20, delivered: 14, undelivered: 6 }), 5),
        ).toBe('20 mensajes · 14 entregados · 6 sin entregar');
    });

    it('says "menos de" instead of the undelivered when they are too few', () => {
        expect(
            tallyText(
                tally({
                    total: 20,
                    delivered: null,
                    undelivered: null,
                    undeliveredBelowMinimum: true,
                }),
                5,
            ),
        ).toBe('20 mensajes · menos de 5 sin entregar');
    });

    it('shows no number under the minimum of messages', () => {
        expect(
            tallyText(
                tally({ total: null, delivered: null, undelivered: null }),
                5,
            ),
        ).toBe('Menos de 5 mensajes');
    });

    it('uses the minimum it is given', () => {
        expect(
            tallyText(
                tally({ total: null, delivered: null, undelivered: null }),
                8,
            ),
        ).toBe('Menos de 8 mensajes');
    });
});

describe('answersText', () => {
    it('counts who answered and who did not', () => {
        expect(
            answersText({ channel: 'mail', answered: 12, unanswered: 8 }),
        ).toBe('12 contestaron · 8 no');
    });
});

describe('hiddenAnswersText', () => {
    it('says when the table comes back', () => {
        expect(hiddenAnswersText(5)).toBe(
            'Se muestra cuando cada grupo tiene al menos 5 personas',
        );
    });
});
