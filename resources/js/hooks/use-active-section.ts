import { useEffect, useState } from 'react';

/** The part of a long page being read: the last one whose top went past the upper third of the window; the first until then. */
export function useActiveSection(ids: string[]): string {
    const [active, setActive] = useState(ids[0] ?? '');
    const key = ids.join(' ');

    useEffect(() => {
        const sections = key
            .split(' ')
            .map((id) => document.getElementById(id))
            .filter((section) => section !== null);
        const observer = new IntersectionObserver(
            (entries) => {
                const seen = entries.filter((entry) => entry.isIntersecting);
                const top = seen.at(-1);

                if (top !== undefined) {
                    setActive(top.target.id);
                }
            },
            { rootMargin: '-20% 0px -70% 0px' },
        );

        sections.forEach((section) => observer.observe(section));

        return () => observer.disconnect();
    }, [key]);

    return active;
}
