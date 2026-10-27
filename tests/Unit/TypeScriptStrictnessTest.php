<?php

declare(strict_types=1);

/** @return array<string, mixed> */
function tsconfigOptions(): array
{
    $tsconfig = file_get_contents(__DIR__.'/../../tsconfig.json');
    // tsconfig.json carries comments; drop whole-line and trailing ones before decoding.
    $json = preg_replace(['~^\s*//.*$~m', '~(?<=\s)/\*.*?\*/~s', '~,(\s*[\]}])~'], ['', '', '$1'], (string) $tsconfig);

    return json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR)['compilerOptions'];
}

it('keeps every strictest TypeScript flag switched on', function (string $flag, bool $expected) {
    expect(tsconfigOptions()[$flag] ?? null)->toBe($expected);
})->with([
    ['strict', true],
    ['noUncheckedIndexedAccess', true],
    ['exactOptionalPropertyTypes', true],
    ['noImplicitReturns', true],
    ['noImplicitOverride', true],
    ['noFallthroughCasesInSwitch', true],
    ['noPropertyAccessFromIndexSignature', true],
    ['noUnusedLocals', true],
    ['noUnusedParameters', true],
    ['allowUnusedLabels', false],
    ['allowUnreachableCode', false],
]);

it('has no TypeScript checker suppressions in the frontend source', function () {
    $root = realpath(__DIR__.'/../../resources/js');
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = $file->getPathname();

        if (! preg_match('~\.tsx?$~', $path) || preg_match('~/(actions|routes|wayfinder)/~', $path)) {
            continue;
        }

        if (preg_match('~@ts-(ignore|expect-error|nocheck)~', (string) file_get_contents($path))) {
            $offenders[] = substr($path, strlen($root) + 1);
        }
    }

    expect($offenders)->toBe([]);
});
