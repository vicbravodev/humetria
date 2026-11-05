import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** One task of the stage the center is in (board V2-Ruta-N037): done with a check, or open with its button. */
export default function RouteTask({
    title,
    detail,
    refs,
    done,
    href,
    action,
    primary,
}: {
    title: string;
    detail: string | null;
    refs: string;
    done: boolean;
    href: string | null;
    action: string | null;
    primary: boolean;
}) {
    return (
        <li className="grid grid-cols-[24px_minmax(0,1fr)] items-center gap-x-3.5 gap-y-2.5 border-t px-[18px] py-4 first:border-t-0 @[520px]:grid-cols-[24px_minmax(0,1fr)_auto]">
            <span
                aria-hidden
                className={cn(
                    'inline-flex size-6 items-center justify-center rounded-full border-[1.5px] border-input',
                    done && 'border-primary bg-primary text-primary-foreground',
                    primary && 'border-2 border-primary bg-accent',
                )}
            >
                {done && <Check className="size-3.5" />}
            </span>
            <div className="min-w-0">
                <p className="font-medium">{title}</p>
                {detail !== null && (
                    <p className="text-sm text-muted-foreground">{detail}</p>
                )}
                <p className="text-sm text-muted-foreground">{refs}</p>
            </div>
            {href !== null && (
                <Button
                    asChild
                    size="sm"
                    variant={primary ? 'default' : 'outline'}
                    className="col-start-2 justify-self-start @[520px]:col-start-auto"
                >
                    <Link href={href}>
                        {done ? 'Ver' : (action ?? 'Continuar')}
                        <span className="sr-only">: {title}</span>
                    </Link>
                </Button>
            )}
        </li>
    );
}
