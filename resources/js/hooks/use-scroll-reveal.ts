import { useEffect } from 'react';

/**
 * Reveals what scrolls into view: after hydrating, every `[data-reveal]` whose top edge sits below the fold becomes
 * `pending` (app.css hides it) and goes `done` once `data-reveal-amount` (a fifth by default) shows, which plays its
 * transition. What is already in view is never hidden, so nothing flickers; without this hook (no JavaScript, or before it
 * runs) everything is visible. Attributes are not inline styles: the CSP lets them through.
 */
export function useScrollReveal(): void {
    useEffect(() => {
        const observers: IntersectionObserver[] = [];

        document
            .querySelectorAll<HTMLElement>('[data-reveal]')
            .forEach((element) => {
                if (element.getBoundingClientRect().top < window.innerHeight) {
                    return;
                }

                const observer = new IntersectionObserver(
                    (entries) => {
                        if (entries.some((entry) => entry.isIntersecting)) {
                            element.dataset['reveal'] = 'done';
                            observer.disconnect();
                        }
                    },
                    {
                        threshold: Number(
                            element.dataset['revealAmount'] ?? 0.2,
                        ),
                    },
                );

                element.dataset['reveal'] = 'pending';
                observer.observe(element);
                observers.push(observer);
            });

        return () => observers.forEach((observer) => observer.disconnect());
    }, []);
}
