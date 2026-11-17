import { useState } from 'react';
import { toCalendarDate } from '@/lib/calendar-date';

/** Today on this device's calendar, Y-m-d, read once when the field appears: the last day a past date can be. */
export function useToday(): string {
    const [today] = useState(() => toCalendarDate(new Date()));

    return today;
}
