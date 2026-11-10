<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Bank;

use App\Modules\Engagement\Enums\Driver;

/** What a manager can commit to for a driver that needs work. A fixed catalog, written in tú like the rest of the panel. */
final class SuggestedActions
{
    /**
     * @return list<string>
     */
    public static function for(Driver $driver): array
    {
        return match ($driver) {
            Driver::Clarity => [
                'Presenta las 3 metas del año en una junta por área y deja que cada equipo escriba cómo aporta',
                'Revisa prioridades con tu equipo al inicio de cada mes',
            ],
            Driver::Leadership => [
                'Explica por escrito el motivo de cada decisión importante antes de aplicarla',
                'Abre una sesión trimestral de preguntas a la dirección',
                'Publica qué se hizo con lo prometido',
            ],
            Driver::Communication => [
                'Avisa de cada cambio con fecha, a quién afecta y a quién preguntar',
                'Define un contacto por área para dudas de información',
                'Acuerda con otras áreas qué información se entrega y cuándo',
            ],
            Driver::Recognition => [
                'Publica los criterios con los que se reconoce un buen trabajo',
                'Pide a cada jefe decir qué se hizo bien, con un ejemplo, al cerrar un resultado',
            ],
            Driver::Growth => [
                'Da a cada persona una tarea donde aplique lo que aprendió en su último curso',
                'Publica las vacantes internas antes de buscarlas fuera',
            ],
            Driver::Autonomy => [
                'Define con cada equipo qué decisiones puede tomar sin pedir permiso',
                'Quita una aprobación que no agrega valor',
            ],
            Driver::Resources => [
                'Levanta la lista de herramientas que fallan y fija fecha para las 3 más citadas',
                'Revisa con el equipo un proceso y quita un paso',
                'Revisa la carga por equipo antes de aceptar nuevos compromisos',
            ],
            Driver::Team => [
                'Acuerda reglas de trato y de coordinación en cada equipo',
                'Fija una reunión breve semanal para coordinar tareas',
            ],
            Driver::Voice => [
                'Abre un canal para proponer mejoras y responde cada propuesta en 15 días',
                'Explica cómo se aplican las reglas y revisa los casos donde se percibe trato desigual',
            ],
            Driver::Reward => [
                'Publica los criterios de aumentos de sueldo',
                'Explica las prestaciones actuales y pregunta cuáles se usan',
                'Compara los sueldos del puesto con sus responsabilidades',
            ],
        };
    }
}
