import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import HumiMessageActions from '@/components/assistant/humi-message-actions';
import HumiSources from '@/components/assistant/humi-sources';
import HumiSuggestions from '@/components/assistant/humi-suggestions';
import { Button } from '@/components/ui/button';
import type { Rating } from '@/lib/assistant/api';
import type { AnswerLinks } from '@/lib/assistant/answer-links';

/**
 * What closes a finished answer: its sources and the button to the exact page when the tool brought them, the actions,
 * and, under the last answer, the questions to follow.
 */
export default function HumiAnswerFooter({
    text,
    links,
    followUps,
    rating,
    canRate,
    onRate,
    onRegenerate,
    onPick,
}: {
    text: string;
    links: AnswerLinks;
    /** Empty but for the last answer. */
    followUps: readonly string[];
    rating: Rating | null;
    canRate: boolean;
    onRate: (rating: Rating) => void;
    onRegenerate: (() => void) | null;
    onPick: (text: string) => void;
}) {
    return (
        <div className="humi-enter flex flex-col gap-3">
            {links.sources.length > 0 && (
                <HumiSources sources={links.sources} />
            )}
            <div className="flex flex-wrap items-center justify-between gap-2">
                {links.next !== null && (
                    <Button asChild size="sm" className="min-h-11 xl:min-h-9">
                        <Link href={links.next.link}>
                            {links.next.label}
                            <ArrowRight aria-hidden />
                        </Link>
                    </Button>
                )}
                <HumiMessageActions
                    text={text}
                    rating={rating}
                    canRate={canRate}
                    onRate={onRate}
                    onRegenerate={onRegenerate}
                />
            </div>
            {followUps.length > 0 && (
                <HumiSuggestions suggestions={followUps} onPick={onPick} />
            )}
        </div>
    );
}
