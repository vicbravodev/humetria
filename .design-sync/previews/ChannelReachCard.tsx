import { ChannelReachCard } from 'humetria';

export const BothChannels = () => (
    <ChannelReachCard
        reach={{
            minGroup: 5,
            channels: [
                { channel: 'mail', total: 112, delivered: 104, undelivered: 8, undeliveredBelowMinimum: false },
                { channel: 'whatsapp', total: 74, delivered: null, undelivered: null, undeliveredBelowMinimum: true },
            ],
            answeredByChannel: [
                { channel: 'mail', answered: 68, unanswered: 44 },
                { channel: 'whatsapp', answered: 59, unanswered: 15 },
            ],
        }}
    />
);

export const AnswersHidden = () => (
    <ChannelReachCard
        reach={{
            minGroup: 5,
            channels: [
                { channel: 'mail', total: 38, delivered: 31, undelivered: 7, undeliveredBelowMinimum: false },
                { channel: 'whatsapp', total: null, delivered: null, undelivered: null, undeliveredBelowMinimum: true },
            ],
            answeredByChannel: null,
        }}
    />
);

export const MailOnly = () => (
    <ChannelReachCard
        reach={{
            minGroup: 5,
            channels: [{ channel: 'mail', total: 186, delivered: 179, undelivered: 7, undeliveredBelowMinimum: false }],
            answeredByChannel: [{ channel: 'mail', answered: 141, unanswered: 45 }],
        }}
    />
);
