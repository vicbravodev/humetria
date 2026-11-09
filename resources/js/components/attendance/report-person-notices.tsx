import { Badge } from '@/components/ui/badge';

/** What an inspector should notice about a person: the first two flags as chips and «+N» for the rest, in its title. */
export default function ReportPersonNotices({
    notices,
}: {
    notices: string[];
}) {
    const [first, second, ...rest] = notices;

    if (first === undefined) {
        return <span className="text-muted-foreground">Sin avisos</span>;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            <Badge variant="destructive">{first}</Badge>
            {second !== undefined && (
                <Badge variant="destructive">{second}</Badge>
            )}
            {rest.length > 0 && (
                <Badge variant="secondary" title={rest.join(' · ')}>
                    +{rest.length}
                </Badge>
            )}
        </div>
    );
}
