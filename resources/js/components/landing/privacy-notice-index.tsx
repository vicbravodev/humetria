import type { PrivacySection } from '@/components/landing/privacy-notice-content';

/** The sections of the notice as a list of jumps: it stays in view beside the text on a wide screen. */
export default function PrivacyNoticeIndex({
    sections,
}: {
    sections: readonly PrivacySection[];
}) {
    return (
        <nav
            aria-label="En este aviso"
            className="hidden lg:sticky lg:top-24 lg:block lg:self-start"
        >
            <p className="mb-3 text-caption font-medium tracking-wide text-muted-foreground uppercase">
                En este aviso
            </p>
            <ol className="grid gap-1 border-l">
                {sections.map(({ id, title }) => (
                    <li key={id}>
                        <a
                            href={`#${id}`}
                            className="-ml-px block border-l border-transparent py-1.5 pl-4 text-meta text-muted-foreground transition-colors hover:border-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            {title}
                        </a>
                    </li>
                ))}
            </ol>
        </nav>
    );
}
