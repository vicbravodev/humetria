import { useState } from 'react';
import BeforeCard from '@/components/complaints/before-card';
import ChannelArrivals from '@/components/complaints/channel-arrivals';
import ChannelInspection from '@/components/complaints/channel-inspection';
import ChannelProcedure from '@/components/complaints/channel-procedure';
import ChannelReach from '@/components/complaints/channel-reach';
import CoverageTable from '@/components/complaints/coverage-table';
import DesignateDialog from '@/components/complaints/designate-dialog';
import { Button } from '@/components/ui/button';
import { channelGaps, gapsTitle } from '@/lib/complaints/channel';
import type {
    ChannelBounds,
    ChannelSettings,
    ChannelState,
    ChannelSummary,
    CoverageByKind,
    HandlerCandidate,
} from '@/types';

/**
 * A channel that was opened (board V4-Quejas-Canal): first what is missing for it to be safe, then who receives each kind of
 * complaint and how it is handled, and at its side how people arrive, what came and the evidence. Designating opens its dialog.
 */
export default function ChannelOpen({
    channel,
    settings,
    bounds,
    summary,
    coverage,
    handlersCount,
    candidates,
}: {
    channel: ChannelState & { url: string; qr: string };
    settings: ChannelSettings;
    bounds: ChannelBounds;
    summary: ChannelSummary;
    coverage: CoverageByKind[];
    handlersCount: number;
    candidates: HandlerCandidate[];
}) {
    const [designating, setDesignating] = useState<{
        row: CoverageByKind | null;
    } | null>(null);
    const gaps = channelGaps(coverage, handlersCount);
    const first = gaps[0];

    return (
        <>
            {first !== undefined && (
                <BeforeCard
                    eyebrow={gapsTitle(gaps.length)}
                    title={first.title}
                    action={
                        <Button
                            onClick={() =>
                                setDesignating({
                                    row:
                                        coverage.find(
                                            (row) => row.kind === first.kind,
                                        ) ?? null,
                                })
                            }
                        >
                            Designar a alguien
                        </Button>
                    }
                >
                    {first.text}
                </BeforeCard>
            )}
            <div className="grid items-start gap-6 min-[1240px]:grid-cols-[minmax(0,1fr)_360px]">
                <div className="flex min-w-0 flex-col gap-6">
                    <CoverageTable
                        coverage={coverage}
                        handlersCount={handlersCount}
                        onDesignate={(row) => setDesignating({ row })}
                    />
                    <ChannelProcedure settings={settings} bounds={bounds} />
                </div>
                <div className="flex min-w-0 flex-col gap-6">
                    <ChannelReach channel={channel} />
                    <ChannelArrivals summary={summary} />
                    <ChannelInspection />
                </div>
            </div>
            <DesignateDialog
                key={designating?.row?.kind ?? 'any'}
                open={designating !== null}
                onOpenChange={(open) =>
                    setDesignating(open ? designating : null)
                }
                row={designating?.row ?? null}
                candidates={candidates}
            />
        </>
    );
}
