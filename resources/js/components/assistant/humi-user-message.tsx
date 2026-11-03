import type { UIMessage } from 'ai';

/** What the person wrote, in a bubble on the right. */
export default function HumiUserMessage({ message }: { message: UIMessage }) {
    const text = message.parts
        .map((part) => (part.type === 'text' ? part.text : ''))
        .join('');

    return (
        <div className="humi-enter flex justify-end">
            <p className="max-w-[85%] rounded-lg bg-accent px-3.5 py-3 text-base leading-6 break-words whitespace-pre-wrap">
                {text}
            </p>
        </div>
    );
}
