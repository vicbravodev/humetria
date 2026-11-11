import { ChevronDown } from 'lucide-react';
import { countOf } from '@/lib/plural';
import type { ClimateSection } from '@/types';

/** "Lo que van a contestar": the questions by section, each one closed until opened, so RH can read them before launching. */
export default function QuestionnairePreview({
    sections,
}: {
    sections: ClimateSection[];
}) {
    return (
        <section
            aria-labelledby="questionnaire-title"
            className="flex flex-col gap-3"
        >
            <h2
                id="questionnaire-title"
                className="text-center text-sm text-muted-foreground"
            >
                Lo que van a contestar
            </h2>
            <div className="flex flex-col card px-5 py-1">
                {sections.map((section) => (
                    <details
                        key={section.title}
                        className="group border-t py-3 first:border-t-0"
                    >
                        <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-md font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                            <span>{section.title}</span>
                            <span className="flex items-center gap-2 text-sm font-normal whitespace-nowrap text-muted-foreground">
                                {countOf(
                                    section.items.length,
                                    'pregunta',
                                    'preguntas',
                                )}
                                <ChevronDown
                                    aria-hidden="true"
                                    className="size-4 transition-transform group-open:rotate-180"
                                />
                            </span>
                        </summary>
                        <ol className="mt-3 flex list-decimal flex-col gap-2 pl-5 text-sm text-muted-foreground">
                            {section.items.map((item) => (
                                <li key={item.text}>{item.text}</li>
                            ))}
                        </ol>
                    </details>
                ))}
            </div>
        </section>
    );
}
