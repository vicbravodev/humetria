/** A line that tells how something went, read out by a screen reader when it appears; an alert when it went wrong. */
export default function StatusLine({
    text,
    problem = false,
}: {
    text: string;
    problem?: boolean;
}) {
    return (
        <p
            role={problem ? 'alert' : 'status'}
            className={
                problem
                    ? 'rounded-xl bg-risk-alto-bg px-4 py-3 text-base text-risk-alto-fg'
                    : 'rounded-xl bg-accent px-4 py-3 text-base'
            }
        >
            {text}
        </p>
    );
}
