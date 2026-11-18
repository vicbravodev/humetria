/** The channels a person's invitation can go through. */
export type ReachChannel = 'mail' | 'whatsapp';

/**
 * The messages of one channel for a campaign or survey, already under the minimum of 5: `total` is null under it; the
 * delivered and undelivered are null while the undelivered are fewer than it (then `undeliveredBelowMinimum`).
 */
export type ChannelTally = {
    channel: ReachChannel;
    total: number | null;
    delivered: number | null;
    undelivered: number | null;
    undeliveredBelowMinimum: boolean;
};

/** Who answered, by the channel of their invitation: counts of people. */
export type ChannelAnswers = {
    channel: ReachChannel;
    answered: number;
    unanswered: number;
};

/** How the invitations went, by channel. `answeredByChannel` is null when any cell is under the minimum. */
export type ChannelReach = {
    channels: ChannelTally[];
    answeredByChannel: ChannelAnswers[] | null;
    minGroup: number;
};
