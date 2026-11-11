import { describe, expect, it, vi } from 'vitest';
import {
    canPresentVideoFullscreen,
    presentVideoFullscreen,
} from '@/lib/video-fullscreen';

describe('video fullscreen', () => {
    it('detects standard fullscreen and the iPhone video API', () => {
        expect(canPresentVideoFullscreen({}, true)).toBe(true);
        expect(
            canPresentVideoFullscreen({ webkitEnterFullscreen() {} }, false),
        ).toBe(true);
        expect(canPresentVideoFullscreen({}, false)).toBe(false);
        expect(
            canPresentVideoFullscreen({ webkitEnterFullscreen: true }, false),
        ).toBe(false);
    });

    it('prefers standard fullscreen when it is enabled', async () => {
        const video = {
            requestFullscreen: vi.fn().mockResolvedValue(undefined),
            webkitEnterFullscreen: vi.fn(),
        };

        await presentVideoFullscreen(video, true);

        expect(video.requestFullscreen).toHaveBeenCalledOnce();
        expect(video.webkitEnterFullscreen).not.toHaveBeenCalled();
    });

    it('uses iPhone fullscreen even when document fullscreen is disabled', async () => {
        const video = {
            requestFullscreen: vi.fn(),
            webkitEnterFullscreen: vi.fn(),
        };

        await presentVideoFullscreen(video, false);

        expect(video.webkitEnterFullscreen).toHaveBeenCalledOnce();
        expect(video.requestFullscreen).not.toHaveBeenCalled();
    });

    it('handles unavailable elements and unsupported fullscreen', async () => {
        await expect(
            presentVideoFullscreen(null, false),
        ).resolves.toBeUndefined();
        await expect(
            presentVideoFullscreen({}, false),
        ).resolves.toBeUndefined();
        await expect(presentVideoFullscreen({}, true)).resolves.toBeUndefined();
    });

    it('keeps the inline player usable when fullscreen is rejected', async () => {
        await expect(
            presentVideoFullscreen(
                {
                    requestFullscreen: vi
                        .fn()
                        .mockRejectedValue(new Error('Denied')),
                },
                true,
            ),
        ).resolves.toBeUndefined();
        await expect(
            presentVideoFullscreen(
                {
                    webkitEnterFullscreen() {
                        throw new Error('Not ready');
                    },
                },
                false,
            ),
        ).resolves.toBeUndefined();
    });
});
