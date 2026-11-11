function hasVideoFullscreen(
    element: object,
): element is { webkitEnterFullscreen: () => void } {
    return (
        'webkitEnterFullscreen' in element &&
        typeof element.webkitEnterFullscreen === 'function'
    );
}

export function canPresentVideoFullscreen(
    element: object,
    fullscreenEnabled: boolean,
): boolean {
    return fullscreenEnabled || hasVideoFullscreen(element);
}

/** Safari on iPhone exposes video fullscreen independently of the document API. */
export async function presentVideoFullscreen(
    element: object | null,
    fullscreenEnabled: boolean,
): Promise<void> {
    if (element === null) {
        return;
    }

    try {
        if (
            fullscreenEnabled &&
            'requestFullscreen' in element &&
            typeof element.requestFullscreen === 'function'
        ) {
            await element.requestFullscreen();
        } else if (hasVideoFullscreen(element)) {
            element.webkitEnterFullscreen();
        }
    } catch {
        // Browsers can reject fullscreen before metadata loads; the inline controls remain available.
    }
}
