import type { ComponentType } from 'react';
import HorasExtraSemana from '@/components/blog/diagrams/horas-extra-semana';
import JornadaEscalera from '@/components/blog/diagrams/jornada-escalera';
import Nom035GuiasPorCentro from '@/components/blog/diagrams/nom035-guias-por-centro';
import Nom035PasoAPaso from '@/components/blog/diagrams/nom035-paso-a-paso';
import RegistroDiezPasos from '@/components/blog/diagrams/registro-diez-pasos';
import TopesHorasExtra from '@/components/blog/diagrams/topes-horas-extra';
import VacacionesPorAntiguedad from '@/components/blog/diagrams/vacaciones-por-antiguedad';

/**
 * The diagrams an article can ask for with `[diagrama: name]`, by name. Each name is also the file of the component:
 * tests/Unit/Blog/PublishedContentTest.php checks that every diagram the articles use has its file.
 */
export const DIAGRAMS: Record<string, ComponentType> = {
    'horas-extra-semana': HorasExtraSemana,
    'jornada-escalera': JornadaEscalera,
    'nom035-guias-por-centro': Nom035GuiasPorCentro,
    'nom035-paso-a-paso': Nom035PasoAPaso,
    'registro-diez-pasos': RegistroDiezPasos,
    'topes-horas-extra': TopesHorasExtra,
    'vacaciones-por-antiguedad': VacacionesPorAntiguedad,
};
