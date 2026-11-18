<?php

declare(strict_types=1);

namespace App\Modules\Blog\Support;

use App\Modules\Blog\Data\Article;
use App\Modules\Blog\Exceptions\InvalidArticle;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads one article file and enforces the rules of the blog, all in one place: the library uses it to decide what is
 * published and tests/Unit/Blog/PublishedContentTest.php runs it over every file, so a broken article fails the CI.
 */
final class ArticleParser
{
    public const int MIN_WORDS = 500;

    private const int WORDS_PER_MINUTE = 200;

    private const string SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const string DATE_HINT = 'a date like 2026-11-17';

    public static function isSlug(string $slug): bool
    {
        return preg_match(self::SLUG, $slug) === 1 && strlen($slug) <= 80;
    }

    /** @throws InvalidArticle with every rule the file breaks */
    public static function parse(string $slug, string $source): Article
    {
        $problems = [];

        if (! self::isSlug($slug)) {
            $problems[] = 'The slug (the file name) must be lowercase words joined by hyphens.';
        }

        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $source, $parts) !== 1) {
            throw new InvalidArticle([...$problems, 'The file has no front matter between --- lines.']);
        }

        $front = self::front($parts[1]);

        if ($front === null) {
            throw new InvalidArticle([...$problems, 'The front matter is not a map of keys.']);
        }

        $body = $parts[2];
        $html = Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $words = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', strip_tags($html));

        $title = self::text($front['title'] ?? null);
        $description = self::text($front['description'] ?? null);
        $published = self::date($front['published_at'] ?? null);
        $updated = ($front['updated_at'] ?? null) === null ? $published : self::date($front['updated_at']);
        $reviewedAt = ($front['reviewed_at'] ?? null) === null ? null : self::date($front['reviewed_at']);
        $reviewedBy = self::text($front['reviewed_by'] ?? null);
        $sources = is_array($front['sources'] ?? null) ? array_values($front['sources']) : [];

        $problems = [
            ...$problems,
            ...self::titleProblems($title),
            ...(($description === null || mb_strlen($description) < 120 || mb_strlen($description) > 160) ? ['description must have 120 to 160 characters.'] : []),
            ...($published === null ? ['published_at must be '.self::DATE_HINT.'.'] : []),
            ...($updated === null ? ['updated_at must be '.self::DATE_HINT.'.'] : []),
            ...($published !== null && $updated !== null && $updated < $published ? ['updated_at cannot be before published_at.'] : []),
            ...(($front['reviewed_at'] ?? null) !== null && $reviewedAt === null ? ['reviewed_at must be '.self::DATE_HINT.' or null.'] : []),
            ...($reviewedAt !== null && $reviewedBy === null ? ['reviewed_by is required when reviewed_at is set.'] : []),
            ...self::sourceProblems($sources),
            ...self::bodyProblems($body, (int) $words),
        ];

        if ($problems !== [] || $title === null || $description === null || $published === null || $updated === null) {
            throw new InvalidArticle($problems);
        }

        return new Article(
            slug: $slug,
            title: $title,
            description: $description,
            publishedAt: $published,
            updatedAt: $updated,
            reviewedBy: $reviewedBy,
            reviewedAt: $reviewedAt,
            sources: array_values(array_filter($sources, is_string(...))),
            html: $html,
            readingMinutes: max(1, (int) ceil($words / self::WORDS_PER_MINUTE)),
        );
    }

    /** @return array<array-key, mixed>|null */
    private static function front(string $yaml): ?array
    {
        try {
            $front = Yaml::parse($yaml, Yaml::PARSE_DATETIME);
        } catch (ParseException) {
            return null;
        }

        return is_array($front) && ! array_is_list($front) ? $front : null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d') === $value ? CarbonImmutable::instance($date) : null;
    }

    /** @return list<string> */
    private static function titleProblems(?string $title): array
    {
        return match (true) {
            $title === null => ['title is required.'],
            mb_strlen($title) > 60 => ['title must have at most 60 characters.'],
            default => [],
        };
    }

    /**
     * @param  list<mixed>  $sources
     * @return list<string>
     */
    private static function sourceProblems(array $sources): array
    {
        if ($sources === []) {
            return ['sources needs at least one official source.'];
        }

        $problems = [];

        foreach ($sources as $source) {
            if (! is_string($source) || ! self::isOfficial($source)) {
                $problems[] = 'sources must be https URLs of gob.mx: '.(is_string($source) ? $source : '(not text)');
            }
        }

        return $problems;
    }

    private static function isOfficial(string $url): bool
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';

        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ($host === 'gob.mx' || str_ends_with($host, '.gob.mx'));
    }

    /** @return list<string> */
    private static function bodyProblems(string $body, int $words): array
    {
        return [
            ...(preg_match('/^# /m', $body) === 1 ? ['The body must not have a level 1 heading: the title is the h1.'] : []),
            ...(preg_match('/<\/?(?!https?:)[a-zA-Z][^>]*>/', $body) === 1 ? ['The body must not have raw HTML: write Markdown.'] : []),
            ...(preg_match('/\]\(\/[^)]*\)/', $body) === 1 ? [] : ['The body needs a link to a page of Humetría (starting with /).']),
            ...($words < self::MIN_WORDS ? ['The body needs at least '.self::MIN_WORDS.' words.'] : []),
        ];
    }
}
