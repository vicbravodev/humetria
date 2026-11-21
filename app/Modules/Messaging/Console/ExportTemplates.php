<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Console;

use App\Modules\Messaging\Support\TemplateExport;
use App\Modules\Messaging\Support\TemplateRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Writes the creation request of every WhatsApp template, as Meta's API asks for it. It sends nothing and reads no credential: the
 * templates are for internal use until the business is verified and the flow is finished.
 */
#[Signature('messaging:export-templates {--path= : Directorio donde escribir un JSON por plantilla (sin él, todo sale por la salida estándar)}')]
#[Description('Exporta el JSON de creación de cada plantilla de WhatsApp (no manda nada a Meta)')]
final class ExportTemplates extends Command
{
    public function handle(TemplateRegistry $registry, TemplateExport $export): int
    {
        $requests = array_map($export->build(...), $registry->all());
        $path = $this->option('path');

        if (! is_string($path) || $path === '') {
            $this->line(json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        File::ensureDirectoryExists($path);

        foreach ($requests as $key => $request) {
            File::put("{$path}/{$key}.json", json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        }

        $this->components->info(count($requests)." plantillas escritas en {$path}. Nada se mandó a Meta.");

        return self::SUCCESS;
    }
}
