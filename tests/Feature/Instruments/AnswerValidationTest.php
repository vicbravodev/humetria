<?php

declare(strict_types=1);

use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Support\AnswerValidator;
use Illuminate\Support\Arr;

mutates(AnswerValidator::class);

function validateAnswers(string $key, array $answers): void
{
    resolve(Instruments::class)->validate($key, $answers);
}

it('accepts a complete Guía II or III with both blocks answered', function (string $key): void {
    validateAnswers($key, nom035Answers($key));
})->with(['nom035.guide-ii', 'nom035.guide-iii'])->throwsNoExceptions();

it('accepts both gates answered no, without their items', function (string $key): void {
    validateAnswers($key, nom035Answers($key, 'never', clients: false, supervises: false));
})->with(['nom035.guide-ii', 'nom035.guide-iii'])->throwsNoExceptions();

it('accepts every answer option', function (string $value): void {
    validateAnswers('nom035.guide-iii', nom035Answers('nom035.guide-iii', $value));
})->with(['always', 'almost_always', 'sometimes', 'almost_never', 'never'])->throwsNoExceptions();

it('rejects a questionnaire with an item missing', function (string $key, string $missing): void {
    validateAnswers($key, Arr::except(nom035Answers($key), $missing));
})->with([
    ['nom035.guide-ii', '1'],
    ['nom035.guide-ii', '40'],
    ['nom035.guide-iii', '64'],
    ['nom035.guide-iii', '72'],
    ['nom035.guide-iii', 'serves_clients'],
    ['nom035.guide-iii', 'supervises'],
])->throws(InvalidArgumentException::class, 'Conteste todas las preguntas.');

it('rejects an item the questionnaire does not ask', function (): void {
    validateAnswers('nom035.guide-ii', array_replace(nom035Answers('nom035.guide-ii'), ['47' => 'never']));
})->throws(InvalidArgumentException::class);

it('rejects an answer outside the options', function (string $value): void {
    validateAnswers('nom035.guide-iii', array_replace(nom035Answers('nom035.guide-iii'), ['12' => $value]));
})->with(['yes', 'sometimes ', 'SIEMPRE', ''])->throws(InvalidArgumentException::class);

it('rejects a gate answered other than yes or no', function (): void {
    validateAnswers('nom035.guide-iii', array_replace(nom035Answers('nom035.guide-iii'), ['supervises' => 'sometimes']));
})->throws(InvalidArgumentException::class);

it('rejects the items of a block whose gate was answered no', function (string $key, string $gate, string $item): void {
    validateAnswers($key, array_replace(nom035Answers($key, clients: $gate !== 'serves_clients', supervises: $gate !== 'supervises'), [$item => 'never']));
})->with([
    ['nom035.guide-ii', 'serves_clients', '41'],
    ['nom035.guide-ii', 'supervises', '46'],
    ['nom035.guide-iii', 'serves_clients', '68'],
    ['nom035.guide-iii', 'supervises', '69'],
])->throws(InvalidArgumentException::class);

it('rejects a yes without every item of its block', function (string $key, string $item): void {
    validateAnswers($key, Arr::except(nom035Answers($key), $item));
})->with([
    ['nom035.guide-ii', '43'],
    ['nom035.guide-iii', '65'],
])->throws(InvalidArgumentException::class);

it('ends Guía I after a section I answered all no', function (): void {
    validateAnswers('nom035.guide-i', guideOneAnswers());
})->throwsNoExceptions();

it('takes the whole Guía I after any yes in section I', function (int $yes): void {
    validateAnswers('nom035.guide-i', guideOneAnswers([$yes]));
})->with([1, 6])->throwsNoExceptions();

it('rejects sections II to IV after a section I answered all no', function (): void {
    validateAnswers('nom035.guide-i', array_replace(guideOneAnswers(), ['7' => 'no']));
})->throws(InvalidArgumentException::class);

it('requires the whole Guía I after a yes in section I', function (): void {
    validateAnswers('nom035.guide-i', Arr::except(guideOneAnswers([2]), '20'));
})->throws(InvalidArgumentException::class, 'Conteste todas las preguntas.');

it('rejects a Guía I without section I', function (): void {
    validateAnswers('nom035.guide-i', Arr::except(guideOneAnswers(), '3'));
})->throws(InvalidArgumentException::class);

it('rejects an answer other than yes or no in Guía I', function (): void {
    validateAnswers('nom035.guide-i', array_replace(guideOneAnswers(), ['1' => 'sometimes']));
})->throws(InvalidArgumentException::class);
