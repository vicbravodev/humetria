type ObserverClass = new (callback: ResizeObserverCallback) => ResizeObserver;

/**
 * Makes every ResizeObserver hand its callback over on the next frame instead of while the browser delivers the sizes.
 * A callback that changes the size of what it observes (a floating menu fitting its room, a chart, the org chart under load)
 * makes the browser raise «ResizeObserver loop completed with undelivered notifications» like a crash, though nothing
 * broke: it would reach the error reports and fail a page that has no errors. One frame later the same change is
 * just another frame. Install it before anything creates an observer.
 */
export function deferResizeObserver(scope: {
    ResizeObserver?: ObserverClass;
    requestAnimationFrame: (callback: FrameRequestCallback) => unknown;
}): void {
    const Native = scope.ResizeObserver;

    if (Native === undefined) {
        return;
    }

    scope.ResizeObserver = class extends Native {
        private live = true;

        constructor(callback: ResizeObserverCallback) {
            super((entries, observer) => {
                scope.requestAnimationFrame(() => {
                    if (this.live) {
                        callback(entries, observer);
                    }
                });
            });
        }

        override disconnect(): void {
            this.live = false;
            super.disconnect();
        }
    };
}
