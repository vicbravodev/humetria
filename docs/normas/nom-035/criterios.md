# Criterios de aplicación de la NOM-035-STPS-2018 en Humetría

Versión 1.2 · 30 de octubre de 2026 · Norma base: NOM-035-STPS-2018, DOF del 23 de octubre de 2018 (versión del
instrumento en el sistema: `dof-2018-10-23`).

Este documento explica **cómo aplica Humetría la NOM-035 y por qué**. Cada criterio dice qué hace la plataforma, qué
dice la norma (citada literal), en qué se basa la decisión y la fuente. Sirve para tres cosas:

1. **Sustento**: si una inspección, un cliente o un auditor pregunta por qué el sistema hace algo, aquí está la
   respuesta con su fuente.
2. **Manual**: el texto está escrito para poder explicarlo a RH o incluirlo en un manual de usuario.
3. **Cambios de la norma**: si la STPS modifica la NOM-035 o publica un criterio nuevo, se revisa criterio por
   criterio (ver "Si la norma cambia").

**Este documento manda** sobre cualquier otro del repositorio en cuanto a cómo se interpreta la NOM-035. Si el código
o un diseño lo contradicen, se corrige el código o el diseño, o se actualiza este documento con su justificación.

Fuentes (copias en [`fuentes/`](fuentes/README.md)):

- **[DOF]** NOM-035-STPS-2018, Diario Oficial de la Federación, 23/10/2018.
  <https://dof.gob.mx/nota_detalle.php?codigo=5541828&fecha=23/10/2018>
- **[STPS-1]** STPS, *Preguntas frecuentes sobre el entendimiento de la NOM-035-STPS-2018 (Parte uno)*.
  <https://trabajoseguro.stps.gob.mx/bol097/vinculos/preguntas_bol097.pdf>
- **[STPS-2]** STPS, *Preguntas frecuentes (…) (Parte dos)*.
  <https://trabajoseguro.stps.gob.mx/bol098/vinculos/preguntas_bol098.pdf>
- **[STPS-web]** STPS, *Preguntas frecuentes*, gob.mx, 05/11/2019.
  <https://www.gob.mx/stps/nom035/articulos/preguntas-frecuentes-226297>

El texto literal de las guías y sus tablas está en [`texto-de-referencia.md`](texto-de-referencia.md).

Cada criterio indica su **tipo**:
- **Norma**: lo dice la norma de forma expresa.
- **STPS**: lo dice un criterio escrito de la STPS.
- **Interpretación**: la norma no lo resuelve y Humetría adopta la lectura más apegada a su texto.
- **Humetría**: va más allá de la norma, normalmente para proteger a las personas.

---

## A. Alcance

### C-01. Se evalúa por centro de trabajo y cada empresa (razón social) por separado · Norma + STPS

**Qué hacemos.** Cada evaluación corresponde a un centro de trabajo de una empresa. Si dos razones sociales comparten
instalaciones, cada una es una empresa distinta en Humetría y evalúa a su propia gente.

**Qué dice la norma.** El campo de aplicación se define por «centros de trabajo en los que laboren» cierto número de
trabajadores (num. 2). Centro de trabajo es «el lugar o lugares, tales como edificios, locales, instalaciones y áreas,
donde se realicen actividades (…) en los que laboren personas que estén sujetas a una relación de trabajo» (num. 4.4).

**Criterio STPS.** «Cada razón social debe presentar su propia documentación correspondiente al cumplimiento con la
Norma (…) en virtud de que cada una de éstas es una empresa diferente (…) aun cuando dos o más organizaciones laboren
en el mismo centro de trabajo.» [STPS-1, pregunta 9]

### C-02. Qué guías aplican según el número de trabajadores · Norma

**Qué hacemos.**

| Trabajadores del centro | Guías |
|---|---|
| 1 a 15 | Guía I |
| 16 a 50 | Guía I y Guía II |
| 51 o más | Guía I y Guía III |

