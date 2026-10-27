<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * Each case writes throwaway files into resources/js, runs the project's real
 * linter (`vp lint`, configured in vite.config.ts) on them and checks the verdict.
 * The files and the folders created for them are removed right after the run.
 */

const FRONTEND_ROOT = __DIR__.'/../..';

/**
 * @param  array<string, string>  $files  path relative to resources/js => contents
 * @return array{passed: bool, output: string}
 */
function lintFrontendProbe(array $files): array
{
    $paths = [];
    $createdDirectories = [];

    foreach ($files as $path => $contents) {
        $absolute = FRONTEND_ROOT.'/resources/js/'.$path;

        for ($directory = dirname($absolute); ! is_dir($directory); $directory = dirname($directory)) {
            $createdDirectories[] = $directory;
        }

        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0777, true);
        }

        file_put_contents($absolute, $contents);
        $paths[] = 'resources/js/'.$path;
    }

    $process = new Process([FRONTEND_ROOT.'/node_modules/.bin/vp', 'lint', ...$paths], FRONTEND_ROOT);
    $process->run();

    array_map(unlink(...), array_map(fn (string $path): string => FRONTEND_ROOT.'/'.$path, $paths));
    array_map(rmdir(...), array_unique($createdDirectories));

    return ['passed' => $process->isSuccessful(), 'output' => $process->getOutput().$process->getErrorOutput()];
}

function probeId(): string
{
    return 'zz-probe-'.bin2hex(random_bytes(4));
}

/** @param  int<1, max>  $count */
function linesOf(int $count, string $line): string
{
    return implode("\n", array_fill(0, $count, $line))."\n";
}

it('accepts a small component that imports shared building blocks', function (): void {
    $module = probeId();

    $result = lintFrontendProbe([
        "components/{$module}/campaign-summary.tsx" => <<<'TSX'
            import { Button } from '@/components/ui/button';
            import { cn } from '@/lib/utils';

            export default function CampaignSummary({ className }: { className?: string }) {
                return <Button className={cn('w-full', className)}>Ver reporte</Button>;
            }
            TSX,
    ]);

    expect($result['passed'])->toBeTrue($result['output']);
});

it('rejects files longer than 300 lines', function (): void {
    $result = lintFrontendProbe([
        'lib/'.probeId().'.ts' => implode("\n", array_map(fn (int $n): string => "export const value{$n} = {$n};", range(1, 301)))."\n",
    ]);

    expect($result['passed'])->toBeFalse()
        ->and($result['output'])->toContain('max-lines');
});

it('rejects functions and components longer than 100 lines', function (): void {
    $result = lintFrontendProbe([
        'lib/'.probeId().'.ts' => "export function long(): number {\n    let total = 0;\n"
            .linesOf(100, '    total += 1;')
            ."    return total;\n}\n",
    ]);

    expect($result['output'])->toContain('max-lines-per-function');
});

it('rejects cyclomatic complexity above 10', function (): void {
    $branches = implode("\n", array_map(fn (int $n): string => "    if (value === {$n}) return {$n};", range(1, 11)));

    $result = lintFrontendProbe([
        'lib/'.probeId().'.ts' => "export function pick(value: number): number {\n{$branches}\n    return 0;\n}\n",
    ]);

    expect($result['output'])->toContain('complexity');
});

it('rejects blocks nested deeper than 4', function (): void {
    $result = lintFrontendProbe([
        'lib/'.probeId().'.ts' => <<<'TS'
            export function deep(a: boolean): number {
                if (a) { if (a) { if (a) { if (a) { if (a) { return 1; } } } } }
                return 0;
            }
            TS,
    ]);

    expect($result['output'])->toContain('max-depth');
});

it('rejects functions with more than 3 parameters', function (): void {
    $result = lintFrontendProbe([
        'lib/'.probeId().'.ts' => "export function many(a: number, b: number, c: number, d: number): number {\n    return a + b + c + d;\n}\n",
    ]);

    expect($result['output'])->toContain('max-params');
});

it('rejects more than one component per file', function (): void {
    $result = lintFrontendProbe([
        'components/'.probeId().'/two-things.tsx' => <<<'TSX'
            export function First() {
                return <p>uno</p>;
            }

            export function Second() {
                return <p>dos</p>;
            }
            TSX,
    ]);

    expect($result['output'])->toContain('no-multi-comp');
});

it('rejects file names that are not kebab-case', function (): void {
    $result = lintFrontendProbe([
        'lib/'.probeId().'/CampaignHelpers.ts' => "export const helper = 1;\n",
    ]);

    expect($result['output'])->toContain('filename-case');
});

it('rejects relative imports that climb out of the folder', function (): void {
    $result = lintFrontendProbe([
        'lib/'.probeId().'/helper.ts' => "import { cn } from '../utils';\n\nexport const helper = cn;\n",
    ]);

    expect($result['output'])->toContain('no-restricted-imports');
});

it('rejects importing a page from anywhere', function (): void {
    $result = lintFrontendProbe([
        'components/'.probeId().'/uses-page.tsx' => "import Dashboard from '@/pages/dashboard';\n\nexport default Dashboard;\n",
    ]);

    expect($result['output'])->toContain('no-restricted-imports');
});

it('rejects dependency cycles', function (): void {
    $directory = 'lib/'.probeId();

    $result = lintFrontendProbe([
        "{$directory}/first.ts" => "import { second } from './second';\n\nexport const first = (): number => second() + 1;\n",
        "{$directory}/second.ts" => "import { first } from './first';\n\nexport const second = (): number => first() - 1;\n",
    ]);

    expect($result['output'])->toContain('no-cycle');
});

it('rejects a module importing another module', function (): void {
    $campaigns = probeId();
    $results = probeId();

    $result = lintFrontendProbe([
        "components/{$results}/risk-chart.tsx" => "export default function RiskChart() {\n    return <p>chart</p>;\n}\n",
        "components/{$campaigns}/campaign-card.tsx" => "import RiskChart from '@/components/{$results}/risk-chart';\n\nexport default RiskChart;\n",
    ]);

    expect($result['output'])->toContain('no-restricted-imports')
        ->toContain("components/{$campaigns}/campaign-card.tsx");
});

it('keeps design-system components free of modules and layouts', function (string $import): void {
    $result = lintFrontendProbe([
        'components/humetria/'.probeId().'.tsx' => "import Thing from '{$import}';\n\nexport default Thing;\n",
    ]);

    expect($result['output'])->toContain('no-restricted-imports');
})->with(['@/components/app-header', '@/layouts/app-layout']);

it('keeps hooks, lib and types free of components and layouts', function (string $layer): void {
    $result = lintFrontendProbe([
        "{$layer}/".probeId().'.ts' => "import { Button } from '@/components/ui/button';\n\nexport const thing = Button;\n",
    ]);

    expect($result['output'])->toContain('no-restricted-imports');
})->with(['hooks', 'lib', 'types']);
