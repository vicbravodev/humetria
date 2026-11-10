import type { ComponentType } from 'react';
import type { ToolKey } from '@/components/landing/landing-tools';
import VisualAttendance from '@/components/landing/visual-attendance';
import VisualCompensation from '@/components/landing/visual-compensation';
import VisualComplaints from '@/components/landing/visual-complaints';
import VisualEngagement from '@/components/landing/visual-engagement';
import VisualLeave from '@/components/landing/visual-leave';
import VisualLeySilla from '@/components/landing/visual-ley-silla';
import VisualNom035 from '@/components/landing/visual-nom-035';
import VisualNom037 from '@/components/landing/visual-nom-037';
import VisualOrgChart from '@/components/landing/visual-org-chart';

const visuals: Readonly<Record<Exclude<ToolKey, 'attendance'>, ComponentType>> =
    {
        nom_035: VisualNom035,
        nom_037: VisualNom037,
        ley_silla: VisualLeySilla,
        complaints: VisualComplaints,
        compensation: VisualCompensation,
        leave: VisualLeave,
        org_chart: VisualOrgChart,
        engagement: VisualEngagement,
    };

/** A glimpse of the tool at work, with example data. */
export default function ToolVisual({ tool }: { tool: ToolKey }) {
    // The film brings its own figure and caption.
    if (tool === 'attendance') {
        return <VisualAttendance />;
    }

    const Visual = visuals[tool];

    return (
        <figure className="w-full">
            <Visual />
            <figcaption className="mt-4 text-caption text-muted-foreground">
                Datos de ejemplo
            </figcaption>
        </figure>
    );
}