**Qué dice la norma.** Num. 2 a), b) y c): hasta quince; «entre dieciséis y cincuenta»; «más de 50». Num. 7.1 a) y b).
Guía II: «puede ser utilizada por aquellos centros de trabajo que tengan hasta 50 trabajadores». Guía III: «por
aquellos centros de trabajo que cuenten con más de 50 trabajadores». [DOF]

**Nota para clientes.** Las Guías de referencia son, según la propia norma, «un complemento (…) y no de cumplimiento
obligatorio»: un centro puede usar otro método validado (num. 7.4 y 7.5). Pero la norma establece que quien demuestra
usar el método de la Guía II o III da por cumplidos los numerales 7.1, 7.2, 7.4 y 7.5 (y 7.3 con la Guía III) [DOF,
num. 10.2]. Por eso Humetría aplica las guías **sin modificarlas**.

### C-03. Cómo se cuentan los trabajadores · Interpretación

**Qué hacemos.** Contamos a las personas **activas** en la plantilla del centro de trabajo **al momento de lanzar** la
evaluación. Ese número queda fijo en la campaña y decide las guías y, en su caso, el tamaño de la muestra. Antes de
lanzar, el sistema recuerda a RH que la plantilla debe estar completa.

**Por qué.** La norma cuenta a quienes «laboren» en el centro (num. 2) y no define otra regla de conteo. Fijar el
número al lanzar evita que una alta o baja a mitad de la evaluación cambie las guías que ya se están aplicando.

**Responsabilidad del cliente.** Que la plantilla cargada en Humetría refleje a todas las personas que trabajan en el
centro.

---

## B. Guía de referencia I (acontecimientos traumáticos severos)

### C-04. La Guía I se aplica a todas las personas en cada evaluación · STPS

**Qué hacemos.** Toda evaluación NOM-035 incluye la Guía I para todas las personas del centro, sin importar su
tamaño.

**Criterio STPS.** Hay dos casos: si el centro tiene información confiable sobre quién estuvo expuesto, «puede
aplicar la Guía de referencia I, únicamente a los trabajadores previamente identificados»; si no la tiene «o ésta no
sea confiable, tendrá que aplicar la Guía de referencia I, a todos los trabajadores». [STPS-web] Humetría aplica la
opción que siempre cumple: a todos.

### C-05. Guía I cuando ocurre un acontecimiento · STPS

**Qué hacemos.** Además de la evaluación, RH puede lanzar en cualquier momento una campaña de **solo Guía I** para las
personas que sufrieron o presenciaron un acontecimiento.

**Criterio STPS.** «Cada vez que alguno de los trabajadores sufre o presencia un acontecimiento traumático severo,
durante o con motivo del trabajo (incluye los accidentes y actos violentos que se presenten en el trayecto directo
entre domicilio y centro de trabajo), debe aplicarse el cuestionario de la Guía de referencia I y, en su caso,
canalizársele para su atención médica o psicológica.» [STPS-1, pregunta 19; STPS-2, pregunta 9]

### C-06. Regla de valoración clínica · Norma

**Qué hacemos.** Si todas las respuestas de la Sección I son "No", el cuestionario termina ahí y no se requiere
valoración. Si alguna es "Sí", la persona contesta las Secciones II, III y IV, y **requiere valoración clínica** si
contesta "Sí" en: al menos 1 pregunta de la Sección II, o al menos 3 de la Sección III, o al menos 2 de la Sección IV.

**Qué dice la norma.** Guía I, numeral GR.I a) y b) (texto literal en el anexo). [DOF]

**Nota.** La Sección I tiene seis acontecimientos y cada uno se contesta "Sí" o "No". Así lo pide GR.I a) al hablar
de «todas las respuestas a la Sección I».

### C-07. La Guía I es identificada · Norma

