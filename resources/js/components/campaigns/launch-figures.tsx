/** What launching does in two figures, before the sentence: the emails that go today and, if any, the people with none. */
export default function LaunchFigures({
    emails,
    withoutEmail,
}: {
    emails: number;
    withoutEmail: number;
}) {
    return (
        <div data-test="launch-figures" className="grid grid-cols-2 gap-3">
            <div className="rounded-xl bg-muted px-4 py-3">
                <p className="text-2xl leading-8 font-semibold tabular-nums">
                    {emails}
                </p>
                <p className="text-sm text-muted-foreground">
                    {emails === 1 ? 'correo' : 'correos'}
                </p>
            </div>
            {withoutEmail > 0 && (
                <div className="rounded-xl bg-warning px-4 py-3 text-warning-foreground">
                    <p className="text-2xl leading-8 font-semibold tabular-nums">
                        {withoutEmail}
                    </p>
                    <p className="text-sm">sin correo</p>
                </div>
            )}
        </div>
    );
}
