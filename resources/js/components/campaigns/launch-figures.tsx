/** What launching does in figures, before the sentence: the emails that go today, the WhatsApp messages if there are any, and the people with no way to be reached. */
export default function LaunchFigures({
    emails,
    whatsapp,
    withoutContact,
}: {
    emails: number;
    whatsapp: number;
    withoutContact: number;
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
            {whatsapp > 0 && (
                <div className="rounded-xl bg-muted px-4 py-3">
                    <p className="text-2xl leading-8 font-semibold tabular-nums">
                        {whatsapp}
                    </p>
                    <p className="text-sm text-muted-foreground">WhatsApp</p>
                </div>
            )}
            {withoutContact > 0 && (
                <div className="rounded-xl bg-warning px-4 py-3 text-warning-foreground">
                    <p className="text-2xl leading-8 font-semibold tabular-nums">
                        {withoutContact}
                    </p>
                    <p className="text-sm">sin contacto</p>
                </div>
            )}
        </div>
    );
}
