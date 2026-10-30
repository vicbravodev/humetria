<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support\Nom035;

use App\Modules\Instruments\Data\InstrumentDefinition;
use App\Modules\Instruments\Data\SectionDefinition;

/**
 * NOM-035 Guía de referencia I: identifies who went through a severe traumatic event at work and needs a clinical
 * assessment (num. 5.5). Identified: the employer must channel the person to care. Literal from the DOF; the norm does
 * not number its questions, 1 to 20 is ours.
 */
final class GuideOne
{
    public const string KEY = 'nom035.guide-i';

    /**
     * Sections II to IV are asked only after a "yes" in section I (GR.I a and b).
     *
     * @param  array<array-key, mixed>  $answers
     */
    public static function continuesAfterSectionOne(array $answers): bool
    {
        foreach (range(1, 6) as $number) {
            if (($answers[(string) $number] ?? null) === 'yes') {
                return true;
            }
        }

        return false;
    }

    public static function definition(): InstrumentDefinition
    {
        return new InstrumentDefinition(self::KEY, Nom035::VERSION, 'Acontecimientos traumáticos severos', true, [
            ['value' => 'yes', 'label' => 'Sí'],
            ['value' => 'no', 'label' => 'No'],
        ], [
            new SectionDefinition('I.- Acontecimiento traumático severo', '¿Ha presenciado o sufrido alguna vez, durante o con motivo del trabajo un acontecimiento como los siguientes:', Nom035::items([
                1 => 'Accidente que tenga como consecuencia la muerte, la pérdida de un miembro o una lesión grave?',
                2 => 'Asaltos?',
                3 => 'Actos violentos que derivaron en lesiones graves?',
                4 => 'Secuestro?',
                5 => 'Amenazas?, o',
                6 => 'Cualquier otro que ponga en riesgo su vida o salud, y/o la de otras personas?',
            ])),
            new SectionDefinition('II.- Recuerdos persistentes sobre el acontecimiento (durante el último mes):', '', Nom035::items([
                7 => '¿Ha tenido recuerdos recurrentes sobre el acontecimiento que le provocan malestares?',
                8 => '¿Ha tenido sueños de carácter recurrente sobre el acontecimiento, que le producen malestar?',
            ])),
            new SectionDefinition('III.- Esfuerzo por evitar circunstancias parecidas o asociadas al acontecimiento (durante el último mes):', '', Nom035::items([
                9 => '¿Se ha esforzado por evitar todo tipo de sentimientos, conversaciones o situaciones que le puedan recordar el acontecimiento?',
                10 => '¿Se ha esforzado por evitar todo tipo de actividades, lugares o personas que motivan recuerdos del acontecimiento?',
                11 => '¿Ha tenido dificultad para recordar alguna parte importante del evento?',
                12 => '¿Ha disminuido su interés en sus actividades cotidianas?',
                13 => '¿Se ha sentido usted alejado o distante de los demás?',
                14 => '¿Ha notado que tiene dificultad para expresar sus sentimientos?',
                15 => '¿Ha tenido la impresión de que su vida se va a acortar, que va a morir antes que otras personas o que tiene un futuro limitado?',
            ])),
            new SectionDefinition('IV Afectación (durante el último mes):', '', Nom035::items([
                16 => '¿Ha tenido usted dificultades para dormir?',
                17 => '¿Ha estado particularmente irritable o le han dado arranques de coraje?',
                18 => '¿Ha tenido dificultad para concentrarse?',
                19 => '¿Ha estado nervioso o constantemente en alerta?',
                20 => '¿Se ha sobresaltado fácilmente por cualquier cosa?',
            ])),
        ]);
    }
}
