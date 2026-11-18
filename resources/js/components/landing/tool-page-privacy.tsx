import { WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';

type Props = {
    privacy: { title: string; items: { title: string; text: string }[] };
};

/** What the tool keeps private, on the inverse band: each rule with what it means, nothing promised beyond it. */
export default function ToolPagePrivacy({ privacy }: Props) {
    return (
        <section
            id="confidencialidad"
            aria-labelledby="confidencialidad-title"
            className={cn(WRAP, 'scroll-mt-16 pb-20 lg:pb-24')}
        >
            <div className="grid gap-6 rounded-[28px] bg-foreground p-7 text-background sm:p-12">
                <h2
                    id="confidencialidad-title"
                    className="max-w-[26ch] text-3xl leading-tight font-semibold tracking-[-0.025em] text-balance"
                >
                    {privacy.title}
                </h2>
                <ul className="grid gap-x-12 md:grid-cols-2">
                    {privacy.items.map((item) => (
                        <li
                            key={item.title}
                            className="grid gap-1.5 border-t border-background/20 py-4"
                        >
                            <b className="text-lg">{item.title}</b>
                            <span className="leading-relaxed text-background/80">
                                {item.text}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