**Qué hacemos.** La respuesta de Guía I queda ligada a la persona. Antes de contestarla se le explica que esta parte
lleva su nombre porque la empresa está obligada a canalizarla a atención si lo necesita, y que solo la verán personas
autorizadas.

**Por qué.** La obligación es «identificar a los trabajadores que fueron sujetos a acontecimientos traumáticos severos
(…) y canalizarlos para su atención» (num. 5.5), y llevar registro de sus nombres (num. 5.8 c). Eso no se puede hacer
de forma anónima.

---

## C. Guías de referencia II y III (factores de riesgo psicosocial y entorno organizacional)

### C-08. Guía II: a todas las personas · Norma

**Qué hacemos.** En centros de 16 a 50 trabajadores, la Guía II se aplica a todos. No hay opción de muestra.

**Qué dice la norma.** «El cuestionario deberá aplicarse a todos los trabajadores del centro de trabajo» (II.1). Num.
7.1 a): «incluyendo a todos los trabajadores».

### C-09. Guía III: a todos o a una muestra representativa · Norma + Interpretación

**Qué hacemos.** RH elige aplicar la Guía III a todas las personas (opción por defecto) o a una muestra. El tamaño de
la muestra es

> n = 0.9604 · N / (0.0025 · (N − 1) + 0.9604)

**redondeado hacia arriba**. El sistema lo calcula con números enteros, ⌈9604·N / (25·(N − 1) + 9604)⌉, para que no
haya error de redondeo. Ejemplos: N = 51 → 46; N = 100 → 80; N = 500 → 218.

**Qué dice la norma.** III.1: «se podrá aplicar a una muestra representativa (…) conforme a la Ecuación 1», con el
ejemplo N = 100 → 80 (el cálculo exacto da 79.5). III.2 a) 1): «Determinar el número mínimo de trabajadores a los que
se les aplicarán los cuestionarios que **al menos** deberá corresponder con el tamaño de la muestra».

**Por qué hacia arriba (interpretación).** La norma habla de un mínimo que debe cumplirse «al menos»; redondear hacia
abajo podría quedar por debajo del mínimo (N = 51 da 45.13: con 45 no se alcanza). Su propio ejemplo también sube
(79.5 → 80).

**Por qué "todos" por defecto (Humetría).** Cuesta lo mismo en una plataforma en línea, da un diagnóstico más
completo y hace más grandes los grupos, lo que protege mejor el anonimato.

### C-10. Selección de la muestra · Norma + Interpretación

**Qué hacemos.** El sorteo es aleatorio entre todas las personas activas del centro y se reparte por género en
proporción a la plantilla. Cada género recibe su parte entera y los lugares que sobran van a los géneros con mayor
fracción, así que el total es exactamente n. Si alguien seleccionado no contestará, RH puede sortear reemplazos del
mismo género entre quienes no están en la muestra.

**Qué dice la norma.** III.1: «La selección de los trabajadores deberá realizarse de forma aleatoria, de manera que
todos los trabajadores puedan ser considerados (…) sin importar su turno, tipo de puesto, área de trabajo,
departamento, etc. La muestra se distribuirá entre hombres y mujeres conforme al porcentaje de cada género en el centro
de trabajo.»

**Interpretación.** Las personas registradas con género "Otro / prefiero no decir" forman un grupo más del reparto,
para que todas puedan ser consideradas, como pide la norma.

### C-11. Escala de respuestas · Norma

**Qué hacemos.** Cinco opciones: Siempre, Casi siempre, Algunas veces, Casi nunca, Nunca. Unos ítems valen 0 a 4 y
otros 4 a 0, exactamente como las Tablas 2 (Guía II) y 5 (Guía III).

**Nota.** El ítem 33 de la Guía II y el 57 de la Guía III («En mi trabajo puedo expresarme libremente sin
interrupciones») valen 0 a 4 y forman parte del dominio Violencia. Así lo establece la norma y así se aplica.

### C-12. Preguntas filtro (clientes o usuarios, y ser jefe) · Interpretación

