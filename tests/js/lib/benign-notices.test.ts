import { describe, expect, it, vi } from 'vitest';
import { ignoreBenignNotices } from '@/lib/benign-notices';

function errorWith(message: string) {
    return Object.assign(new Event('error', { cancelable: true }), {
        message,
    });
}

describe('ignoreBenignNotices', () => {
    it.each([
        'ResizeObserver loop completed with undelivered notifications.',
        'ResizeObserver loop limit exceeded',
    ])(
        'drops «%s»: the browser says a size changed twice in a frame, and nothing broke',
        (message) => {
            const target = new EventTarget();
            const later = vi.fn();
            ignoreBenignNotices(target);
            target.addEventListener('error', later);

            const event = errorWith(message);
            target.dispatchEvent(event);

            expect(event.defaultPrevented).toBe(true);
            expect(later).not.toHaveBeenCalled();
        },
    );

    it('lets every other error through, to whoever reports it', () => {
        const target = new EventTarget();
        const later = vi.fn();
        ignoreBenignNotices(target);
        target.addEventListener('error', later);

        const event = errorWith('Cannot read properties of undefined');
        target.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(later).toHaveBeenCalledOnce();
    });

    it('lets through an error with no message, such as a failed resource', () => {
        const target = new EventTarget();
        const later = vi.fn();
        ignoreBenignNotices(target);
        target.addEventListener('error', later);

        target.dispatchEvent(new Event('error', { cancelable: true }));

        expect(later).toHaveBeenCalledOnce();
    });
});
