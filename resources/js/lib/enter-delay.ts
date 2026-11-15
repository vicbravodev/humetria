/**
 * The class that holds an entrance back by `seconds` (`enter-delay-{ms}`, in whole 10 ms: app.css lists them with `@source inline`).
 * A delay is a class and not a `style`: the CSP has no style-src-attr, and a style the server prints is a violation.
 */
export function enterDelay(seconds: number): string {
    return `enter-delay-${Math.round(seconds * 100) * 10}`;
}
