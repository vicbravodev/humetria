import { Link } from '@inertiajs/react';
import type { Inline } from '@/lib/assistant/markdown';

function renderInline(node: Inline, key: number) {
    switch (node.kind) {
        case 'strong':
            return (
                <strong key={key} className="font-semibold">
                    {node.text}
                </strong>
            );
        case 'em':
            return <em key={key}>{node.text}</em>;
        case 'code':
            return (
                <code
                    key={key}
                    className="rounded bg-muted px-1 py-0.5 text-[0.9em]"
                >
                    {node.text}
                </code>
            );
        case 'link':
            return (
                <Link
                    key={key}
                    href={node.href}
                    className="inline-flex items-center rounded-full bg-accent px-2.5 py-0.5 text-sm font-medium text-primary hover:underline"
                >
                    {node.text}
                </Link>
            );
        default:
            return node.text;
    }
}

/** The marks of a line of Humi's text: bold, code and links to the panel; plain words otherwise. */
export default function HumiMarkdownInlines({
    nodes,
}: {
    nodes: readonly Inline[];
}) {
    return <>{nodes.map(renderInline)}</>;
}
