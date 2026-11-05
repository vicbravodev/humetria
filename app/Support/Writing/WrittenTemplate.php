<?php

declare(strict_types=1);

namespace App\Support\Writing;

/**
 * A document written with questions (decisión A3): its questions and the text its answers make. The wording is a draft for
 * a lawyer to review before production (TODO-LEGAL); what the boards and the DOF give is used as it is.
 */
interface WrittenTemplate
{
    /** Changes whenever the questions or the wording change; each published version keeps the one it was written with. */
    public function version(): string;

    /** How RH calls it in the lists ("Riesgos de trabajar desde casa"). */
    public function name(): string;

    /** The title of the document as people read it. */
    public function title(): string;

    /** What it is, in a line, for the list of documents. */
    public function summary(): string;

    /** About how long its questions take, in minutes. */
    public function minutes(): int;

    /** @return list<Question> */
    public function questions(): array;

    /**
     * The sections in the order the document shows them.
     *
     * @return list<Section>
     */
    public function sections(Answers $answers): array;
}
