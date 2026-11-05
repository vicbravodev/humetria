<?php

declare(strict_types=1);

/*
 * A page never takes a list of records as an array (CLAUDE.md, «Listas»): an array prop is only a fixed catalog or a
 * short summary, each named here with why. A page that takes a Paginated prop is checked at runtime by
 * tests/Feature/PaginatedListsTest.php.
 */

/** "page:prop" => why it may be an array. */
const ARRAY_PROPS = [
    'organization/employees:genders' => 'catálogo fijo: el enum Gender',
    'organization/employees:staff_groups' => 'catálogo fijo: sindicalizado o confianza',
    'organization/employees:states' => 'catálogo fijo: las 32 entidades',
    'tenancy/work-centers:states' => 'catálogo fijo: las 32 entidades',
    'tenancy/members:roles' => 'catálogo fijo: los roles que se pueden dar',
    'dashboard:urgencies' => 'resumen corto: lo que no puede esperar, cinco a lo más',
    'dashboard:week' => 'resumen corto: lo que vence en los próximos 7 días, cinco a lo más',
    'results/screenings:destinations' => 'catálogo fijo: los cinco destinos de una canalización, con su guion y su aviso',
    'tenancy/nom035-route:urgencies' => 'resumen corto: quienes esperan canalización en ese centro, un renglón por evaluación',
    'tenancy/nom035-route:rows' => 'catálogo fijo: los seis requisitos de la NOM-035 del centro, uno por etapa, solo conteos',
    'tenancy/nom035-inspection:missing' => 'catálogo fijo: los requisitos que faltan (6 a lo más)',
    'tenancy/nom035-inspection:ready' => 'catálogo fijo: los requisitos que se pueden mostrar (6 a lo más)',
    'campaigns/survey:instruments' => 'las guías que contesta una persona: tres a lo más',
    'seating/seats:kinds' => 'catálogo fijo: los cuatro tipos de asiento de la Quinta E',
    'seating/seats:uses' => 'catálogo fijo: en el puesto o en un área de descanso',
    'seating/seats:conditions' => 'catálogo fijo: buen estado, por reparar o fuera de servicio',
    'seating/seats:features' => 'catálogo fijo: las nueve características de la Quinta F',
    'seating/seats:shortfalls' => 'resumen corto: los puestos con menos asientos que personas a la vez de un centro',
    'seating/documents:kinds' => 'catálogo fijo: los cuatro tipos de documento',
    'seating/documents:incisos' => 'catálogo fijo: los diez incisos de la Quinta (A a J)',
    'seating/assessment:measureKinds' => 'catálogo fijo: los cinco tipos de medida de la Quinta G y «otra»',
    'seating/plan:kinds' => 'catálogo fijo: los cinco tipos de medida de la Quinta G y «otra»',
    'seating/plan:statuses' => 'catálogo fijo: planeada, en curso, hecha o cancelada',
    'seating/plan:owners' => 'resumen corto: las personas de la empresa que pueden responder por una medida',
    'seating/plan:withoutAgreement' => 'resumen corto: los puestos de un centro con asiento aparte y sin acuerdo de pausas, pocos',
    'seating/assessment:referrals' => 'resumen corto: las últimas diez canalizaciones de una persona, que tiene pocas',
    'seating/assessment:facts' => 'catálogo fijo: los once datos del registro de la Quinta C',
    'seating/assessment:conditions' => 'catálogo fijo: las siete condicionantes de la Quinta D con sus tres opciones del DOF',
    'seating/position:types' => 'catálogo fijo: los cuatro tipos de bipedestación',
    'seating/position:conditions' => 'catálogo fijo: las cinco condicionantes del puesto con sus tres opciones del DOF',
    'seating/center:sectors' => 'catálogo fijo: los cuatro sectores que declara un centro',
    'seating/center:requirements' => 'catálogo fijo: los trece requisitos del centro con su estado, solo conteos',
    'seating/center:tabs' => 'catálogo fijo: las ocho secciones del centro con su conteo',
    'seating/route:rows' => 'catálogo fijo: los trece requisitos del centro, cada uno en su etapa, solo conteos',
    'seating/inspection:missing' => 'catálogo fijo: los requisitos que faltan (13 a lo más)',
    'seating/inspection:ready' => 'catálogo fijo: los requisitos que se pueden mostrar (13 a lo más)',
    'telework/settings:presets' => 'catálogo fijo: los periodos predefinidos de la Sección II (cuatro)',
    'telework/checklist:questions' => 'las preguntas de una versión de la lista: un instrumento, 122 en la base',
    'telework/portal-checklist:questions' => 'las preguntas de una sección de la lista: un instrumento',
    'telework/checklist:requirements' => 'catálogo fijo: los requisitos del capítulo 7 y 5.1.1 (catorce)',
    'telework/documents:kinds' => 'catálogo fijo: los ocho tipos de documento de la norma, con sus incisos',
    'telework/committee:candidates' => 'quienes pueden integrar la Comisión: las personas de la empresa con rol de revisión (dueño, RH, Comisión), pocas',
    'telework/acknowledgements:committee' => 'resumen corto: los integrantes de la Comisión que confirman en el panel, pocos por centro',
    'telework/trainings:uncovered' => 'resumen corto: los temas del 8.1 sin cubrir en un centro, ocho a lo más',
    'telework/trainings:topics' => 'catálogo fijo: los ocho temas del numeral 8.1',
    'telework/trainings:modalities' => 'catálogo fijo: remota, presencial o mixta',
    'telework/center:rows' => 'catálogo fijo: los renglones de la tabla 10.3 (17)',
    'telework/inspection:missing' => 'catálogo fijo: los renglones de la tabla 10.3 que faltan (17 a lo más)',
    'telework/inspection:ready' => 'catálogo fijo: los renglones de la tabla 10.3 que se pueden mostrar (17 a lo más)',
    'telework/route:rows' => 'catálogo fijo: los renglones de la tabla 10.3 (17), cada uno en su etapa',
    'telework/route:accidents' => 'resumen corto: los avisos de accidente sin revisar de un centro, un renglón',
    'telework/queue:tasks' => 'resumen corto: lo que espera a la Comisión, dos renglones por cada uno de sus centros',
    'telework/queue:centers' => 'resumen corto: los centros donde se sienta la Comisión, con sus integrantes, pocos',
    'telework/notice:choices' => 'catálogo fijo: lo que se hizo por un accidente (NoticeAction, cinco)',
    'telework/write:questions' => 'las preguntas de un documento escrito con preguntas: un instrumento, ocho a lo más',
    'telework/write:sections' => 'catálogo fijo: las secciones de un documento escrito con preguntas, doce a lo más',
    'telework/write:items' => 'catálogo fijo: los incisos que pide el numeral del documento, doce a lo más',
    'telework/supporting-documents:documents' => 'catálogo fijo: los seis documentos de apoyo de la norma (5.3 a 5.14)',
    'compensation/benefit-create:kinds' => 'catálogo fijo: los cuatro modos de calcular una prestación (BenefitKind)',
    'compensation/benefit-create:frequencies' => 'catálogo fijo: diaria, mensual o anual',
    'compensation/benefit-create:groups' => 'catálogo fijo: sindicalizado o confianza',
    'compensation/benefit-create:sbc_integrations' => 'catálogo fijo: siempre, nunca o hasta un tope',
    'compensation/benefits:kinds' => 'catálogo fijo: los cuatro modos de calcular una prestación (BenefitKind)',
    'compensation/benefits:frequencies' => 'catálogo fijo: diaria, mensual o anual',
    'compensation/benefits:groups' => 'catálogo fijo: sindicalizado o confianza',
    'compensation/benefits:sbc_integrations' => 'catálogo fijo: siempre, nunca o hasta un tope',
    'compensation/scenario:assumptions' => 'catálogo fijo: los cinco supuestos del largo plazo (AssumptionKey)',
    'compensation/scenario:states' => 'catálogo fijo: las 32 entidades',
    'compensation/scenario-compare:scenarios' => 'resumen corto: de dos a cuatro escenarios comparados',
    'compensation/scenario-compare:rows' => 'resumen corto: un renglón por concepto del costo, menos de cuarenta',
    'compensation/scenario:horizons' => 'catálogo fijo: 3, 4 o 5 años',
    'compensation/scenario:others' => 'resumen corto: a lo más 4 escenarios activos para comparar el costo (ScenarioEditor::OTHERS)',
    'complaints/handlers:handlers' => 'resumen corto: las personas que atienden quejas, pocas por empresa',
    'complaints/channel:coverage' => 'catálogo fijo: las seis opciones de «contra quién», cada una con quienes la reciben (pocas por empresa)',
    'complaints/channel:candidates' => 'quienes pueden ser designadas: las personas de la empresa con un rol que permite atender quejas, pocas',
    'complaints/evidence:coverage' => 'catálogo fijo: las seis opciones de «contra quién» con su conteo',
    'complaints/handlers:candidates' => 'quienes pueden ser designadas: las personas de la empresa con un rol que permite atender quejas, pocas',
    'complaints/show:access' => 'resumen corto: los nombres de quienes tienen asignado el caso, pocos',
    'complaints/show:resolutions' => 'catálogo fijo: las cinco resoluciones con que cierra quien atiende',
    'complaints/show:candidates' => 'resumen corto: las personas que podrían recibir el caso (pocas por empresa)',
    'complaints/index:types' => 'catálogo fijo: los tres tipos de queja, denuncia y aviso',
    'seating/dossier:rows' => 'expediente imprimible: documento completo para la inspección',
    'seating/dossier:documents' => 'expediente imprimible: documento completo para la inspección',
    'seating/dossier:links' => 'catálogo fijo: los cuatro enlaces de lo que se queda dentro de la aplicación',
    'telework/dossier:rows' => 'expediente imprimible: documento completo para la inspección',
    'telework/dossier:roster' => 'expediente imprimible: documento completo para la inspección',
    'telework/dossier:workplaces' => 'expediente imprimible: documento completo para la inspección',
    'telework/dossier:documents' => 'expediente imprimible: documento completo para la inspección',
    'telework/dossier:equipment' => 'expediente imprimible: documento completo para la inspección',
    'telework/dossier:trainings' => 'expediente imprimible: documento completo para la inspección',
    'telework/dossier:overdue' => 'expediente imprimible: documento completo para la inspección',
    'telework/center:tabs' => 'resumen corto: las secciones del centro con su conteo, seis a lo más',
    'campaigns/show:instruments' => 'resumen por guía de la evaluación: tres a lo más',
    'campaigns/show:standings' => 'conteos por situación: cinco',
    'campaigns/show:areas' => 'resumen por área del centro: un conteo por área, no personas',
    'campaigns/show:replacementPool' => 'conteos por género: tres a lo más',
    'results/show:items' => 'catálogo fijo: las preguntas de la guía, contadas (72 a lo más)',
    'results/report:durations' => 'catálogo fijo: las seis duraciones de un enlace',
    'results/report:levels' => 'catálogo fijo: los cinco niveles de riesgo',
    'results/shared-report:levels' => 'catálogo fijo: los cinco niveles de riesgo',
    'results/show:levels' => 'catálogo fijo: los cinco niveles de riesgo',
];

