/**
 * The line under the figure: why it is that amount. It is plain text with a short CSS entrance (`reason-in`, app.css): the
 * caller gives each reason its own `key`, so a new reason mounts again and plays it. No Motion here: the server renders this
 * and Motion's `initial` would print a `style=""` the CSP blocks.
 */
export default function PricingReason({ children }: { children: string }) {
    return (
        <span className="reason-in text-meta text-muted-foreground motion-reduce:animate-none">
            {children}
        </span>
    );
}
