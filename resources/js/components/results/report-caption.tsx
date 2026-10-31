import type { ReactNode } from 'react';

/** The small capitals that name a figure or a fact of the report (style caption). */
export default function ReportCaption({ children }: { children: ReactNode }) {
    return (
        <span className="text-xs leading-4 font-medium tracking-[0.06em] text-muted-foreground uppercase">
            {children}
        </span>
    );
}
