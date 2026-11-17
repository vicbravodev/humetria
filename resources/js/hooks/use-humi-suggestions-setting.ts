import { useState } from 'react';
import {
    suggestionsOn,
    whenNeverAgain,
    whenTurnedOn,
} from '@/lib/assistant/invitation-rules';
import {
    loadRemembered,
    saveRemembered,
} from '@/lib/assistant/invitation-store';

/**
 * Whether the closed bubble may suggest, for the person in the company, and how to change it: turning it on forgets
 * every «Ahora no» and «No volver a sugerir»; turning it off is «No volver a sugerir».
 */
export function useHumiSuggestionsSetting(
    storageKey: string,
): [boolean, (on: boolean) => void] {
    const [on, setOn] = useState(() =>
        suggestionsOn(loadRemembered(storageKey), Date.now()),
    );

    return [
        on,
        (next: boolean) => {
            saveRemembered(
                storageKey,
                next
                    ? whenTurnedOn()
                    : whenNeverAgain(loadRemembered(storageKey)),
            );
            setOn(next);
        },
    ];
}
