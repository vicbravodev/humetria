import { Briefcase, Building2, ShieldCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    ICON_BOX,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import type { ToolPageContent } from '@/types';

type Card = { id?: string; icon: LucideIcon; title: string; text: string };

/** For whom: RH of a company or a consultant with several; and, when the tool collects answers, what nobody gets to see. */
export default function ToolPageAudience({ page }: { page: ToolPageContent }) {
    const cards: Card[] = [
        { icon: Building2, title: 'Para RH', text: page.audience.company },
        {
            icon: Briefcase,
            title: 'Para consultores',
            text: page.audience.consultant,
        },
        ...(page.privacy === null
            ? []
            : [
                  {
                      id: 'confidencialidad',
                      icon: ShieldCheck,
                      title: 'Confidencialidad',
                      text: page.privacy,
                  },
              ]),
    ];

    return (
        <section
            id="para-quien"
            aria-labelledby="para-quien-title"
            className={cn('scroll-mt-16 border-y bg-band', SECTION)}
        >
            <div className={cn(WRAP, 'grid gap-10')}>
                <h2 id="para-quien-title" className={TITLE}>
                    Para quién es
                </h2>
                <ul
                    className={cn(
                        'grid gap-4 md:grid-cols-2',
                        cards.length === 3 && 'lg:grid-cols-3',
                    )}
                >
                    {cards.map((card) => (
                        <li
                            key={card.title}
                            id={card.id}
                            className="grid scroll-mt-24 content-start gap-3 rounded-2xl border bg-card p-6"
                        >
                            <span
                                aria-hidden
                                className={cn(ICON_BOX, 'size-10')}
                            >
                                <card.icon className="size-5" />
                            </span>
                            <h3 className="text-lg font-semibold">
                                {card.title}
                            </h3>
                            <p className="leading-7 text-pretty text-muted-foreground">
                                {card.text}
                            </p>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
