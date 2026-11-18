import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    answersText,
    channelLabel,
    hiddenAnswersText,
    tallyText,
} from '@/lib/channel-reach';
import type { ChannelReach } from '@/types';

/**
 * How the invitations went, by channel: a gauge for each with its messages and how many did not arrive, and who answered by
 * channel. Only counts, already under the minimum of 5: nothing here says who. The reading underestimates (whoever turns off
 * read receipts never shows as read), so it compares channels; it proves nothing about a person.
 */
export default function ChannelReachCard({
    reach,
}: {
    reach: ChannelReach | null;
}) {
    if (reach === null || reach.channels.length === 0) {
        return null;
    }

    return (
        <section
            aria-label="Alcance por canal"
            className="flex flex-col gap-4 card p-6"
            data-test="channel-reach"
        >
            <h2 className="text-lg leading-7 font-semibold">
                Alcance por canal
            </h2>
            <div className="grid gap-4 md:grid-cols-2">
                {reach.channels.map((tally) => (
                    <div
                        key={tally.channel}
                        className="flex min-w-0 flex-col gap-1.5"
                    >
                        <h3 className="text-sm font-medium">
                            {channelLabel(tally.channel)}
                        </h3>
                        {tally.total !== null && tally.delivered !== null && (
                            <div
                                role="progressbar"
                                aria-label={`Mensajes entregados por ${channelLabel(tally.channel)}`}
                                aria-valuemin={0}
                                aria-valuemax={tally.total}
                                aria-valuenow={tally.delivered}
                                className="h-2 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    className="h-full rounded-full bg-primary"
                                    style={{
                                        width: `${(tally.delivered / tally.total) * 100}%`,
                                    }}
                                />
                            </div>
                        )}
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {tallyText(tally, reach.minGroup)}
                        </p>
                    </div>
                ))}
            </div>
            {reach.answeredByChannel === null ? (
                <p className="text-sm text-muted-foreground">
                    {hiddenAnswersText(reach.minGroup)}
                </p>
            ) : (
                <Table>
                    <TableCaption className="sr-only">
                        Quién contestó, por canal de la invitación
                    </TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Canal</TableHead>
                            <TableHead>Contestaron</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {reach.answeredByChannel.map((row) => (
                            <TableRow key={row.channel}>
                                <TableCell>
                                    {channelLabel(row.channel)}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {answersText(row)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </section>
    );
}
