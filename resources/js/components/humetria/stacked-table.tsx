import type { ComponentProps } from 'react';
import { Table } from '@/components/ui/table';
import { cn } from '@/lib/utils';

/**
 * A table that, on a phone, turns each row into a card: the cells wrap one after another instead of running off to the
 * right where the ribbon, the notices and the action cannot be seen. On paper it is always the table, whatever the width of the sheet. The header goes (the cells say what they are); give
 * the cell that names the row `max-md:w-full` so it takes the first line. From `md` up it is the usual table.
 */
export default function StackedTable({
    className,
    ...props
}: ComponentProps<typeof Table>) {
    return (
        <Table
            className={cn(
                'not-print:max-md:block not-print:max-md:[&_tbody]:block not-print:max-md:[&_td]:block not-print:max-md:[&_td]:h-auto not-print:max-md:[&_td]:min-w-0 not-print:max-md:[&_td]:p-0 not-print:max-md:[&_thead]:hidden not-print:max-md:[&_tr]:flex not-print:max-md:[&_tr]:flex-wrap not-print:max-md:[&_tr]:items-center not-print:max-md:[&_tr]:gap-x-4 not-print:max-md:[&_tr]:gap-y-2 not-print:max-md:[&_tr]:px-4 not-print:max-md:[&_tr]:py-3',
                className,
            )}
            {...props}
        />
    );
}
