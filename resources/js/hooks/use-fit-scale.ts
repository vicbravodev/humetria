import { useLayoutEffect, useRef, useState } from 'react';

/**
 * The zoom that makes a box of `width` × `height` px fit whole inside the content box of the element the ref is put on (a letter
 * sheet seen complete on any screen). It follows the element's size; the first measure lands before the first paint.
 */
export function useFitScale<T extends HTMLElement>(
    width: number,
    height: number,
) {
    const ref = useRef<T>(null);
    const [scale, setScale] = useState(1);

    useLayoutEffect(() => {
        const element = ref.current;

        if (element === null) {
            return;
        }

        const observer = new ResizeObserver(([entry]) => {
            if (entry !== undefined) {
                setScale(
                    Math.min(
                        entry.contentRect.width / width,
                        entry.contentRect.height / height,
                    ),
                );
            }
        });
        observer.observe(element);

        return () => observer.disconnect();
    }, [width, height]);

    return { ref, scale };
}