**Qué hacemos.** Si la persona contesta "No" a «En mi trabajo debo brindar servicio a clientes o usuarios» o a «Soy
jefe de otros trabajadores», no se le hacen las preguntas de ese bloque y esos ítems valen 0 en su calificación. Si
contesta "Sí", tiene que contestar todo el bloque.

**Qué dice la norma.** «Si su respuesta fue "NO" pase a las preguntas de la sección siguiente» y «Si su respuesta fue
"NO", ha concluido el cuestionario». La calificación «se obtiene sumando el puntaje de todos y cada uno de los ítems
que integran el cuestionario» (II.3 b, III.3 b).

**Interpretación.** Un ítem que la norma ordena no contestar no aporta puntaje. Es la única lectura compatible con
sumar los ítems contestados.

### C-13. Rangos de riesgo: el límite inferior pertenece al nivel · Norma (verificado en el original)

**Qué hacemos.** Cada nivel incluye su límite inferior y excluye el superior. En la Guía II, por ejemplo: Nulo si
Cfinal < 20; Bajo si 20 ≤ Cfinal < 45; Medio si 45 ≤ Cfinal < 70; Alto si 70 ≤ Cfinal < 90; Muy alto si Cfinal ≥ 90.
Una calificación exactamente en el límite (20, 45, 70, 90) siempre tiene nivel.

**Qué dice la norma.** El texto HTML del DOF muestra «20<Cfinal<45», lo que dejaría sin nivel una calificación de
exactamente 20. Pero en el original, el primer «<» de cada rango y el «>» de "Muy alto" están **subrayados**, la
convención tipográfica de «≤» y «≥». Lo verificamos en el código fuente de la publicación oficial: los 116 operadores
de las tablas de rangos de II.3 y III.3 llevan subrayado. Evidencia:
[`fuentes/evidencia-rangos-subrayados.html`](fuentes/evidencia-rangos-subrayados.html).

**Importancia.** Herramientas que leen el texto plano del DOF pierden el subrayado y clasifican mal los casos que caen
justo en el límite.

### C-14. Se califica cada cuestionario; el resultado del centro no es un promedio · STPS

**Qué hacemos.** Cada cuestionario se califica por sí solo: calificación final, por categoría y por dominio, cada una
con su nivel de riesgo. El resultado del centro se presenta como **cuántas personas quedan en cada nivel de riesgo**,
nunca como un promedio ni como una suma.

**Criterio STPS.** «Los resultados que se obtienen al aplicar el cuestionario de la Guía de referencia III (…) en
cuanto a la calificación del dominio, la categoría y la final del cuestionario, **no se promedian, ni se suman ni se
generalizan en alguna forma**. Debe analizarse cada respuesta, de cada uno de los cuestionarios aplicados (…) Los casos
que caigan en niveles de riesgo Medio, Alto y Muy alto, deben ser atendidos a través del Programa de acciones de
control referido en los numerales 8.3, 8.4 y 8.5 de la Norma.» [STPS-1, pregunta 12; STPS-2, pregunta 2]. El mismo
procedimiento se describe para la Guía II [STPS-2, pregunta 8].

**Importancia.** Un promedio esconde a las personas en riesgo alto detrás de una mayoría en riesgo bajo, y es
precisamente lo que la STPS dice que no se haga.

### C-15. Acciones según el nivel de riesgo · Norma

**Qué hacemos.** Cada nivel lleva la «necesidad de acción» de las Tablas 4 y 7 de la norma. Medio, Alto y Muy alto
requieren un Programa de intervención.

**Qué dice la norma.** II.4 y III.4, Tablas 4 y 7 (texto en el anexo).

---

## D. Aplicación de los cuestionarios

### C-16. Aplicación en línea y evidencia electrónica · Norma

**Qué hacemos.** Cada persona contesta desde su celular o computadora con un enlace personal. Los registros se
conservan en medios electrónicos.

