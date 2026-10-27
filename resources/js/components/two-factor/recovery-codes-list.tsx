import type { Ref } from 'react';

type Props = {
    recoveryCodesList: string[];
    ref: Ref<HTMLDivElement>;
};

export default function RecoveryCodesList({ recoveryCodesList, ref }: Props) {
    return (
        <>
            <div
                ref={ref}
                className="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm"
                role="list"
                aria-label="Recovery codes"
            >
                {recoveryCodesList.length ? (
                    recoveryCodesList.map((code, index) => (
                        <div
                            key={index}
                            role="listitem"
                            className="select-text"
                        >
                            {code}
                        </div>
                    ))
                ) : (
                    <div
                        className="space-y-2"
                        aria-label="Loading recovery codes"
                    >
                        {Array.from({ length: 8 }, (_, index) => (
                            <div
                                key={index}
                                className="h-4 animate-pulse rounded bg-muted-foreground/20"
                                aria-hidden="true"
                            />
                        ))}
                    </div>
                )}
            </div>

            <div className="text-xs text-muted-foreground select-none">
                <p id="regenerate-warning">
                    Each recovery code can be used once to access your account
                    and will be removed after use. If you need more, click{' '}
                    <span className="font-bold">Regenerate codes</span> above.
                </p>
            </div>
        </>
    );
}
