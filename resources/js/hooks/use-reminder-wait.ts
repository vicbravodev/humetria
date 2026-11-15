import { usePage } from '@inertiajs/react';

/**
 * Where a reminder stands on an evaluation's page: the hours left before another can go out (null while one can) and the day
 * of the last one (null when none went out).
 */
export function useReminderWait(): {
    hours: number | null;
    lastOn: string | null;
} {
    const { reminderWait, lastResentOn } = usePage().props;

    return {
        hours: typeof reminderWait === 'number' ? reminderWait : null,
        lastOn: typeof lastResentOn === 'string' ? lastResentOn : null,
    };
}
