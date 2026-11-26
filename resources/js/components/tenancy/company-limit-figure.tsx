import type { LimitFigure } from '@/lib/company-360';
import { cn } from '@/lib/utils';

const NUMBER = new Intl.NumberFormat('es-MX');

/** One limit of the summary (tablero Empresa 360): what it uses against what it may, in a figure and a bar; amber from 80 %. */
export default function CompanyLimitFigure({
    figure,
}: {
    figure: LimitFigure;
}) {
    const percent =
        figure.of === 0 ? 0 : Math.min(100, (figure.used / figure.of) * 100);

    return (
        <div data-limit={figure.key} className="grid gap-2 card px-6 py-5">
            <span className="text-caption font-medium tracking-[0.02em] text-muted-foreground uppercase">
                {figure.label}
            </span>
            <p className="tabular-nums">
                <span className="text-[32px] leading-10 font-semibold tracking-[-0.01em]">
                    {NUMBER.format(figure.used)}
                </span>{' '}
                <span className="text-muted-foreground">
                    de {NUMBER.format(figure.of)}
                </span>
            </p>
            <div
                role="progressbar"
                aria-label={`${figure.label} en uso`}
                aria-valuemin={0}
                aria-valuemax={figure.of}
                aria-valuenow={Math.min(figure.used, figure.of)}
                className="h-1.5 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={cn(
                        'h-full rounded-full',
                        figure.warn ? 'bg-warning-foreground' : 'bg-primary',
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}
