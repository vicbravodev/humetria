<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

/**
 * The notice a person reads before answering about their footwear and discomfort (criterio C-18). Answering is voluntary;
 * the company's lawyer validates the legal basis before launch. A company-editable text is still to come.
 */
final class PrivacyNotice
{
    public static function text(): string
    {
        return 'Contestar sobre su calzado y sus molestias es voluntario. Si lo hace, esos datos de salud los verán únicamente '
            .'el área de Recursos Humanos y la Comisión de Seguridad e Higiene de su centro de trabajo, y solo para evaluar su nivel '
            .'de riesgo por trabajar de pie y decidir qué medidas le corresponden. No se publican ni se comparten con otras personas. '
            .'Si prefiere no contestar, la evaluación se hará sin esos datos y no tendrá ninguna consecuencia para usted.';
    }
}
