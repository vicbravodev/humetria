import { Badge } from '@/components/ui/badge';

/** What an inspector should notice about a person: the first two flags as chips and «+N» for the rest, in its title. */
export default function ReportPersonNotices({
    notices,
    inProgress,
}: {
    notices: string[];
    /** A day of theirs is still going: shown apart and neutral, since it is no warning. */
    inProgress: boolean;
}) {
    const [first, second, ...rest] = notices;

    if (first === undefined) {
        return inProgress ? (
            <Badge variant="secondary">En curso</Badge>
        ) : (
            <span className="text-muted-foreground">Sin avisos</span>
        );
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            <Badge
                variant="destructive"
                className="max-w-full whitespace-normal"
            >
                {first}
            </Badge>
            {second !== undefined && (
                <Badge
                    variant="destructive"
                    className="max-w-full whitespace-normal"
                >
                    {second}
                </Badge>
            )}
            {rest.length > 0 && (
                <Badge variant="secondary" title={rest.join(' · ')}>
                    +{rest.length}
                </Badge>
            )}
            {inProgress && <Badge variant="secondary">En curso</Badge>}
        </div>
    );
}
