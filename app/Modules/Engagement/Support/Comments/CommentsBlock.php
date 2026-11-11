<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Comments;

use Closure;

/**
 * The comments as the model receives them: a block of data, one JSON array of `{n, texto}`, each text redacted and on a
 * single line without angle brackets, so it cannot close the block or open a tag. Nothing about who wrote a comment or
 * which group it came from goes in.
 */
final class CommentsBlock
{
    /**
     * @param  list<string>  $comments
     * @param  Closure(string): string  $redact
     */
    public static function build(array $comments, Closure $redact): string
    {
        $items = [];
        foreach ($comments as $n => $comment) {
            $plain = str_replace(['<', '>'], '', $redact($comment));
            $items[] = ['n' => $n, 'texto' => trim((string) preg_replace('/\s+/u', ' ', $plain))];
        }

        return "<comentarios>\n".json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n</comentarios>";
    }
}
