import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const OPTIONS = [
    { label: 'Totalmente en desacuerdo', fill: 'bg-risk-muy-alto' },
    { label: 'En desacuerdo', fill: 'bg-risk-alto' },
    { label: 'Ni de acuerdo ni en desacuerdo', fill: 'bg-risk-medio' },
    { label: 'De acuerdo', fill: 'bg-risk-nulo' },
    { label: 'Totalmente de acuerdo', fill: 'bg-risk-bajo' },
];

/**
 * How the answers to one question split among the five options, as one bar from disagreement to agreement. The words
 * are in the tooltip and in the reading for screen readers: the color only confirms the position.
 */
export default function DistributionBar({
    distribution,
}: {
    distribution: number[];
}) {
    const description = OPTIONS.map(
        (option, index) => `${option.label} ${distribution[index] ?? 0} %`,
    ).join(', ');

    return (
        <div
            role="img"
            aria-label={description}
            className="flex h-3 w-full gap-0.5 overflow-hidden rounded-full"
        >
            {OPTIONS.map((option, index) => {
                const share = distribution[index] ?? 0;

                return share === 0 ? null : (
                    <Tooltip key={option.label}>
                        <TooltipTrigger asChild>
                            <span
                                className={`block h-full min-w-0.5 ${option.fill}`}
                                style={{ width: `${share}%` }}
                            />
                        </TooltipTrigger>
                        <TooltipContent>
                            {option.label}: {share} %
                        </TooltipContent>
                    </Tooltip>
                );
            })}
        </div>
    );
}
