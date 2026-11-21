import { useEffect, useState } from 'react';

/** Whether any part of the element with that id is on screen: false until it is, and on a page that has no such element. */
export function useElementInView(id: string): boolean {
    const [inView, setInView] = useState(false);

    useEffect(() => {
        const element = document.getElementById(id);

        if (element === null) {
            return undefined;
        }

        const observer = new IntersectionObserver((entries) => {
            setInView(entries.at(-1)?.isIntersecting === true);
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, [id]);

    return inView;
}
