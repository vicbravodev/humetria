<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * La lógica de un módulo entra a la mutación semanal (docs/testing.md, «Convención»): toda clase concreta de Actions,
 * Support, Listeners o Jobs la declara algún test en mutates(), o está aquí con su razón. Sin esta guardia la regla
 * dependía de acordarse, y las clases nuevas la cumplían cada vez menos (86 % a fines de octubre, 68 % a fines de
 * noviembre de 2026).
 */

/** @return array<string, string> the short-name suffixes whose classes only shape what a page or a text shows, with why */
function mutationExemptSuffixes(): array
{
    return [
        'Pages' => 'Arma las props de las páginas de Inertia: sus tests de página fijan lo que se ve, y la lógica que decide vive en Actions y Support con nombre propio.',
        'Page' => 'Arma las props de una página de Inertia (igual que Pages).',
        'Template' => 'Texto fijo de un documento: lo fijan los tests del documento, no hay decisión que mutar.',
    ];
}

/** @return array<string, list<string>> the classes left out of the mutation, by why */
function mutationExemptClasses(): array
{
    return [
        'Adaptador a un servicio externo (red, DNS, disco): sus tests usan el fake del contrato, y mutarlo exigiría la red.' => [
            'App\Modules\Sso\Support\DnsDomainOwnership',
        ],
        'Lista fija de preguntas de la revisión de calidad de Humi: la lógica que las usa se muta en QualityAssistant.' => [
            'App\Modules\Assistant\Support\QualityQuestions',
        ],
        'Arma las rutas del panel a las que enlazan las respuestas, sin decidir nada: los tests de cada herramienta fijan el enlace.' => [
            'App\Modules\Assistant\Support\PanelLinks',
        ],
        'DTO o valor de solo constructor: no decide nada, lo arma y lo lee lógica que sí se muta.' => [
            'App\Modules\Billing\Support\UnitCost',
        ],
        'Catálogos fijos del SAT (régimen fiscal y uso del CFDI): validar el código lo fijan los tests de AccountRules, que sí se muta, y mutar cada renglón del catálogo no mide ninguna decisión.' => [
            'App\Modules\Billing\Support\SatCatalogs',
        ],
        'Lista fija de las herramientas de las que habla un artículo: la regla que la lee (una herramienta que no está, un artículo inválido) se muta en ArticleParser.' => [
            'App\Modules\Blog\Support\BlogTools',
        ],
        'Pendiente de clasificar (26 nov 2026): entra a la mutación o pasa arriba con su razón. Esta lista solo se achica.' => [
            'App\Modules\Campaigns\Support\CampaignClock',
            'App\Modules\Campaigns\Support\CampaignInstruments',
            'App\Modules\Campaigns\Support\CampaignInvitationLinks',
            'App\Modules\Campaigns\Support\CompanyCenterSurveys',
            'App\Modules\Campaigns\Support\InstrumentTally',
            'App\Modules\Campaigns\Support\InvitationComposer',
            'App\Modules\Campaigns\Support\OpenedSurvey',
            'App\Modules\Compensation\Support\BenefitRows',
            'App\Modules\Compensation\Support\CompensationStart',
            'App\Modules\Compensation\Support\ConceptLabels',
            'App\Modules\Compensation\Support\ScenarioEditor',
            'App\Modules\Compensation\Support\ScenarioRows',
            'App\Modules\Compensation\Support\ShareMoments',
            'App\Modules\Complaints\Support\ChannelSettings',
            'App\Modules\Complaints\Support\ComplaintInput',
            'App\Modules\Complaints\Support\Dates',
            'App\Modules\Complaints\Support\OpenedChannel',
            'App\Modules\Complaints\Support\ProcedureModel',
            'App\Modules\Complaints\Support\RandomCode',
            'App\Modules\Complaints\Support\RandomReferenceSource',
            'App\Modules\Complaints\Support\Receipt',
            'App\Modules\Demos\Actions\ListDemoRequests',
            'App\Modules\Demos\Actions\StartDemoRun',
            'App\Modules\Demos\Jobs\PrepareDemo',
            'App\Modules\Demos\Jobs\ResetDemo',
            'App\Modules\Demos\Support\AdmissionResult',
            'App\Modules\Demos\Support\DemoDoor',
            'App\Modules\Demos\Support\DemoLeadRegistry',
            'App\Modules\Demos\Support\DoorKeeper',
            'App\Modules\Demos\Support\QrCode',
            'App\Modules\Disclosure\Support\Days',
            'App\Modules\Disclosure\Support\DeliveryStanding',
            'App\Modules\Disclosure\Support\DocumentTemplates',
            'App\Modules\Disclosure\Support\PageUrl',
            'App\Modules\Disclosure\Support\PolicyFact',
            'App\Modules\Disclosure\Support\PolicyText',
            'App\Modules\Disclosure\Support\PublicChannel',
            'App\Modules\Disclosure\Support\PublicMeasure',
            'App\Modules\Disclosure\Support\PublicationCenters',
            'App\Modules\Disclosure\Support\Requirement',
            'App\Modules\Disclosure\Support\ResultsFact',
            'App\Modules\Disclosure\Support\ResultsStanding',
            'App\Modules\Disclosure\Support\Writing\Sentence',
            'App\Modules\Engagement\Support\EngagementInvitationLinks',
            'App\Modules\Engagement\Support\InvitationComposer',
            'App\Modules\Engagement\Support\ManagerSurvey',
            'App\Modules\Engagement\Support\OpenedInvitation',
            'App\Modules\Engagement\Support\Pulse\PulseHistory',
            'App\Modules\Engagement\Support\Results\CutGroups',
            'App\Modules\Engagement\Support\Results\Delta',
            'App\Modules\Engagement\Support\Results\DriverResult',
            'App\Modules\Engagement\Support\Results\GroupSummary',
            'App\Modules\Engagement\Support\Results\ItemResult',
            'App\Modules\Engagement\Support\Results\RecommendationResult',
            'App\Modules\Engagement\Support\Results\ResponseRow',
            'App\Modules\Engagement\Support\Results\Wave',
            'App\Modules\Instruments\Support\Nom035\Likert',
            'App\Modules\Instruments\Support\Nom035\Nom035',
            'App\Modules\Instruments\Support\Nom035\ScoringTable',
            'App\Modules\Instruments\Support\Nom037\Nom037',
            'App\Modules\Interventions\Support\ProgramClock',
            'App\Modules\Leave\Actions\SaveWorkWeek',
            'App\Modules\Leave\Support\CompanyPendingRequests',
            'App\Modules\Leave\Support\EmployeeLock',
            'App\Modules\Messaging\Listeners\MarkMailSent',
            'App\Modules\Messaging\Support\DeliveryFailures',
            'App\Modules\Messaging\Support\MailDeliveryStash',
            'App\Modules\Messaging\Support\MetaStatus',
            'App\Modules\Messaging\Support\TemplateExport',
            'App\Modules\Results\Support\ResultsClock',
            'App\Modules\Results\Support\RiskLevels',
            'App\Modules\Results\Support\SuppressionResult',
            'App\Modules\Seating\Listeners\FlagOnPersonInput',
            'App\Modules\Seating\Listeners\RequestOnSignature',
            'App\Modules\Seating\Support\AssessmentAccess',
            'App\Modules\Seating\Support\ComplianceRow',
            'App\Modules\Seating\Support\EvidenceStore',
            'App\Modules\Seating\Support\PdfStore',
            'App\Modules\Seating\Support\PrivacyNotice',
            'App\Modules\Seating\Support\QuintaIncisos',
            'App\Modules\Seating\Support\SeatingFiles',
            'App\Modules\Seating\Support\Today',
            'App\Modules\Telework\Jobs\SendPortalInvitation',
            'App\Modules\Telework\Jobs\SendPortalReminder',
            'App\Modules\Telework\Support\CenterFacts',
            'App\Modules\Telework\Support\ChecklistOrder',
            'App\Modules\Telework\Support\ComplianceRow',
            'App\Modules\Telework\Support\NoticeAccess',
            'App\Modules\Telework\Support\OpenedPortal',
            'App\Modules\Telework\Support\TeleworkWorkerSection',
            'App\Modules\Telework\Support\WorkplaceAddress',
            'App\Modules\Tenancy\Support\ComplianceRoutes',
            'App\Modules\Tenancy\Support\DisplayDate',
            'App\Modules\Tenancy\Support\Nom035Facts',
            'App\Modules\Tenancy\Support\PermissionDefinition',
            'App\Modules\WorkerAccess\Support\CeremonySignatures',
            'App\Modules\WorkerAccess\Support\IgnoreCounter',
            'App\Modules\WorkerAccess\Support\LinkInvitations',
            'App\Modules\WorkerAccess\Support\OpenSession',
            'App\Modules\WorkerAccess\Support\RouteWorkerLinks',
        ],
    ];
}

