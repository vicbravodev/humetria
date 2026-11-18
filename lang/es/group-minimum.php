<?php

declare(strict_types=1);

/*
 * What a person who answers, and RH, are told about the minimum of people per group (criterio C-23), read with
 * trans_choice() and the minimum. With 1 the company set no minimum: no text may promise that a small group is hidden.
 */

$none = 'Su empresa no fijó un mínimo de personas por grupo: el resultado de un grupo muy pequeño podría permitir identificar sus respuestas.';

return [
    // To whoever answers (the survey speaks "usted").
    'notice_none' => $none,
    'notice' => "{1} {$none}|[2,*] Recursos Humanos solo recibe resultados de grupos de :minimum o más personas.",

    // To the owner, when an evaluation closes without risk levels to show.
    'hidden' => '{1} No se recibieron cuestionarios, así que no hay niveles de riesgo que mostrar.|[2,*] Con menos de :minimum cuestionarios no se muestran niveles de riesgo, para cuidar el anonimato.',

    // To RH (the panel tutea), before it publishes the results of an evaluation to its people.
    'publish' => '{1} Verán cuántas personas contestaron y los temas que más atención necesitan, del centro completo y nunca por área. Tu empresa no fijó un mínimo de personas por grupo: el resultado de un grupo muy pequeño podría permitir identificar respuestas.|[2,*] Verán cuántas personas contestaron y los temas que más atención necesitan; nunca datos de áreas o grupos con menos de :minimum personas.',

    // In the method of the 7.7 report.
    'report' => '{1} La empresa no fijó un mínimo de cuestionarios por grupo: se muestran los resultados de todos los grupos, y el de un grupo muy pequeño podría permitir identificar respuestas.|[2,*] Los resultados de grupos con menos de :minimum cuestionarios no se muestran, para proteger el anonimato de las respuestas.',
];
