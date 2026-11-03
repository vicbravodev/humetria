import { Check, LoaderCircle } from 'lucide-react';

/** One line over an answer saying what Humi looked at: with a spinner while it looks, with a check once it did. */
export default function HumiToolStep({
    label,
    done,
}: {
    label: string;
    done: boolean;
}) {
    return (
        <li className="humi-enter flex items-center gap-2 text-sm text-muted-foreground">
            {done ? (
                <Check aria-hidden className="size-4 shrink-0 text-primary" />
            ) : (
                <LoaderCircle
                    aria-hidden
                    className="size-4 shrink-0 animate-spin motion-reduce:animate-none"
                />
            )}
            <span>{label}</span>
        </li>
    );
}
