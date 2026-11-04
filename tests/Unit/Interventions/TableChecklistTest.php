<?php

declare(strict_types=1);

use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Support\TableChecklist;

mutates(TableChecklist::class);

/**
 * The lines as [key, status] pairs.
 *
 * @param  list<array{key: string, status: string}>  $lines
 * @return list<array{string, string}>
 */
function statuses(array $lines): array
{
    return array_map(fn (array $line): array => [$line['key'], $line['status']], $lines);
}

it('asks nothing of a Programa with no level: nothing was derived to ask for', function (): void {
    expect(TableChecklist::for(null, [], []))->toBe([]);
});

it('asks for the policy to be reviewed and reinforced from Medio up', function (): void {
    expect(statuses(TableChecklist::for(RiskLevel::Medium, [], [])))->toBe([['policy_review', 'missing'], ['policy_dissemination', 'missing']]);

    expect(statuses(TableChecklist::for(RiskLevel::Medium, [ActionKind::PolicyReview], [])))->toBe([['policy_review', 'covered'], ['policy_dissemination', 'missing']])
        ->and(statuses(TableChecklist::for(RiskLevel::Medium, [ActionKind::PolicyReview, ActionKind::PolicyDissemination], [])))->toBe([['policy_review', 'covered'], ['policy_dissemination', 'covered']]);
});

it('asks for an analysis, a campaign and, at Muy alto, specific evaluations from Alto up', function (RiskLevel $level, array $kinds, array $expected): void {
    expect(statuses(TableChecklist::for($level, $kinds, [['level' => RiskLevel::High, 'written' => true]])))->toBe($expected);
})->with([
    'Alto, nothing yet' => [RiskLevel::High, [], [['policy_review', 'missing'], ['policy_dissemination', 'missing'], ['analysis', 'covered'], ['awareness_campaign', 'missing'], ['specific_assessment', 'optional']]],
    'Alto with a campaign' => [RiskLevel::High, [ActionKind::AwarenessCampaign], [['policy_review', 'missing'], ['policy_dissemination', 'missing'], ['analysis', 'covered'], ['awareness_campaign', 'covered'], ['specific_assessment', 'optional']]],
    'Alto with an evaluation, which it only may include' => [RiskLevel::High, [ActionKind::SpecificAssessment], [['policy_review', 'missing'], ['policy_dissemination', 'missing'], ['analysis', 'covered'], ['awareness_campaign', 'missing'], ['specific_assessment', 'covered']]],
    'Muy alto, nothing yet' => [RiskLevel::VeryHigh, [], [['policy_review', 'missing'], ['policy_dissemination', 'missing'], ['analysis', 'covered'], ['awareness_campaign', 'missing'], ['specific_assessment', 'missing']]],
    'Muy alto with evaluations' => [RiskLevel::VeryHigh, [ActionKind::SpecificAssessment], [['policy_review', 'missing'], ['policy_dissemination', 'missing'], ['analysis', 'covered'], ['awareness_campaign', 'missing'], ['specific_assessment', 'covered']]],
]);

it('asks for no analysis, campaign or evaluation at Medio', function (): void {
    expect(array_column(TableChecklist::for(RiskLevel::Medium, [], [['level' => RiskLevel::Medium, 'written' => false]]), 'key'))->toBe(['policy_review', 'policy_dissemination']);
});

it('covers the analysis only when every row of Alto or Muy alto has it, and says how many', function (): void {
    $rows = [['level' => RiskLevel::High, 'written' => true], ['level' => RiskLevel::VeryHigh, 'written' => false], ['level' => RiskLevel::Medium, 'written' => false], ['level' => RiskLevel::VeryHigh, 'written' => true]];

    $line = array_find(TableChecklist::for(RiskLevel::VeryHigh, [], $rows), fn (array $line): bool => $line['key'] === 'analysis');

    expect($line['status'])->toBe('missing')
        ->and($line['detail'])->toBe('2 de 3 filas');
    expect(array_find(TableChecklist::for(RiskLevel::High, [], [['level' => RiskLevel::High, 'written' => true]]), fn (array $line): bool => $line['key'] === 'analysis')['detail'])->toBe('1 de 1 fila');
});

it('has the words of the Tabla for each line', function (): void {
    $texts = array_column(TableChecklist::for(RiskLevel::VeryHigh, [], []), 'text', 'key');

    expect($texts)->toBe([
        'policy_review' => 'Revisar la política de prevención de riesgos psicosociales y los programas',
        'policy_dissemination' => 'Reforzar su aplicación y difusión',
        'analysis' => 'Análisis de cada categoría y dominio',
        'awareness_campaign' => 'Campaña de sensibilización',
        'specific_assessment' => 'Evaluaciones específicas',
    ]);
});
