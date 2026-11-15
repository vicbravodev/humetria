/** The year on the clock now. A function, so Node (SSR) reads it per render and not once when it starts. */
export function currentYear(): number {
    return new Date().getFullYear();
}
