import type { PrivacySection } from '@/components/landing/privacy-notice-content';
import {
    ICON_BOX,
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import PrivacyNoticeIndex from '@/components/landing/privacy-notice-index';
import { cn } from '@/lib/utils';

/** The title of the page, an index of its sections and each section with its text. */
export default function PrivacyNoticeArticle({
    sections,
}: {
    sections: readonly PrivacySection[];
}) {
    return (
        <div className={cn(WRAP, SECTION)}>
            <div className="flex flex-col gap-4 pb-10 lg:pb-14">
                <h1 className={TITLE}>Aviso de privacidad</h1>
                <p className={LEAD}>
                    Qué datos personales recibe Humetría cuando visitas este
                    sitio o pides una demo, para qué los usa y cómo puedes
                    decidir sobre ellos.
                </p>
            </div>
            <div className="grid gap-10 lg:grid-cols-[minmax(0,240px)_minmax(0,1fr)] lg:gap-16">
                <PrivacyNoticeIndex sections={sections} />
                <div className="grid max-w-[68ch] gap-10">
                    {sections.map((section, index) => (
                        <section
                            key={section.id}
                            id={section.id}
                            aria-labelledby={`${section.id}-title`}
                            className="grid scroll-mt-24 gap-3 border-t pt-8 first:border-t-0 first:pt-0"
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    ICON_BOX,
                                    'size-9 text-meta font-semibold',
                                )}
                            >
                                {index + 1}
                            </span>
                            <h2
                                id={`${section.id}-title`}
                                className="text-2xl font-semibold tracking-[-0.02em] text-balance"
                            >
                                {section.title}
                            </h2>
                            {section.paragraphs.map((text) => (
                                <p
                                    key={text}
                                    className="leading-7 text-pretty text-muted-foreground"
                                >
                                    {text}
                                </p>
                            ))}
                            {section.items === undefined ? null : (
                                <ul className="grid list-disc gap-1.5 pl-5 leading-7 text-muted-foreground marker:text-primary">
                                    {section.items.map((item) => (
                                        <li key={item}>{item}</li>
                                    ))}
                                </ul>
                            )}
                            {section.closing?.map((text) => (
                                <p
                                    key={text}
                                    className="leading-7 text-pretty text-muted-foreground"
                                >
                                    {text}
                                </p>
                            ))}
                        </section>
                    ))}
                </div>
            </div>
        </div>
    );
}
