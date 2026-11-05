import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { taskText } from '@/lib/telework/queue';
import type { CommitteeTask as Task } from '@/types';

/** One thing that waits for the Comisión (board V2-Comision): its number, the standard, what it asks and its button. */
export default function CommitteeTask({
    task,
    number,
}: {
    task: Task;
    number: number;
}) {
    const { title, text, estimate, action } = taskText(task);

    return (
        <li className="grid grid-cols-[44px_minmax(0,1fr)] items-center gap-x-4 gap-y-3 border-t px-5 py-5 first:border-t-0 @[600px]:grid-cols-[44px_minmax(0,1fr)_auto]">
            <span
                aria-hidden
                className="inline-flex size-11 items-center justify-center rounded-full bg-accent font-semibold text-primary"
            >
                {number}
            </span>
            <div className="min-w-0">
                <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <span className="rounded-md border bg-muted px-2 font-semibold text-foreground">
                        NOM-037
                    </span>
                    {estimate}
                </p>
                <h2 className="mt-1 text-lg font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{text}</p>
            </div>
            <Button
                asChild
                variant={number === 1 ? 'default' : 'outline'}
                className="col-start-2 justify-self-start @[600px]:col-start-auto"
            >
                <Link href={task.href}>{action}</Link>
            </Button>
        </li>
    );
}
