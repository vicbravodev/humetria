import { useSyncExternalStore, type RefObject } from 'react';
import {
    canPresentVideoFullscreen,
    presentVideoFullscreen,
} from '@/lib/video-fullscreen';

const subscribe = () => () => undefined;

export function useVideoFullscreen(video: RefObject<HTMLVideoElement | null>) {
    const canFullscreen = useSyncExternalStore(
        subscribe,
        () =>
            canPresentVideoFullscreen(
                HTMLVideoElement.prototype,
                document.fullscreenEnabled,
            ),
        () => false,
    );

    const fullscreen = (): void => {
        void presentVideoFullscreen(video.current, document.fullscreenEnabled);
    };

    return { canFullscreen, fullscreen };
}