**Qué dice la norma.** La norma no exige papel ni aplicación presencial. «Las evidencias de tipo documental o los
registros a que alude esta Norma podrán exhibirse de manera impresa o en medios electrónicos, y se deberán conservar
al menos durante un año, a partir de la fecha de elaboración» (num. 10.4).

### C-17. Presentación e instrucciones antes de contestar · Norma (adaptada a la aplicación en línea)

**Qué hacemos.** Antes del cuestionario, la persona ve:

- el objetivo de la evaluación;
- que sus datos son confidenciales y que la información se usa solo para mejorar el ambiente de trabajo;
- que no hay respuestas correctas ni incorrectas;
- que debe contestar todo, con concentración y sinceridad;
- que **considere las condiciones de los dos últimos meses**.

La encuesta nunca avanza sola ni sugiere respuestas.

**Qué dice la norma.** II.2 y III.2 a) 3) a 6) y b) 6): «evitar conducir, persuadir o dirigir respuestas». Las
condiciones propias de una aplicación presencial (instalaciones y mobiliario, presentación del aplicador, aclarar
dudas en el momento) se cubren con instrucciones escritas, una interfaz accesible y un contacto de RH.

### C-18. Solo cuenta un cuestionario completo, y no se modifica · Norma

**Qué hacemos.** Cada guía se envía una sola vez, completa. El sistema rechaza un cuestionario incompleto o
incoherente (por ejemplo, un "No" en una pregunta filtro con el bloque contestado). Una vez enviado, no se puede
cambiar, y el enlace deja de servir para esa guía.

**Qué dice la norma.** II.2 c) y III.2 c): «verificar que hayan sido respondidos completamente, sin tachaduras o
enmendaduras» y «comprobar que la cantidad de cuestionarios entregados corresponda con la cantidad de cuestionarios
respondidos, y que a su vez corresponda con el tamaño mínimo de la muestra». El avance de la campaña muestra esa
comparación y avisa mientras la muestra no se alcance.

### C-19. Orden de las guías · Interpretación

**Qué hacemos.** La encuesta ofrece primero la Guía I y después la Guía II o III, pero cada guía se puede contestar
por separado.

**Por qué.** La norma ordena aplicar el cuestionario antes de la ficha de datos (II.2 a 6, III.2 a 7), pero no fija un
orden entre la Guía I y las Guías II o III. Si una no se pudiera contestar sin la otra, quien no quisiera contestar la
parte con su nombre dejaría también la anónima, y eso puede dejar corta la muestra.

### C-20. Sin ficha de datos del trabajador (Guía V) · Norma + Humetría

**Qué hacemos.** No se pide la ficha de la Guía V (edad, estado civil, escolaridad, tipo de puesto, etc.).

**Qué dice la norma.** La Guía V «no es de cumplimiento obligatorio», y la ficha se llena «en su caso» (II.2 a 6).

**Por qué (Humetría).** Cruzar edad, escolaridad y puesto con respuestas anónimas puede identificar a una persona en
grupos pequeños. Para segmentar resultados basta el centro y el área.

### C-21. Textos literales · Norma

**Qué hacemos.** Las preguntas, instrucciones y opciones se presentan **tal como están en el DOF**, sin reescribirlas.
Solo se corrigen erratas en etiquetas internas («Escaza claridad de funciones» → «Escasa»; «9, 10. 11» → «9, 10,
11»; «Insuficiente sentido de pertenencia e, inestabilidad» → «… e inestabilidad»). El texto que ve la persona no cambia.

**Por qué.** Modificar la redacción de una guía la convierte en un cuestionario propio, que la norma exige validar
(num. 7.5).

---

## E. Confidencialidad (más allá de la norma)

### C-22. Las respuestas de las Guías II y III son anónimas · Humetría (sustentado en la norma)