/** @return list<string> the concrete classes of Actions, Support, Listeners and Jobs of every module */
function mutationLogicClasses(): array
{
    $classes = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/Modules', FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = (string) $file;
        $source = (string) file_get_contents($path);

        if (preg_match('#/app/Modules/\w+/(Actions|Support|Listeners|Jobs)/.+\.php$#', $path) !== 1 || preg_match('/^(final |readonly )*class \w+/m', $source) !== 1) {
            continue;
        }

        $classes[] = 'App\\'.str_replace('/', '\\', substr($path, strlen(dirname(__DIR__, 2).'/app/'), -strlen('.php')));
    }

    sort($classes);

    return $classes;
}

/** @return list<string> every class a test declares in mutates() (scripts/mutation-classes.php) */
function mutationDeclaredClasses(): array
{
    $process = new Process([PHP_BINARY, 'scripts/mutation-classes.php', 'tests'], dirname(__DIR__, 2));
    $process->mustRun();

    return array_values(array_filter(explode("\n", $process->getOutput())));
}

/** Whether mutates() reaches the class: it compares by prefix, so mutates(Foo::class) also mutates FooBar. */
/** @param  list<string>  $mutated */
function mutationReaches(string $class, array $mutated): bool
{
    foreach ($mutated as $declared) {
        if (str_starts_with($class, $declared)) {
            return true;
        }
    }

    return false;
}

