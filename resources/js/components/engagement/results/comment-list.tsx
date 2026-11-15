import Pagination from '@/components/humetria/pagination';
import type { ManagerComment, Paginated } from '@/types';

/**
 * The original comments of a group, a few at a time, in a random order that is the same each time: only the text, with
 * nothing that says who wrote it or when.
 */
export default function CommentList({
    heading,
    comments,
}: {
    heading: string;
    comments: Paginated<ManagerComment>;
}) {
    return (
        <section
            aria-labelledby="comment-list"
            className="flex max-w-[760px] flex-col gap-4 card p-5 sm:p-6"
        >
            <h2 id="comment-list" className="text-xl font-semibold">
                {heading}
            </h2>
            <ul className="divide-y">
                {comments.data.map((comment, position) => (
                    <li
                        // The text is all there is of a comment: its place in the page tells them apart.
                        key={`${comments.current_page}-${position}`}
                        className="py-4 text-pretty first:pt-0 last:pb-0"
                    >
                        <blockquote className="border-l-2 border-primary pl-4 leading-relaxed">
                            {comment.body}
                        </blockquote>
                    </li>
                ))}
            </ul>
            <Pagination page={comments} label={heading} pinned={false} />
        </section>
    );
}