**Qué hacemos.** Las respuestas de las Guías II y III se guardan sin ninguna relación con la persona: no llevan su
nombre ni su identificador, ni el de su invitación, ni la hora en que contestó. Solo llevan la campaña, el centro y el
área. RH ve quién ya contestó, pero nunca qué contestó.

**Sustento.** La norma pide «enfatizar en la protección de la privacidad y confidencialidad del manejo de los datos»
(II.2 a 4, III.2 a 5). Humetría lo lleva a anonimato técnico: ni la empresa ni la plataforma pueden saber qué contestó
cada persona.

### C-23. Mínimo de personas por grupo en los resultados · Humetría

**Qué hacemos.** Ningún resultado se muestra para un grupo (área, filtro) con menos de 5 respuestas. El mínimo se
aplica después de cada filtro.

**Por qué.** Con grupos muy pequeños, un resultado "agregado" revela la respuesta de una persona. La norma no lo exige;
es protección adicional de Humetría, coherente con C-22.

Cómo se aplica en los resultados:

- **Áreas, con supresión complementaria.** Un área se muestra si tiene al menos 5 cuestionarios **y** lo que queda
  oculto suma 0 o al menos 5; si no, se ocultan también las áreas visibles más chicas hasta cumplirlo. Sin esto, «el
  centro menos las áreas visibles» revelaría al grupo pequeño.
- **Preguntas.** Cada pregunta se muestra solo si la contestaron al menos 5 (las de los bloques filtro las contesta una
  parte), y solo para el centro completo: por área, la resta entre el centro y el área revelaría a los demás.
- **Dimensiones.** No se muestran: la norma no les da niveles, y sumarlas es lo que la STPS prohíbe (C-14).

### C-27. Los resultados de las Guías II y III solo existen con la evaluación cerrada · Humetría

**Qué hacemos.** Mientras la evaluación está abierta, RH ve quién ya contestó (C-22), pero ningún resultado de las
Guías II y III. Los resultados aparecen al cerrarla y ya no cambian.

**Por qué.** Si RH viera los resultados moverse con cada respuesta, sabría qué contestó la última persona que marcó
«ya contestó». La Guía I no espera: es identificada a propósito y la canalización (num. 5.5) no debe demorarse.

### C-28. Lista de canalización de la Guía I · Norma

**Qué hacemos.** Owner y RH ven quiénes requieren valoración clínica, con su área y el día en que contestaron. El rol
de consulta no la ve, ni contada. Cada consulta de la lista queda en la bitácora.

**Qué dice la norma.** Identificar a los trabajadores que fueron sujetos a acontecimientos traumáticos severos y
canalizarlos para su atención (num. 5.5); llevar el registro de sus nombres (num. 5.8 c).

### C-29. Informe del numeral 7.7 y enlace temporal · Norma + Humetría

**Qué hacemos.** El informe se arma con lo que la plataforma sabe: datos del centro (a), método (d) y resultados (e).
RH captura objetivo (b), actividades (c), conclusiones (f), recomendaciones y acciones de intervención (g) y el
responsable con su cédula, «en su caso» (h). Se imprime o se guarda como PDF desde el navegador.

Para que alguien sin cuenta lo lea (la dirección de la empresa, un auditor), quien gestiona evaluaciones crea un
**enlace temporal** de 15 minutos a 48 horas. Solo se guarda el hash del enlace; se muestra una vez. Se puede revocar,
cada apertura queda en la bitácora y nunca incluye la Guía I. Un enlace vencido, revocado o de una empresa bloqueada no
abre nada ni nombra a la empresa.

**Qué dice la norma.** «El resultado (…) deberá constar en un informe que contenga lo siguiente: a) Datos del centro de
trabajo verificado (…) h) Datos del responsable de la evaluación» (num. 7.7). El informe es también lo que se pone a
disposición de los trabajadores (num. 7.8).

---

## F. Durante la evaluación

### C-24. Cambios en la plantilla mientras la evaluación está abierta · Humetría

**Qué hacemos.**

