import { usePoll } from '@inertiajs/react';

/**
 * Asks for the room again every few seconds while something runs (a preparation, a session being prepared or ending):
 * when it ends, the page has the demo and the session as they were left.
 */
export default function RoomPoll() {
    usePoll(3000, {
        only: ['run', 'company', 'prepared', 'complaints', 'door', 'session'],
    });

    return null;
}
