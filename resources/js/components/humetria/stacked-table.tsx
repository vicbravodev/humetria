import type { ComponentProps } from 'react';
import { Table } from '@/components/ui/table';
import { cn } from '@/lib/utils';

/**
 * A table that, on a phone, turns each row into a card: the cells wrap one after another instead of running off to the
 * right where the ribbon, the notices and the action cannot be seen. The header goes (the cells say what they are); give
 * the cell that names the row `max-md:w-full` so it takes the first line. From `md` up it is the usual table.
 */
export default function StackedTable({
    className,
    ...props
}: ComponentProps<typeof Table>) {
    return (
        <Table
            className={cn(
                'max-md:block max-md:[&_tbody]:block max-md:[&_td]:block max-md:[&_td]:h-auto max-md:[&_td]:min-w-0 max-md:[&_td]:p-0 max-md:[&_thead]:hidden max-md:[&_tr]:flex max-md:[&_tr]:flex-wrap max-md:[&_tr]:items-center max-md:[&_tr]:gap-x-4 max-md:[&_tr]:gap-y-2 max-md:[&_tr]:px-4 max-md:[&_tr]:py-3',
                className,
            )}
            {...props}
        />
    );
}
