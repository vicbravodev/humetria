<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support\Writing;

use App\Support\Writing\Answers;
use App\Support\Writing\Field;
use App\Support\Writing\Question;
use App\Support\Writing\Section;
use App\Support\Writing\WrittenTemplate;

/**
 * The mechanisms to present a complaint (num. 5.7 d) written with one question: where a person can present it. The channel
 * of complaints, when there is one, is shown on the page as it is, and this text is added to it (criterio C-52).
 *
 * TODO-LEGAL: the question, options and wording are Humetría's; a lawyer validates them before production.
 */
final readonly class ComplaintTemplate implements WrittenTemplate
{
    public function version(): string
    {
        return 'questions-2026-11-05';
    }

    public function name(): string
    {
        return 'Quejas';
    }

    public function title(): string
    {
        return 'Cómo presentar una queja';
    }

    public function summary(): string
    {
        return 'Por dónde se presenta una queja o una denuncia.';
    }

    public function minutes(): int
    {
        return 2;
    }

    public function questions(): array
    {
        return [
            new Question('channels', 'Quejas', '¿Por dónde puede alguien presentar una queja o denuncia?', [
                new Field('channels', 'Por dónde', ['channel' => 'El canal de quejas de Humetría, también sin dar su nombre', 'hr' => 'Con Recursos Humanos, en persona o por correo', 'person' => 'Con una persona de confianza designada', 'committee' => 'Con la Comisión de seguridad e higiene'], multiple: true, default: ['channel']),
            ], 'Elige todos los que vas a ofrecer.'),
        ];
    }

    public function sections(Answers $answers): array
    {
        $channels = $answers->list('channels', ['channel' => 'por el canal de quejas, también sin dar su nombre', 'hr' => 'con Recursos Humanos, en persona o por correo', 'person' => 'con una persona de confianza designada', 'committee' => 'con la Comisión de seguridad e higiene']);

        return [
            new Section('channels', 'Dónde presentarla', $channels === null ? null : "Puede presentar una queja o denuncia {$channels}."),
        ];
    }
}
