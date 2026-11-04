import ReportTable from '@/components/compensation/report-table';
import type { ReportCut } from '@/types';

/** One cut of the cost (by area, category or group) as a table: its slices and what each costs before and after. */
export default function CutTable({
    caption,
    label,
    cuts,
}: {
    caption: string;
    label: string;
    cuts: ReportCut[];
}) {
    return (
        <div className="grid gap-2">
            <h3 className="text-caption font-medium text-muted-foreground">
                {caption}
            </h3>
            <ReportTable
                caption={caption}
                rows={cuts}
                rowKey={(cut) => cut.label}
                columns={[
                    { label, cell: (cut) => cut.label },
                    {
                        label: 'Personas',
                        cell: (cut) => cut.people,
                        right: true,
                    },
                    {
                        label: 'Antes',
                        cell: (cut) => cut.before_text,
                        right: true,
                    },
                    {
                        label: 'Después',
                        cell: (cut) => cut.after_text,
                        right: true,
                    },
                    {
                        label: 'Cambio',
                        cell: (cut) => cut.delta_text,
                        right: true,
                    },
                ]}
            />
        </div>
    );
}
