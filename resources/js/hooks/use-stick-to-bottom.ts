import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type RefObject,
} from 'react';

const THRESHOLD = 48;

/**
 * Keeps a scrolling list anchored to its end while it grows, unless the person scrolled up: then it leaves them where they
 * are and says there is more below, so a button can take them back.
 */
export function useStickToBottom(
    list: RefObject<HTMLElement | null>,
    changes: unknown,
): { atEnd: boolean; scrollToEnd: () => void; onScroll: () => void } {
    const [atEnd, setAtEnd] = useState(true);
    const stuck = useRef(true);

    const scrollToEnd = useCallback(() => {
        const element = list.current;

        if (element !== null) {
            element.scrollTo({ top: element.scrollHeight, behavior: 'smooth' });
            stuck.current = true;
            setAtEnd(true);
        }
    }, [list]);

    const onScroll = useCallback(() => {
        const element = list.current;

        if (element !== null) {
            const near =
                element.scrollHeight -
                    element.scrollTop -
                    element.clientHeight <
                THRESHOLD;

            stuck.current = near;
            setAtEnd(near);
        }
    }, [list]);

    useEffect(() => {
        if (stuck.current) {
            list.current?.scrollTo({ top: list.current.scrollHeight });
        }
    }, [list, changes]);

    return { atEnd, scrollToEnd, onScroll };
}
