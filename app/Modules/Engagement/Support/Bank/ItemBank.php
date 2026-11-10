<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Bank;

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Enums\ItemScale;
use InvalidArgumentException;

/**
 * The questions of the climate and pulse surveys, word for word as docs/superpowers/specs/2026-11-09-engagement-item-bank.md
 * has them. Changing the wording of a question means raising its `wording`, never editing the text in place: a survey
 * stores the key and the wording it was asked with, and a trend only compares equal wordings.
 */
final class ItemBank
{
    public const string VERSION = 'v1';

    /** The three questions whose average favorable share is the engagement index. */
    public const array INDEX = ['eng.pride', 'eng.motivation', 'eng.connection'];

    public const string RECOMMEND = 'eng.recommend';

    public const string STAY = 'eng.stay';

    /** Asked from the second wave of a company on. */
    public const string LOOP = 'loop.action';

    public const array AGREEMENT_LABELS = [
        1 => 'Totalmente en desacuerdo',
        2 => 'En desacuerdo',
        3 => 'Ni de acuerdo ni en desacuerdo',
        4 => 'De acuerdo',
        5 => 'Totalmente de acuerdo',
    ];

    /** The instruction of the manager section; `:name` is the name of the manager. */
    public const string MANAGER_INTRO = 'Las siguientes preguntas son sobre su jefe directo: :name. Sus respuestas se juntan con las de sus compañeros y su jefe solo ve el resultado del grupo. El sistema no guarda quién contestó qué.';

    /** Shown next to every open question. */
    public const string OPEN_NOTICE = 'No escriba nombres ni datos que permitan identificarle a usted o a otra persona. Para reportar acoso o violencia use el canal de quejas.';

