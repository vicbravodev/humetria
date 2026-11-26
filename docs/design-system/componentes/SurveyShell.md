Marco de la encuesta del trabajador (`components/campaigns/survey/survey-shell.tsx`; clima y pulso tienen el suyo en `components/engagement/survey/`): logo del consultor, aviso de confidencialidad, progreso por sección, instrucción, navegación y sello.

**Recibe:** `company` (el nombre de la empresa, o nada) y `children`: la página pone dentro el progreso por sección, la instrucción y una o más `SurveyQuestion`.

- Móvil: una pregunta por pantalla. Escritorio: hasta 3 preguntas de la misma sección por página.
- El progreso cuenta por sección (“Sección 1 de 8 · 1 de 9 respondidas”), no un porcentaje global que avanza lento.
- El logo de la empresa (`CompanyMark`) va arriba; Humetría solo aparece como el sello “hecho con humetría” al pie.
- Botón “Siguiente” grande y a la derecha; “Anterior” en `ghost`. Las respuestas se guardan en cada paso.
- La encuesta trata de “usted”, como las guías oficiales.
