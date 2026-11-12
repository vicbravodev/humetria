/** The event that opens the first steps panel from anywhere in the app. */
export const OPEN_FIRST_STEPS = 'first-steps:open';

export function openFirstSteps(): void {
    window.dispatchEvent(new Event(OPEN_FIRST_STEPS));
}