    /** @return list<BankItem> every question, in the order of the document */
    public static function all(): array
    {
        return [
            new BankItem('eng.pride', 'Me siento orgulloso(a) de trabajar en esta empresa.', ItemScale::Agreement, ItemBlock::Outcome, null),
            new BankItem('eng.motivation', 'Me siento motivado(a) para hacer bien mi trabajo.', ItemScale::Agreement, ItemBlock::Outcome, null),
            new BankItem('eng.connection', 'Quiero aportar a que esta empresa mejore.', ItemScale::Agreement, ItemBlock::Outcome, null),
            new BankItem('eng.recommend', '¿Qué tan probable es que recomiende esta empresa como lugar para trabajar? (0 = nada probable, 10 = muy probable)', ItemScale::Recommend, ItemBlock::Outcome, null),
            new BankItem('eng.stay', 'Me gustaría seguir trabajando en esta empresa durante el próximo año.', ItemScale::Agreement, ItemBlock::Outcome, null),
            new BankItem('clarity.goals', 'Conozco las principales metas que busca alcanzar esta empresa.', ItemScale::Agreement, ItemBlock::Company, Driver::Clarity),
            new BankItem('clarity.contribution', 'Sé cómo mi trabajo contribuye a los objetivos de la empresa.', ItemScale::Agreement, ItemBlock::Company, Driver::Clarity, anchor: true),
            new BankItem('clarity.priorities', 'Tengo claro qué es lo más importante de mi trabajo en este momento.', ItemScale::Agreement, ItemBlock::Company, Driver::Clarity),
            new BankItem('leadership.trust', 'Confío en las decisiones que toma la dirección de la empresa.', ItemScale::Agreement, ItemBlock::Company, Driver::Leadership),
            new BankItem('leadership.reasons', 'La dirección explica los motivos de sus decisiones importantes.', ItemScale::Agreement, ItemBlock::Company, Driver::Leadership, anchor: true),
            new BankItem('leadership.consistency', 'Lo que la dirección dice coincide con lo que hace.', ItemScale::Agreement, ItemBlock::Company, Driver::Leadership),
            new BankItem('communication.informed', 'Me entero a tiempo de los cambios que afectan mi trabajo.', ItemScale::Agreement, ItemBlock::Company, Driver::Communication, anchor: true),
            new BankItem('communication.who', 'Sé a quién acudir cuando necesito información para mi trabajo.', ItemScale::Agreement, ItemBlock::Company, Driver::Communication),
            new BankItem('communication.from_areas', 'Recibo de otras áreas la información que necesito para hacer mi trabajo.', ItemScale::Agreement, ItemBlock::Company, Driver::Communication),
            new BankItem('communication.change', 'Cuando cambia la forma de hacer mi trabajo, entiendo qué debo hacer diferente.', ItemScale::Agreement, ItemBlock::Company, Driver::Communication, candidate: true),
            new BankItem('recognition.specific', 'Cuando obtengo buenos resultados, me explican qué hice bien.', ItemScale::Agreement, ItemBlock::Company, Driver::Recognition, anchor: true),
            new BankItem('recognition.criteria', 'Conozco los criterios que se usan para reconocer un buen trabajo.', ItemScale::Agreement, ItemBlock::Company, Driver::Recognition),
            new BankItem('growth.apply', 'Puedo poner en práctica lo nuevo que aprendo para mejorar mi trabajo.', ItemScale::Agreement, ItemBlock::Company, Driver::Growth, anchor: true),
            new BankItem('growth.path', 'Veo oportunidades de crecer dentro de esta empresa.', ItemScale::Agreement, ItemBlock::Company, Driver::Growth),
            new BankItem('autonomy.organize', 'Tengo margen para organizar mis tareas dentro de las reglas de mi puesto.', ItemScale::Agreement, ItemBlock::Company, Driver::Autonomy, anchor: true),
            new BankItem('autonomy.decisions', 'Puedo tomar las decisiones que mi puesto requiere sin pedir permiso para todo.', ItemScale::Agreement, ItemBlock::Company, Driver::Autonomy),
            new BankItem('resources.tools', 'Las herramientas que uso para trabajar funcionan bien.', ItemScale::Agreement, ItemBlock::Company, Driver::Resources, anchor: true),
            new BankItem('resources.processes', 'Los pasos que sigo para completar mis tareas están bien organizados.', ItemScale::Agreement, ItemBlock::Company, Driver::Resources),
            new BankItem('resources.staffing', 'En mi equipo tenemos suficientes personas para cumplir con las tareas.', ItemScale::Agreement, ItemBlock::Company, Driver::Resources),
            new BankItem('team.respect', 'En mi equipo nos tratamos con respeto.', ItemScale::Agreement, ItemBlock::Company, Driver::Team),
            new BankItem('team.coordination', 'Puedo coordinar mis tareas con las personas de mi equipo.', ItemScale::Agreement, ItemBlock::Company, Driver::Team, anchor: true),
            new BankItem('voice.disagree', 'Puedo expresar desacuerdos sobre el trabajo sin que eso me perjudique.', ItemScale::Agreement, ItemBlock::Company, Driver::Voice, anchor: true),
            new BankItem('voice.ideas', 'Puedo proponer mejoras relacionadas con mi trabajo.', ItemScale::Agreement, ItemBlock::Company, Driver::Voice),
            new BankItem('voice.fair_rules', 'Siento que las reglas se aplican de forma justa en mi área.', ItemScale::Agreement, ItemBlock::Company, Driver::Voice),
            new BankItem('reward.pay', 'Considero que mi sueldo corresponde a las responsabilidades de mi puesto.', ItemScale::Agreement, ItemBlock::Company, Driver::Reward, anchor: true),
            new BankItem('reward.raises', 'Conozco los criterios que se usan para dar aumentos de sueldo.', ItemScale::Agreement, ItemBlock::Company, Driver::Reward),
            new BankItem('reward.benefits', 'Las prestaciones que recibo me resultan útiles.', ItemScale::Agreement, ItemBlock::Company, Driver::Reward, candidate: true),
            new BankItem('manager.priorities', 'Mi jefe me aclara las prioridades cuando tengo varias tareas pendientes.', ItemScale::Agreement, ItemBlock::Manager, null),
            new BankItem('manager.guidance', 'Mi jefe me dice qué puedo mejorar usando ejemplos de mi trabajo.', ItemScale::Agreement, ItemBlock::Manager, null, anchor: true),
            new BankItem('manager.listening', 'Mi jefe escucha mis ideas y opiniones.', ItemScale::Agreement, ItemBlock::Manager, null),
            new BankItem('manager.respect', 'Mi jefe me trata con respeto.', ItemScale::Agreement, ItemBlock::Manager, null),
            new BankItem('manager.obstacles', 'Mi jefe me ayuda a resolver obstáculos que no puedo solucionar por mi cuenta.', ItemScale::Agreement, ItemBlock::Manager, null),
            new BankItem('manager.task_criteria', 'Mi jefe explica los criterios que usa para repartir las tareas.', ItemScale::Agreement, ItemBlock::Manager, null),
            new BankItem('manager.learning', 'Mi jefe me orienta sobre qué necesito aprender para asumir nuevas responsabilidades.', ItemScale::Agreement, ItemBlock::Manager, null),
            new BankItem('manager.followthrough', 'Mi jefe cumple los acuerdos que hace conmigo.', ItemScale::Agreement, ItemBlock::Manager, null),
            new BankItem('manager.keep', '¿Qué hace bien su jefe y debería seguir haciendo?', ItemScale::Open, ItemBlock::Manager, null),
            new BankItem('manager.change', '¿Qué podría hacer distinto su jefe para apoyar mejor al equipo?', ItemScale::Open, ItemBlock::Manager, null),
            new BankItem('loop.action', 'He visto mejoras que la empresa presentó como resultado de la encuesta anterior.', ItemScale::Agreement, ItemBlock::Closing, null),
            new BankItem('open.best', '¿Qué es lo mejor de trabajar aquí?', ItemScale::Open, ItemBlock::Closing, null),
            new BankItem('open.improve', 'Si pudiera cambiar una sola cosa de la empresa, ¿cuál sería?', ItemScale::Open, ItemBlock::Closing, null),
        ];
    }

    public static function find(string $key): BankItem
    {
        foreach (self::all() as $item) {
            if ($item->key === $key) {
                return $item;
            }
        }

        throw new InvalidArgumentException("Unknown question [{$key}].");
    }

    /** @return list<BankItem> */
    public static function closed(): array
    {
        return array_values(array_filter(self::all(), fn (BankItem $item): bool => $item->scale !== ItemScale::Open));
    }

    /** @return list<BankItem> */
    public static function open(): array
    {
        return array_values(array_filter(self::all(), fn (BankItem $item): bool => $item->scale === ItemScale::Open));
    }

    /** @return list<BankItem> */
    public static function ofDriver(Driver $driver): array
    {
        return array_values(array_filter(self::all(), fn (BankItem $item): bool => $item->driver === $driver && $item->block === ItemBlock::Company));
    }

    /** The question the pulse asks to follow a driver. */
    public static function anchor(Driver $driver): BankItem
    {
        foreach (self::ofDriver($driver) as $item) {
            if ($item->anchor) {
                return $item;
            }
        }

        throw new InvalidArgumentException("The driver [{$driver->value}] has no anchor.");
    }
}
