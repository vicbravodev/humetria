<?php

declare(strict_types=1);

namespace Tests\Support;

use Faker\Provider\Base;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Faker's names and companies that never contain a word a test looks for to prove it was NOT leaked.
 *
 * `->not->toContain('Rosa')` against a page that also shows a random user passes until Faker draws «Rosario». The
 * words are read from the tests themselves (every bare capitalized word given to `not->toContain` or `assertDontSee`),
 * so a new one is protected without a list to keep: the factories only ever draw names without them.
 */
final class SafeFakerNames extends Base
{
    /** @var list<string>|null */
    private static ?array $avoided = null;

    public function name(?string $gender = null): string
    {
        return $this->draw('name', $gender);
    }

    public function company(): string
    {
        return $this->draw('company');
    }

    public function userName(): string
    {
        return $this->draw('userName');
    }

    public function safeEmail(): string
    {
        return $this->draw('safeEmail');
    }

    private function draw(string $method, ?string $gender = null): string
    {
        $original = null;

        foreach ($this->generator->getProviders() as $provider) {
            if ($provider !== $this && method_exists($provider, $method)) {
                $original = $provider;

                break;
            }
        }

        if ($original === null) {
            throw new RuntimeException("No Faker provider answers [{$method}].");
        }

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $value = $method === 'name' ? $original->name($gender) : $original->{$method}();

            if (is_string($value) && ! $this->collides($value)) {
                return $value;
            }
        }

        throw new RuntimeException("Faker [{$method}] drew a word that a test looks for, 100 times in a row.");
    }

    private function collides(string $value): bool
    {
        foreach (self::avoided() as $word) {
            if (mb_stripos($value, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function avoided(): array
    {
        if (self::$avoided !== null) {
            return self::$avoided;
        }

        $words = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/js/')) {
                continue;
            }

            preg_match_all("/(?:not->toContain|assertDontSee|assertDontSeeText)\\('([A-ZÁÉÍÓÚÑ][a-záéíóúñ]{2,})'/u", (string) file_get_contents($file->getPathname()), $found);
            array_push($words, ...$found[1]);
        }

        return self::$avoided = array_values(array_unique($words));
    }
}
