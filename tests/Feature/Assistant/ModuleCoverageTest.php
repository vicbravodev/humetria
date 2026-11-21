<?php

declare(strict_types=1);

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Support\ComplianceReaders;
use App\Modules\Tenancy\Enums\Module;

/*
 * Humi tiene que estar al corriente de todo lo que una empresa usa: un módulo que se construye sin conexión con Humi no
 * está terminado (CLAUDE.md, «Humi está al corriente»). Lo que no se le conecta va aquí con su razón.
 */

/** @return array<string, string> the company modules Humi does not report on, with why */
function humiExemptCompanyModules(): array
{
    return [
        Module::Sso->value => 'Es un ajuste de cómo se entra a la cuenta, no algo de la empresa que informar.',
    ];
}

/** @return array<string, string> the modules of app/Modules that Humi does not read, with why */
function humiExemptCodeModules(): array
{
    return [
        'Assistant' => 'Es Humi.',
        'Blog' => 'Es contenido público del sitio, sin datos de ninguna empresa: no hay nada de la empresa que Humi informe.',
        'Demos' => 'Solicitudes de demo y la empresa demo: son del superadmin, no de la empresa.',
        'Messaging' => 'Es transporte (WhatsApp y eventos de correo): lo que Humi informa de las invitaciones sale de Campaigns y Engagement, y la entrega por persona no se expone nunca, solo agregados con mínimo.',
        'Responses' => 'Respuestas anónimas: Humi solo las ve agregadas y con el mínimo por grupo, a través de Results.',
        'Sso' => 'Es un ajuste de cómo se entra a la cuenta, no algo de la empresa que informar.',
        'WorkerAccess' => 'Llaves e identidad de la persona trabajadora: Humi nunca las ve.',
    ];
}

it('tells where the company stands in every module it can have', function (string $module): void {
    $readers = resolve(ComplianceReaders::class);
    $covered = array_map(fn (ComplianceReader $reader): ?string => $readers->moduleOf($reader), $readers->all());

    expect(in_array($module, $covered, true))->toBeTrue("Humi no informa del módulo «{$module}»: agrega su lector en AssistantServiceProvider o su razón en humiExemptCompanyModules().");
})->with(fn (): array => array_values(array_diff(array_map(fn (Module $module): string => $module->value, Module::cases()), array_keys(humiExemptCompanyModules()))));

it('reads every module of the code through its public contracts', function (string $module): void {
    $reads = false;

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Modules/Assistant'), FilesystemIterator::SKIP_DOTS)) as $file) {
        $reads = $reads || str_contains((string) file_get_contents((string) $file), "use App\\Modules\\{$module}\\Contracts\\");
    }

    expect($reads)->toBeTrue("Humi no lee el módulo {$module}: conéctalo por uno de sus Contracts o agrega su razón en humiExemptCodeModules().");
})->with(fn (): array => array_values(array_diff(humiCodeModules(), array_keys(humiExemptCodeModules()))));

it('exempts only modules that exist', function (): void {
    expect(array_keys(humiExemptCompanyModules()))->each->toBeIn(array_map(fn (Module $module): string => $module->value, Module::cases()))
        ->and(array_keys(humiExemptCodeModules()))->each->toBeIn(humiCodeModules());
});

/** @return list<string> the modules of app/Modules (read from disk: a dataset is built before the app boots) */
function humiCodeModules(): array
{
    return array_map('basename', glob(dirname(__DIR__, 3).'/app/Modules/*', GLOB_ONLYDIR) ?: []);
}
