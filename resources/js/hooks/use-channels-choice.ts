import { useState } from 'react';
import { initialChoice } from '@/lib/channels';
import type { ChannelChoice } from '@/lib/channels';
import type { CompanyChannelsPage } from '@/types';

/** What the owner has chosen in the form of the channels, and how to change one thing of it. */
export function useChannelsChoice(page: CompanyChannelsPage) {
    const [choice, setChoice] = useState<ChannelChoice>(() =>
        initialChoice(page),
    );

    return {
        choice,
        change: (part: Partial<ChannelChoice>) =>
            setChoice({ ...choice, ...part }),
    };
}
