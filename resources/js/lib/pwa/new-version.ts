/** The part of `navigator.serviceWorker` the watch uses: a plain object stands in for it in the tests. */
export type WorkerHandover = {
    controller: object | null;
    addEventListener(type: 'controllerchange', listener: () => void): void;
    removeEventListener(type: 'controllerchange', listener: () => void): void;
};

/**
 * Calls `onNewVersion` when a worker takes over a page that already had one: the new worker comes with a new build and the page
 * still runs the old one. The first worker a page ever gets is not a new version. Returns how to stop watching.
 */
export function watchNewVersion(
    container: WorkerHandover | undefined,
    onNewVersion: () => void,
): () => void {
    if (container === undefined || container.controller === null) {
        return () => undefined;
    }

    container.addEventListener('controllerchange', onNewVersion);

    return () =>
        container.removeEventListener('controllerchange', onNewVersion);
}