function mutationExemptBySuffix(string $class): bool
{
    $short = substr((string) strrchr($class, '\\'), 1);

    foreach (array_keys(mutationExemptSuffixes()) as $suffix) {
        if (str_ends_with($short, $suffix)) {
            return true;
        }
    }

    return false;
}

it('puts the logic of every module under the weekly mutation, or says why not', function (): void {
    $mutated = mutationDeclaredClasses();
    $exempt = array_merge(...array_values(mutationExemptClasses()));
    $missing = array_values(array_filter(mutationLogicClasses(), fn (string $class): bool => ! mutationReaches($class, $mutated) && ! mutationExemptBySuffix($class) && ! in_array($class, $exempt, true)));

    expect($missing)->toBe([], "Ningún test declara mutates() de estas clases. Declara mutates(Clase::class) en el test que las prueba (docs/testing.md, «Convención») o agrega su razón en mutationExemptClasses():\n".implode("\n", $missing));
});

it('exempts only classes that exist and that no test mutates already', function (): void {
    $mutated = mutationDeclaredClasses();
    $classes = mutationLogicClasses();

    foreach (array_merge(...array_values(mutationExemptClasses())) as $class) {
        expect($class)->toBeIn($classes, "{$class} no existe: quítala de mutationExemptClasses().")
            ->and(mutationReaches($class, $mutated))->toBeFalse("{$class} ya está en la mutación: quítala de mutationExemptClasses().");
    }
});
