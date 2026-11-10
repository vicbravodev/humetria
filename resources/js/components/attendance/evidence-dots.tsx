import { cn } from '@/lib/utils';

/** Three dots, as many filled as the mark proves (board P2, «Registrada»); the words go next to it. */
export default function EvidenceDots({ dots }: { dots: number }) {
    return (
        <span aria-hidden className="inline-flex gap-1">
            {[1, 2, 3].map((dot) => (
                <i
                    key={dot}
                    className={cn(
                        'size-2 rounded-full',
                        dot <= dots ? 'bg-primary' : 'bg-track',
                    )}
                />
            ))}
        </span>
    );
}
