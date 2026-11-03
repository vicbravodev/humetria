import HumiMarkdownBlock from '@/components/assistant/humi-markdown-block';
import { parseMarkdown } from '@/lib/assistant/markdown';

/** What Humi wrote, as paragraphs, lists and marks drawn from the safe reader: never HTML from the model. */
export default function HumiMarkdown({ text }: { text: string }) {
    return (
        <div className="flex flex-col gap-2 text-base leading-6 break-words">
            {parseMarkdown(text).map((block, index) => (
                <HumiMarkdownBlock key={index} block={block} />
            ))}
        </div>
    );
}
