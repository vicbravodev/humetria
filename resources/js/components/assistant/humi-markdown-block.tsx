import { memo } from 'react';
import HumiMarkdownInlines from '@/components/assistant/humi-markdown-inlines';
import type { Block } from '@/lib/assistant/markdown';

/** A paragraph or a list of Humi's text. A block that did not change is not drawn again while the answer streams in. */
export default memo(function HumiMarkdownBlock({ block }: { block: Block }) {
    if (block.kind === 'paragraph') {
        return (
            <p className="humi-enter">
                <HumiMarkdownInlines nodes={block.inline} />
            </p>
        );
    }

    const List = block.ordered ? 'ol' : 'ul';

    return (
        <List
            className={
                block.ordered
                    ? 'humi-enter list-decimal pl-5'
                    : 'humi-enter list-disc pl-5'
            }
        >
            {block.items.map((item, index) => (
                <li key={index}>
                    <HumiMarkdownInlines nodes={item} />
                </li>
            ))}
        </List>
    );
});
