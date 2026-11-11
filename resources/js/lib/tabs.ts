/** The tab an arrow, Home or End key moves to from `index` among `count` (wrapping around), or null for any other key. */
export function nextTab(
    key: string,
    index: number,
    count: number,
): number | null {
    switch (key) {
        case 'ArrowRight':
        case 'ArrowDown':
            return (index + 1) % count;
        case 'ArrowLeft':
        case 'ArrowUp':
            return (index - 1 + count) % count;
        case 'Home':
            return 0;
        case 'End':
            return count - 1;
        default:
            return null;
    }
}