- **Baja o cambio de centro de trabajo**: lo que la persona tenía pendiente se cancela y su enlace deja de servir. Lo
  que ya contestó se conserva.
- **Reactivación**: RH puede volver a invitarla.
- **Nuevas altas**: RH puede invitarlas. Reciben la Guía I y, si la evaluación es para todos, la Guía II o III. Nunca
  entran a una muestra fuera del sorteo.
- **Centro de trabajo con evaluación abierta**: no se puede archivar.

**Por qué.** La evaluación debe reflejar a quienes laboran en el centro (num. 2), y una muestra solo es válida si se
sorteó (III.1).

### C-25. Cierre de la evaluación · Humetría

**Qué hacemos.** La evaluación tiene fecha de cierre, que puede extenderse mientras esté abierta. Al cerrar, ningún
enlace funciona. Se puede cerrar con la muestra sin cubrir, pero el sistema lo advierte, porque la norma pide que los
cuestionarios respondidos correspondan «con el tamaño mínimo de la muestra» (III.2 c 2).

### C-26. Periodicidad · Norma

**Qué hacemos.** Para cada centro de trabajo mostramos la fecha de su última evaluación y la fecha límite de la
siguiente (dos años después).

**Qué dice la norma.** «La identificación y análisis de los factores de riesgo psicosocial y la evaluación del entorno
organizacional deberá realizarse, al menos, cada dos años» (num. 7.9).

---

## Cómo se prueba

Cada criterio que la plataforma aplica tiene pruebas automáticas que fallan si deja de cumplirse. Las de calificación
están escritas a partir del anexo, no del código: son una segunda transcripción independiente de las tablas.

| Criterio | Pruebas |
|---|---|
| C-01, C-02, C-03 | `tests/Feature/Instruments/Nom035GuidesTest.php`, `tests/Feature/Campaigns/CreateEvaluationTest.php` (15/16, 50/51, plantilla activa) |
| C-04 | `tests/Feature/Campaigns/LaunchCampaignTest.php` (Guía I a todos) |
| C-05 | `tests/Feature/Campaigns/TraumaCampaignTest.php`, `tests/Browser/CampaignsTest.php` |
| C-06 | `tests/Feature/Instruments/TraumaScreeningTest.php` (cada umbral de GR.I) |
| C-07 | `tests/Feature/Campaigns/SubmitSurveyTest.php` (tamizaje con la persona) |
| C-08, C-09 | `tests/Feature/Instruments/Nom035SampleTest.php` (N = 51, 52, 100, 500, 1000, 10000; nunca n > N), `tests/Feature/Campaigns/CreateEvaluationTest.php` |
| C-10 | `tests/Feature/Instruments/Nom035SampleTest.php` (reparto), `tests/Feature/Campaigns/CreateEvaluationTest.php` (sorteo aleatorio y proporcional), `tests/Feature/Campaigns/LaunchCampaignTest.php` (no se lanza una muestra desactualizada), `tests/Feature/Campaigns/CampaignActionsTest.php` (reemplazos) |
| C-11, C-12, C-13, C-15 | `tests/Feature/Instruments/RiskScoringTest.php` (escala de cada ítem, bloques filtro en 0, cada límite de cada rango en c − 1, c y c + 1) |
| C-14 | `tests/Feature/Instruments/RiskScoringTest.php` (cada cuestionario por sí solo) |
| C-16, C-17 | `tests/Feature/Campaigns/OpenSurveyTest.php`, `tests/Browser/SurveyTest.php` (presentación, «dos últimos meses») |
| C-18 | `tests/Feature/Instruments/AnswerValidationTest.php`, `tests/Feature/Campaigns/SubmitSurveyTest.php` (completo, coherente, una sola vez) |
| C-19 | `tests/Feature/Campaigns/SubmitSurveyTest.php` (guías en cualquier orden) |
| C-21 | `tests/Feature/Instruments/Nom035DefinitionsTest.php` (cada pregunta igual al anexo) |
| C-22 | `tests/Feature/Responses/ResponseStorageTest.php` (sin columnas ni llaves hacia la persona, UUID v4), `tests/Feature/Campaigns/SubmitSurveyTest.php` (nada en el log), `tests/Feature/Campaigns/CampaignPagesTest.php` (ninguna respuesta en las páginas de RH) |
| C-24 | `tests/Feature/Campaigns/RosterChangesTest.php`, `tests/Feature/Campaigns/CampaignActionsTest.php`, `tests/Feature/Tenancy/ArchiveWorkCenterTest.php` |
| C-25 | `tests/Feature/Campaigns/CloseCampaignTest.php` |
| C-26 | `tests/Feature/Campaigns/CampaignPagesTest.php` (última evaluación y fecha límite) |
| C-14, C-15, C-23 | `tests/Feature/Results/RiskDistributionTest.php` (conteo por nivel, 4/5, supresión complementaria, preguntas con menos de 5), `tests/Feature/Results/ResultsPageTest.php` (filtro por área, ninguna respuesta en las props, número de consultas) |
| C-27 | `tests/Feature/Results/ResultsPageTest.php` (sin resultados con la evaluación abierta) |
| C-28 | `tests/Feature/Results/ScreeningsPageTest.php` (solo owner y RH, bitácora), `tests/Feature/Results/ResultsPageTest.php` (consulta no la ve) |
| C-29 | `tests/Feature/Results/ReportTest.php` (incisos a–h), `tests/Feature/Results/ReportSharesTest.php` (15 min a 48 h, hash, vencido, revocado, empresa bloqueada, sin Guía I) |