/** @return array<string, string> page => its source */
function pageSources(): array
{
    $root = __DIR__.'/../../resources/js/pages/';
    $sources = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && str_ends_with($file->getPathname(), '.tsx')) {
            $sources[substr($file->getPathname(), strlen($root), -strlen('.tsx'))] = (string) file_get_contents($file->getPathname());
        }
    }

    ksort($sources);

    return $sources;
}

it('gives no page a list of records as an array', function (): void {
    $found = [];

    foreach (pageSources() as $page => $source) {
        preg_match_all('/^\s+(\w+)\??: [^;=]*\[\](?: \| null)?;$/m', $source, $matches);

        foreach ($matches[1] as $prop) {
            $found[] = "{$page}:{$prop}";
        }
    }

    sort($found);
    $allowed = array_keys(ARRAY_PROPS);
    sort($allowed);

    expect($found)->toBe($allowed, 'An array prop that is not a fixed catalog or a short summary must be a Paginated<…> instead.');
});

it('checks at runtime every page that takes a paginated list', function (): void {
    $runtime = (string) file_get_contents(__DIR__.'/../Feature/PaginatedListsTest.php');
    $paginated = array_keys(array_filter(pageSources(), fn (string $source): bool => str_contains($source, 'Paginated<')));
    $unchecked = array_values(array_filter($paginated, fn (string $page): bool => ! str_contains($runtime, "'{$page}'")));

    expect($paginated)->not->toBeEmpty()
        ->and($unchecked)->toBe([], 'Pages with a Paginated prop missing from tests/Feature/PaginatedListsTest.php: '.implode(', ', $unchecked));
});
