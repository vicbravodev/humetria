<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Contracts;

use App\Modules\Instruments\Data\ChecklistItem;

/**
 * The base NOM-037 verification checklist (Apéndice 3 plus Humetría's coverage questions) that each work center starts from.
 */
interface Nom037Checklist
{
    /** The DOF edition the questions are transcribed from. */
    public function version(): string;

    /** @return list<ChecklistItem> Section I questions first, then Section II. */
    public function items(): array;

    /** The literal «Instrucciones de uso» of Apéndice 3. */
    public function instructions(): string;

    /** Shown before starting: answering does not shift responsibility to the person (numeral 5.5). */
    public function responsibilityNotice(): string;

    /** Consent for health-related answers and photos, to replace with the company's own if it wants. */
    public function baseConsent(): string;
}