C-20 (sin ficha de la Guía V) se cumple por ausencia.

## Lo que la plataforma no cubre (por ahora)

Obligaciones de la norma que dependen del cliente o que Humetría todavía no gestiona:

- política de prevención (5.1, Guía IV);
- difusión de información (5.7);
- programa de intervención y medidas de control (8.3 a 8.5);
- exámenes médicos y evaluaciones psicológicas (5.6);
- mecanismo de quejas (8.1 b);
- integración al diagnóstico de seguridad y salud de la NOM-030 (7.6).

La consulta de los trabajadores (7.8) se cumple con el informe impreso o publicado por la empresa: no hay todavía un
acceso propio para los trabajadores.

## Si la norma cambia

1. **El instrumento está versionado.** Cada evaluación guarda la versión con la que se aplicó (`dof-2018-10-23`), y
   sus respuestas se califican siempre con esa versión. Una norma nueva es una versión nueva; las evaluaciones
   anteriores no cambian.
2. **Se revisa cada criterio.** Para cada uno: ¿cambió el texto citado? Si es "Interpretación", ¿la nueva norma o un
   criterio STPS lo resuelve de otra forma? Los criterios afectados se actualizan aquí con su nueva fuente, se sube la
   versión de este documento y se agrega una entrada al historial.
3. **Se guarda la fuente nueva** en [`fuentes/`](fuentes/README.md), con fecha de descarga y huella SHA-256.
4. **Las pruebas del instrumento** se escriben contra el anexo, así que un cambio en las tablas obliga a cambiar
   primero el anexo y las pruebas, y después el código.

## Historial

| Versión | Fecha | Cambio |
|---|---|---|
| 1 | 2026-10-29 | Primera versión, con base en el DOF del 23/10/2018 y las preguntas frecuentes de la STPS (partes uno y dos). |
| 1.1 | 2026-10-29 | Se agrega «Cómo se prueba» y la errata «pertenencia e, inestabilidad» a C-21. Ningún criterio cambia. |
| 1.2 | 2026-10-30 | Resultados: C-23 detalla la supresión complementaria y el mínimo por pregunta; nuevos C-27 (resultados solo con la evaluación cerrada), C-28 (lista de canalización) y C-29 (informe 7.7 y enlace temporal). |
