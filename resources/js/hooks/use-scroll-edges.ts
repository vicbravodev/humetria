import { useEffect, useRef } from 'react';

/**
 * Marks a scrolling element with `data-more-above` and `data-more-below` while there is more to scroll that way, so the
 * `scroll-fade` utility fades only those edges. Follows its scroll and any change of size of it or its content.
 */
export function useScrollEdges<T extends HTMLElement>() {
    const ref = useRef<T>(null);

    useEffect(() => {
        const element = ref.current;

        if (element === null) {
            return;
        }

        const mark = () => {
            const below =
                element.scrollHeight - element.clientHeight - element.scrollTop;
            element.toggleAttribute('data-more-above', element.scrollTop > 1);
            element.toggleAttribute('data-more-below', below > 1);
        };
        const observer = new ResizeObserver(mark);
        observer.observe(element);

        for (const child of Array.from(element.children)) {
            observer.observe(child);
        }

        element.addEventListener('scroll', mark, { passive: true });
        mark();

        return () => {
            observer.disconnect();
            element.removeEventListener('scroll', mark);
        };
    }, []);

    return ref;
}
