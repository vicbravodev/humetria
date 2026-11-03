# Criterios de aplicación de la NOM-035-STPS-2018 en Humetría

Versión 1.6 · 3 de noviembre de 2026 · Norma base: NOM-035-STPS-2018, DOF del 23 de octubre de 2018 (versión del
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

**Quién «contestó».** Contestó quien terminó la guía que mide la evaluación (la II o la III; la I si es la única).
El medidor, las pestañas por estado, la participación por área y las respuestas por día cuentan lo mismo y dan el
mismo número. La Guía I se sigue con el filtro de guía; «Todas las guías» pide a quien terminó cada una.

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

Por cada persona registran la constancia de su canalización: **canalizada** o **atendida** (sin registro, sigue
**pendiente**), el día y la institución, sin diagnóstico ni notas. Cada cambio queda en la bitácora con quién lo hizo y
el estado; el log solo dice que se guardó un registro, sin la persona, el estado ni la institución. El registro vive
en la misma empresa y apunta a la Guía I (identificada a propósito), nunca a una respuesta de las Guías II o III.

**Qué dice la norma.** Identificar a los trabajadores que fueron sujetos a acontecimientos traumáticos severos y
canalizarlos para su atención (num. 5.5); llevar el registro de sus nombres (num. 5.8 c).

### C-29. Informe del numeral 7.7 y enlace temporal · Norma + Humetría

**Qué hacemos.** El informe se arma con lo que la plataforma sabe: datos del centro (a), método (d) y resultados (e).
RH captura objetivo (b), actividades (c), conclusiones (f), recomendaciones (g) y el responsable con su cédula, «en su
caso» (h); las acciones de intervención del inciso g se imprimen del Programa (C-30 a C-36), tal como está el día en que
se abre el informe. Se imprime o se guarda como PDF desde el navegador.

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

## G. Programa de intervención

### C-30. Un Programa por evaluación cerrada; una fila por categoría, dominio y calificación final en Medio o más · Norma + STPS + Interpretación

**Qué hacemos.** Cuando una evaluación con Guía II o III se cierra, el sistema redacta un **borrador** del Programa de
intervención con una fila por cada lugar donde al menos un cuestionario cayó en Medio, Alto o Muy alto: la calificación
final, cada categoría y cada dominio. La fila guarda el nivel más alto presente, no cifras: las cifras se leen de
Resultados, con el mínimo vigente (C-23). Si ningún cuestionario quedó en Medio o más, no se crea Programa y la página
dice que la norma no pide uno. Con menos cuestionarios que el mínimo no se deriva nada: el Programa nace sin filas y RH
agrega las que quiera, de la lista de la guía (esas filas no llevan nivel ni cifras y no cuentan para C-34). Un Programa
por evaluación, nunca copiado: en cada ciclo (C-26) se hace otro.

**Qué dice la norma.** «Si los resultados de las evaluaciones (…) determinan la necesidad de acciones de control, se
deberán implementar a través de un Programa de intervención que cumpla con el numeral 8.4» (num. 8.3). Las Tablas 4 y 7
piden el Programa en Medio, Alto y Muy alto. La STPS: «los casos que caigan en niveles de riesgo Medio, Alto y Muy alto
deben ser atendidos a través del Programa de acciones de control» [STPS-1, pregunta 12; STPS-2, preguntas 2 y 8].

**Interpretación.** El DOF no dice si la regla va por el nivel final o por cada grupo, y la STPS menciona los tres
(«calificación del dominio, la categoría y la final»): tomamos el nivel más alto presente en cualquiera de ellos.

### C-31. Contenido de cada acción · Norma

**Qué hacemos.** Cada acción lleva los seis puntos del numeral 8.4: las áreas o personas sujetas (a; ver C-33), el tipo de
acción y la medida (b), las fechas programadas (c: inicio opcional y fecha límite obligatoria), el control de avances (d;
ver C-35), la evaluación posterior «en su caso» (e: por fila, como nota con fecha, sin comparar automáticamente contra la
evaluación anterior) y el responsable de su ejecución (f: un miembro de la empresa, que recibe los recordatorios, o un
nombre). El **catálogo de tipos de acción es de Humetría**, no del DOF (sale de los textos de las Tablas 4 y 7 y de 8.1,
8.2 y 8.5) y así se rotula en pantalla. La acción se puede copiar a otras filas como acciones independientes.

**Qué dice la norma.** «El programa de intervención deberá contener, al menos: a) Las áreas de trabajo y/o los
trabajadores sujetos al programa; b) El tipo de acciones y las medidas de control que deberán adoptarse; c) Las fechas
programadas para su realización; d) El control de los avances de la implementación del programa; e) La evaluación
posterior a la aplicación de las medidas de control, en su caso; y f) El responsable de su ejecución» (num. 8.4).

**Interpretación.** En la tabla 10.2, el texto «Esta disposición (numeral 8.5) sólo aplica para aquellos centros de
trabajo que desarrollen y utilicen métodos diferentes a los contenidos en la Guía de referencia III» está impreso con
«8.5», pero se refiere al 7.5 (validación de métodos propios). Lo leemos como errata: el 8.5 aplica a todo Programa.

### C-32. Niveles 1, 2 y 3 · Norma

**Qué hacemos.** Cada acción es de nivel 1 (organizacional), 2 (grupal) o 3 (individual). El tercero es **atención clínica
o terapéutica** y exige declarar quién la da: médico, psicólogo o psiquiatra; no hay otro tipo en el nivel 3 ni la
atención clínica en otro nivel. Se redacta como **servicio disponible** («convenio con institución, atención psicológica a
solicitud»), nunca como «a quién»: la atención de cada persona se registra en la canalización (C-28).

**Qué dice la norma.** Acciones «según aplique» en tres niveles: primero, organizacional (política de prevención,
organización del trabajo); segundo, grupal (información, sensibilización, apoyo social); tercero, individual, cuando hay
signos o síntomas, con intervenciones clínicas o terapéuticas, que «deberán ser realizadas invariablemente por un médico,
psicólogo o psiquiatra según corresponda» (num. 8.5).

### C-33. Sujetos: el centro o áreas visibles, nunca personas · Humetría (sustentado en 8.4 a y C-22)

**Qué hacemos.** Una acción es para **todo el centro de trabajo** o para **áreas**, y solo de las que Resultados muestra
(con el mínimo y la supresión complementaria de C-23). Un área oculta, inexistente o de otra empresa se rechaza con el
mismo mensaje. El panel muestra, junto a cada área y para esa fila, cuántos cuestionarios del área están en Medio o más
(lo mismo que Resultados al filtrar por esa área). No hay selector de personas ni ninguna tabla del Programa con llave a
empleado, invitación o token. En el informe solo se nombran las áreas que se muestran hoy, y ni cuántas quedan ocultas.

**Qué dice la norma.** El numeral 8.4 a pide «las áreas de trabajo y/o los trabajadores sujetos al programa»: el «y/o»
permite cumplirlo con áreas. **Por qué.** Elegir a las personas de una acción por un resultado de la Guía II o III las
ligaría con su respuesta (C-22).

### C-34. Qué pide la Tabla según el nivel más alto · Norma (II.4, III.4) + Interpretación

**Qué hacemos.** Con el nivel más alto del resultado, la página muestra la lista **«Lo que pide la Tabla»** con Cubierto,
Falta u Opcional:

- desde Medio: revisar la política de prevención y los programas, y reforzar su aplicación y difusión (dos líneas);
- desde Alto: el análisis de cada categoría y dominio (cubierto cuando cada fila en Alto o Muy alto lo tiene escrito) y una
  campaña de sensibilización;
- en Muy alto: evaluaciones específicas; en Alto, la Tabla dice que el Programa «podrá incluir» una, así que se muestra
  como opcional.

«Cubierto» solo dice que hay una acción no cancelada de ese tipo: no juzga si basta, no bloquea nada y no es un «apto».

**Qué dice la norma.** Tablas 4 y 7, columna «Acción».

**Interpretación.** Usamos el nivel más alto de todo el resultado, y los tipos de acción que cubren cada línea son los de
nuestro catálogo (C-31).

### C-35. Evidencia y conservación · Norma

**Qué hacemos.** Nada del Programa se borra: una acción se **cancela con motivo** y queda en el historial. El control de
avances es de solo inserción: cada avance lleva el día, el estado al que pasó, una nota y, si hay, un archivo (PDF o
imagen) de evidencia (convocatoria, lista de asistencia). El archivo vive en un disco privado, lo abren solo owner y RH, se
audita cada apertura y no sale en el informe ni en el enlace temporal; se avisa al subirlo que una lista de asistencia
lleva nombres. Las imágenes se recodifican y pierden los metadatos de la cámara.

**Qué dice la norma.** Llevar los registros de «las medidas de control adoptadas cuando el resultado (…) lo señale» (num.
5.8 b); conservar las evidencias y registros, impresos o electrónicos, «al menos durante un año» (num. 10.4). La STPS
acepta como evidencia de las actividades la convocatoria, las listas de asistencia y los resultados [STPS-1, pregunta 18].

### C-36. Recordatorios · Humetría

**Qué hacemos.** Cada lunes a las 9:30 (hora de México) el titular recibe un correo con **solo conteos**: acciones
vencidas, acciones que vencen en los próximos 14 días, filas sin acciones, Programas sin responsable y evaluaciones
posteriores vencidas; cada responsable que es miembro recibe sus acciones vencidas y por vencer. Nunca va un título, una
descripción ni un nombre (el texto libre puede traer datos de una persona). No se manda nada si todo está en orden, y una
empresa bloqueada no recibe nada.

**Por qué.** La norma no pone plazos para el Programa más allá de la evaluación (C-26): el aviso de 14 días y el correo
semanal son nuestros.

## H. Canal de quejas y denuncias (numerales 8.1 b, 6.4 y 6.5)

La norma exige «mecanismos seguros y confidenciales»; **no** exige que sean en línea, anónimos ni con seguimiento. Que lo sean,
las categorías, los plazos, la retención y el enrutamiento son criterios de Humetría y así se rotulan en pantalla. Lo que la
norma calla no se presenta como obligación. El diseño completo está en
[`docs/superpowers/specs/2026-11-03-nom-035-complaints-channel-design.md`](../../superpowers/specs/2026-11-03-nom-035-complaints-channel-design.md).

### C-40. Mecanismo seguro y confidencial para quejas y denuncias · Norma + Humetría

**Qué hacemos.** Cada empresa activa un canal con un enlace público (`/c/{código}`) y un cartel con QR. La persona abre el
enlace sin cuenta, sin sesión y sin cookies, y presenta una queja por una práctica opuesta a un entorno organizacional
favorable, una denuncia de violencia laboral o el aviso de un acontecimiento traumático severo, de forma anónima o
confidencial. Al enviar recibe una referencia y una clave para dar seguimiento. El canal tiene un procedimiento por escrito,
personas encargadas designadas y una página de evidencia imprimible.

**Qué dice la norma.** «Disponer de mecanismos seguros y confidenciales para la recepción de quejas por prácticas opuestas
al entorno organizacional favorable y para denunciar actos de violencia laboral» (num. 8.1 b). Los trabajadores informan y
denuncian «a través de los mecanismos que establezca el patrón y/o a través de la comisión de seguridad e higiene»
(num. 6.4). La STPS verifica por documental o entrevista que el patrón dispone de ellos (num. 10.2).

**Por qué.** La evidencia que mostramos es la configuración y la operación del canal, nunca el contenido de los casos.
Fuente: DOF, num. 8.1 b, 6.4 y 10.2.

### C-41. La Comisión de seguridad e higiene puede ser receptora · Norma

**Qué hacemos.** Quien está en la Comisión de un centro puede ser designada para atender el canal, por centro. Un rol nuevo,
«Atención de quejas», sirve para una persona de fuera de la empresa (un abogado, una persona de confianza, un representante
sindical): ve el canal y nada más de la plataforma.

**Qué dice la norma.** La persona trabajadora informa y denuncia usando los mecanismos del patrón «y/o a través de la
comisión de seguridad e higiene» (num. 6.4).

### C-42. El escrito del numeral 6.5 exige el nombre · Norma

**Qué hacemos.** El aviso de un acontecimiento traumático severo no se acepta anónimo: pide el nombre de quien lo escribe, la
fecha en que ocurrió y, si los hay, los involucrados. Solo lo ven las personas a quienes se asignó el caso. La base lo exige
con un `check` mientras el caso vive.

**Qué dice la norma.** El aviso por escrito lleva «fecha de elaboración, nombre de quien lo elabora, nombres de los
involucrados (si los hay), fecha de ocurrencia y descripción» (num. 6.5).

### C-43. Quejas y denuncias: tipos y categorías · Interpretación

**Qué hacemos.** Tres tipos: queja por una práctica, denuncia de violencia laboral y aviso 6.5. La violencia se clasifica en
hostigamiento, acoso y malos tratos (4.12); las prácticas, en cargas o jornadas, responsabilidades poco claras, falta de
formación, falta de reconocimiento, falta de comunicación o participación, discriminación y otra (4.6, 7.3, 8.1 c y 8.2 a 2).
La lista se rotula «clasificación de Humetría» y la persona puede no escoger. Otros marcos legales (por ejemplo, protocolos de
acoso sexual) pueden aplicar a lo que se reporte; el texto de ayuda lo menciona sin citar artículos.

**Qué dice la norma.** Violencia laboral: «actos de hostigamiento, acoso o malos tratos» (num. 4.12). Entorno organizacional
favorable: pertenencia, formación, responsabilidades claras, participación y comunicación, cargas y jornadas regulares,
evaluación y reconocimiento del desempeño (num. 4.6 y 7.3).

### C-44. Acceso a un caso solo por asignación · Humetría

**Qué hacemos.** Ver un caso exige una asignación vigente: ser encargada o tener el permiso no abre ninguno. A quien no lo
tiene asignado, el caso le contesta 404 (nunca 403, que diría que existe). Una encargada designada después no ve los casos
anteriores. El dueño y RH, por administrar el canal, ven conteos, no casos. Cada lectura queda en la bitácora. Las rutas de un
caso se cierran a un superadmin que actúa como el dueño. La encargada puede excusarse o compartir el caso con quien el
conflicto deje.

**Por qué.** Una queja puede ser contra RH o contra el dueño: el modelo de amenaza incluye a quien administra la cuenta.

### C-45. Enrutamiento por conflicto de interés · Humetría

**Qué hacemos.** La persona dice contra quién es la queja por rol, nunca por nombre. Una queja contra recursos humanos no se
asigna a encargadas con rol RH; contra la dirección o el dueño, no a quien tiene rol de dueño; el resto, a todas las
encargadas del centro o de la empresa (sin centro, a las de toda la empresa). Si una opción no tiene a nadie independiente
que la reciba, el formulario **no la acepta** y ofrece la vía externa (la Dirección General de Inspección Federal del
Trabajo): aceptarla y entregarla a la persona señalada daría una falsa protección. El panel muestra cuántas personas
recibirían cada opción. El canal abierto no puede quedarse sin nadie que lo atienda.

**Límite honesto.** La plataforma no puede proteger de quien controla toda la empresa: un dueño puede quitar a las
encargadas. Por eso los cambios en la lista quedan en la bitácora, se avisa a las demás y se ofrece designar a una persona
externa. La denuncia ante la STPS puede ser anónima si da razón social, ubicación del centro y circunstancias (STPS-1,
pregunta 10).

### C-46. Anonimato técnico y sus límites; sin hora, dirección, navegador ni cookies · Humetría

**Qué hacemos.** Las rutas públicas no inician sesión ni escriben cookies, y no guardan la dirección de internet, el
navegador ni el `Referer`. El caso no tiene `created_at` ni `updated_at`: guarda solo el día en que llegó; los mensajes de la
persona, solo el día; la asignación del sistema, solo el día; el orden del hilo es un consecutivo. Los límites de frecuencia
usan una huella con llave de la dirección, en la caché y nada más. La protección contra falsificación es un boleto firmado y
sin estado. El contenido va cifrado en la base. La clave de seguimiento solo existe como hash y no se recupera. Ningún log,
bitácora, correo ni cola lleva el texto de una queja, su referencia ni su clave, y la línea de log de lo que hace la persona
no lleva el id del caso. Un aviso opcional por correo es de solo escritura: nadie lo ve, el sistema solo escribe «hay
novedades», sin referencia ni contenido y sin pasar por la cola, y lo borra a los 30 días de cerrar el caso.

**No se promete «anonimato total».** Se dice en pantalla: el contenido puede identificar, el centro y la persona señalada
acotan el grupo, y la red de la empresa o el proveedor de internet pueden ver que se visitó la página.

### C-47. Estadísticas con mínimo por grupo · Humetría (como C-23)

**Qué hacemos.** Quien administra el canal sin ser encargada ve totales por tipo y por estatus, con el mínimo de 5 en cada
celda y supresión complementaria: si lo oculto sumara menos de 5, se oculta también la celda visible más pequeña. Nunca por
centro, categoría, contra quién ni día. Los casos ya purgados cuentan por su esqueleto.

### C-48. Retención y purga · Humetría (por analogía con 10.4)

**Qué hacemos.** El contenido de un caso cerrado se borra a los N meses del cierre (24 por omisión; de 12 a 60). Una
encargada puede poner el caso en retención legal y no se purga hasta levantarla. Queda un esqueleto sin datos personales:
tipo, categoría, resolución, mes de recepción y día de cierre. La referencia y la clave dejan de abrir el caso. Un caso sin
cerrar nunca se purga; se recuerda como «sin movimiento» a los 90 días.

**Qué dice la norma.** Las evidencias «a que alude esta Norma» se conservan al menos un año (num. 10.4). La norma **no fija**
la retención de las quejas (no son un registro nombrado en 5.8): el plazo y su piso de 12 meses son de Humetría y los valida
un abogado antes del lanzamiento.

### C-49. Plazos de acuse y resolución · Humetría

**Qué hacemos.** El acuse de recibo (3 días por omisión) y la respuesta (30) son recordatorios que la empresa se fija. Se
muestran a la persona, marcan «vencido» a quien atiende y a quien administra solo se le cuentan. No son plazos de la norma.

## I. Política de prevención, difusión y página de las personas trabajadoras (numerales 5.1, 5.7 y 7.8)

La norma pide que el patrón tenga **por escrito** una política de prevención, la **difunda** y deje a las personas
trabajadoras **consultar** qué se hace y qué resultó. No pide acuse de lectura, plazo de revisión ni una página web: lo que
Humetría hace encima de la norma se rotula así. El diseño completo está en
[`docs/superpowers/specs/2026-11-03-nom-035-policy-portal-design.md`](../../superpowers/specs/2026-11-03-nom-035-policy-portal-design.md).

### C-50. Política por escrito, por centro y con versiones · Norma + Interpretación

**Qué hacemos.** La política es texto. Cada **versión publicada es inmutable** (ni la plataforma ni la base de datos la
cambian: es evidencia) y se publica a los centros que elija RH. La **vigente** de un centro es la versión más alta que lo
incluye; una versión posterior para otros centros no la sustituye en los demás. Un centro creado después de publicar no
tiene política hasta que RH la publique. Un centro de hasta 15 personas también la tiene: 5.1 y 5.7 aplican a todo centro.

**Qué dice la norma.** «Establecer por escrito, implantar, mantener y difundir en el centro de trabajo una política de
prevención de riesgos psicosociales que contemple: a) la prevención de los factores de riesgo psicosocial; b) la
prevención de la violencia laboral, y c) la promoción de un entorno organizacional favorable» (num. 5.1). La política es
«la declaración de principios y compromisos que establece el patrón (…)» (num. 4.9). Las evidencias «podrán exhibirse de
manera impresa o en medios electrónicos, y se deberán conservar al menos durante un año» (num. 10.4).

**Por qué.** «Mantener» no tiene periodicidad en la norma: no inventamos un plazo de revisión. Publicar con alguno de los
incisos a, b o c sin marcar se permite, con un aviso en el tablero: la norma dice qué debe contemplar, no bloquea una
versión que no lo declara.

### C-51. La plantilla es la Guía IV y no obliga · Norma + Humetría

**Qué hacemos.** Se ofrece el ejemplo de la Guía de referencia IV, **literal y editable**: nueve compromisos y ocho principios
(texto en [`texto-de-referencia.md`](texto-de-referencia.md)). El nombre de la razón social se pone al publicar y al mostrar.
**Responsables y recursos son obligatorios** al publicar, y publicar exige marcar que RH **revisó que cada compromiso y
principio es cierto en sus centros** (la plantilla incluye cosas que no todo centro hace, como exámenes psicológicos al
ingreso o evaluar el desempeño cada año).

**Qué dice la norma.** «El contenido de esta guía es un complemento para la mejor comprensión de la presente Norma, y no
es de cumplimiento obligatorio.» Y: «La política deberá indicar a los responsables (con capacidad para su aplicación) y los
recursos que se disponen para cumplir con las políticas (…)» (Guía IV).

**Por qué.** Publicar compromisos falsos sería declarar algo que no es cierto ante las personas y la STPS. Solo texto plano:
sin Markdown ni HTML, que en una página pública serían un vector de inyección.

### C-52. Qué es difundir y con qué medios se evidencia · Norma + Interpretación

**Qué hacemos.** Se difunden seis cosas (5.7 a a f): la política, las medidas contra la violencia y las prácticas opuestas
al entorno favorable, las medidas de prevención y control, los mecanismos de queja, los resultados y las posibles
alteraciones a la salud. Un inciso está **entregado** en un centro cuando la **página de las personas está activa** o RH
**registró un medio físico** de ese centro que lo cubre (cartel, boletín, folleto, reunión). Lo que viene del Programa y del
canal de quejas se muestra tal como está; el texto que RH escribe se **suma**, nunca los reemplaza.

**Qué dice la norma.** «Difundir y proporcionar información a los trabajadores sobre: a) la política (…); f) las posibles
alteraciones a la salud por la exposición a los factores de riesgo psicosocial» (num. 5.7). «La evidencia para dar
cumplimiento con la difusión (…) puede ser a través de folletos, boletines, y/o carteles» (num. 10.2). Los centros de hasta
15 trabajadores solo deben cumplir 5.1, 5.4, 5.5, 5.7, 8.1 y 8.2; los resultados (5.7 e) solo aplican de 16 en adelante (num. 2).

**Por qué.** La norma no define «entregar»; la lectura más apegada es que la información esté a la vista de quien la quiera
consultar o se haya puesto por un medio físico. El texto base del inciso f se apoya en la definición de los factores de riesgo
(num. 4.7) y lo valida una persona con criterio médico o legal antes de lanzar.

### C-53. Acuse de lectura de la política · Humetría

**Qué hacemos.** Cada persona activa del centro recibe por correo su enlace personal y puede confirmar «Confirmo que la leí»,
con fecha y hora, **una sola vez y solo de la versión vigente**. Quien llega después (alta, reactivación, cambio de centro)
recibe la vigente de su centro. RH ve el avance, registra acuses en papel y recuerda a los pendientes. No se guarda la
dirección IP. El acuse es de la política (5.7 a), no de los demás bloques.

**Qué dice la norma.** Nada: 5.7 pide «difundir y proporcionar información», no confirmar la lectura.

**Por qué.** Es una evidencia adicional que muchos clientes quieren. Por eso **no cuenta como requisito**: entregar la
política no exige el 100 % de acuses (el tablero lo avisa).

### C-54. Resultados para las personas · Norma + Humetría

**Qué hacemos.** RH **publica** (y puede retirar) los resultados de una evaluación **cerrada** hacia la página. Solo
centros de **16 o más** personas; el **centro completo, sin filtros ni áreas**; las mismas cifras que ve RH (nivel final,
categorías y dominios) por la misma ruta de datos, con el mínimo de respondentes de C-23 y sin la Guía I. Con la
evaluación abierta, sin publicar, retirada, de solo Guía I o por debajo del mínimo, no se muestran cifras (bajo el mínimo
se dice solo que contestaron muy pocas personas, sin el conteo).

**Qué dice la norma.** «El resultado de la identificación y análisis de los factores de riesgo psicosocial y la evaluación
del entorno organizacional deberá estar disponible para consulta de los trabajadores» (num. 7.8).

**Por qué.** La página la lee toda la plantilla: un grupo bajo el mínimo o un filtro permitiría atribuir un resultado a una
persona (C-23, C-27). Publicar es una decisión de RH y no automática al cerrar, para preparar las medidas antes de que las
cifras con riesgo alto lleguen sin contexto.

### C-55. La página permanente y su enlace · Humetría

**Qué hacemos.** Un **enlace por empresa** (con selector de centro), de solo lectura, que se imprime como cartel con QR. El
enlace se deriva de la llave de la aplicación y una versión, para poder volver a verlo sin cambiarlo; solo se guarda su
huella. RH puede **rotarlo** (el anterior deja de servir), **desactivarlo** y ponerle un vencimiento opcional de hasta 36
meses. Cualquier causa de fallo (desconocido, rotado, desactivado, vencido, empresa bloqueada, centro ajeno) da la misma
respuesta neutra, sin nombrar a la empresa. La página **nunca contiene** nombres, correos, ids de personas, áreas,
respuestas, medidas dirigidas a personas ni nada de la Guía I; no registra quién la abre.

**Qué dice la norma.** Nada: 5.7 no pide una página web.

**Por qué.** El cartel se imprime y se filtra: la página solo puede tener lo que la empresa decidió que lean todos. No se
cuenta quién entra porque sería vigilar a la plantilla.

### C-56. Revisar la política con riesgo Medio o más · Norma

**Qué hacemos.** Si la última evaluación publicable tiene algún cuestionario en Medio o más y la política vigente del centro
es anterior a su cierre, el tablero avisa «revise la política»; con riesgo Bajo, «mayor difusión». Solo cuando las cifras
se pueden mostrar (con menos del mínimo no se sabe y no se dice). Es consistente con C-15.

**Qué dice la norma.** Medio: «Se requiere revisar la política de prevención de riesgos psicosociales y programas (…) así
como reforzar su aplicación y difusión, mediante un Programa de intervención»; Bajo: «Es necesario una mayor difusión de la
política (…)» (Tablas 4 y 7).

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
| C-28 | `tests/Feature/Results/ScreeningsPageTest.php` (solo owner y RH, bitácora), `tests/Feature/Results/ResultsPageTest.php` (consulta no la ve), `tests/Feature/Results/ScreeningReferralsTest.php` (registro de la canalización: estados, bitácora, sin datos en el log, aislamiento entre empresas) |
| C-29 | `tests/Feature/Results/ReportTest.php` (incisos a–h), `tests/Feature/Results/ReportSharesTest.php` (15 min a 48 h, hash, vencido, revocado, empresa bloqueada, sin Guía I), `tests/Feature/Results/ReportProgramTest.php` (el Programa en el inciso g, también por enlace temporal, sin archivos ni notas) |
| C-30 | `tests/Unit/Results/RiskBreakdownTest.php` (la regla de qué requiere Programa), `tests/Feature/Interventions/CreateProgramTest.php` (filas, sin Programa, bajo el mínimo, 4 contra 5, idempotencia), `tests/Feature/Interventions/DraftProgramOnCloseTest.php` |
| C-31 | `tests/Feature/Interventions/SaveActionTest.php`, `tests/Feature/Interventions/ActionsHttpTest.php`, `tests/Feature/Interventions/ItemsTest.php` (análisis, evaluación posterior, filas agregadas), `tests/Feature/Interventions/SchemaTest.php` (los checks de la base) |
| C-32 | `tests/Feature/Interventions/SaveActionTest.php` (nivel 3 con profesional, atención clínica solo en nivel 3), `tests/Feature/Interventions/SchemaTest.php` |
| C-33 | `tests/Feature/Interventions/SaveActionTest.php` (áreas visibles, oculta, inexistente, de otra empresa), `tests/Feature/Interventions/SchemaTest.php` (ninguna tabla con llave a persona), `tests/Feature/Interventions/ProgramOutlinesTest.php` (solo las áreas que se muestran) |
| C-34 | `tests/Unit/Interventions/TableChecklistTest.php` (cada nivel contra lo que pide), `tests/Feature/Interventions/CopyActionTest.php` |
| C-35 | `tests/Feature/Interventions/RecordProgressTest.php` (estados, cancelar con motivo), `tests/Feature/Interventions/EvidenceTest.php` (archivo privado, permisos, bitácora, sin ruta en las props), `tests/Feature/Interventions/SchemaTest.php` (el historial no se edita) |
| C-36 | `tests/Feature/Interventions/DigestsTest.php`, `tests/Feature/ScheduleTest.php` (lunes 9:30) |
| C-40 | `tests/Feature/Complaints/ChannelTest.php`, `tests/Feature/Complaints/ChannelStatusTest.php` (lista de lo que necesita el canal, evidencia 8.1 b), `tests/Feature/Complaints/PosterTest.php`, `tests/Browser/ComplaintsTest.php` |
| C-41 | `tests/Feature/Complaints/HandlerRoleTest.php` (rol «Atención de quejas», rutas de la Comisión), `tests/Feature/Complaints/HandlersTest.php` |
| C-42 | `tests/Feature/Complaints/ConfidentialComplaintTest.php` (nombre, fecha y `check`s del 6.5) |
| C-43 | `tests/Feature/Complaints/SubmitComplaintTest.php` (categoría coherente con el tipo) |
| C-44 | `tests/Feature/Complaints/RoutingTest.php` (acceso solo por asignación, sin acceso retroactivo), `tests/Feature/Complaints/InboxTest.php` (404 y no 403, bitácora de lectura, impersonación), `tests/Feature/Complaints/CaseHandlingTest.php` |
| C-45 | `tests/Feature/Complaints/RoutingTest.php` (contra RH, contra la dirección, alcance por centro), `tests/Feature/Complaints/HandlersTest.php` (cobertura), `tests/Feature/Complaints/SubmitComplaintTest.php` (opción sin cobertura rechazada) |
| C-46 | `tests/Feature/Complaints/PublicPageTest.php`, `SubmitComplaintTest.php`, `FollowUpTest.php` (sin sesión, sin cookies, sin dirección en la base), `tests/Feature/Complaints/PrivacyTest.php` (la prueba central: logs, bitácora, correos, colas, tablas y esquema), `tests/Feature/Complaints/ReporterEmailTest.php` |
| C-47 | `tests/Unit/MinimumGroupTest.php`, `tests/Feature/Complaints/ChannelStatusTest.php` (4 y 5, supresión complementaria, sin desgloses) |
| C-48 | `tests/Feature/Complaints/PurgeTest.php` (23 meses 29 días y 24 meses, retención legal, esqueleto, idempotencia), `tests/Feature/Complaints/ReporterEmailTest.php` (el correo a los 30 días) |
| C-49 | `tests/Feature/Complaints/InboxTest.php`, `tests/Feature/Complaints/DigestTest.php` (el límite de los días y un recordatorio por día) |

C-20 (sin ficha de la Guía V) se cumple por ausencia.

## Lo que la plataforma no cubre (por ahora)

Obligaciones de la norma que dependen del cliente o que Humetría todavía no gestiona (el orden para cerrarlas está en
[`gaps-pendientes.md`](gaps-pendientes.md)):

- política de prevención (5.1, Guía IV);
- difusión de información (5.7);
- integración al diagnóstico de seguridad y salud de la NOM-030 (7.6).

Los exámenes médicos y las evaluaciones psicológicas (5.6) los hace un tercero: Humetría deja la constancia de la
canalización y de la atención (C-28), no los practica ni guarda su resultado.

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
| 1.3 | 2026-11-03 | C-28 suma el registro de la canalización por persona (canalizada, atendida, día e institución), con bitácora; «exámenes médicos y evaluaciones psicológicas (5.6)» sale de lo que no se cubre. |
| 1.4 | 2026-11-03 | Sección G, Programa de intervención: nuevos C-30 a C-36 (una fila por categoría, dominio y calificación final en Medio o más; contenido de la acción; niveles; sujetos; qué pide la Tabla; evidencia y conservación; recordatorios). C-29 suma que el inciso g imprime el Programa. El programa de intervención sale de lo que no se cubre. |
| 1.5 | 2026-11-03 | Nueva sección H, canal de quejas y denuncias: C-40 a C-49 (mecanismo, la Comisión como receptora, el escrito 6.5, categorías, acceso por asignación, enrutamiento por conflicto, anonimato técnico, estadísticas con mínimo, retención y purga, plazos); «mecanismo de quejas (8.1 b)» sale de lo que no se cubre. |
| 1.6 | 2026-11-03 | Nueva sección I, política de prevención y difusión: C-50 a C-56 (política por escrito con versiones inmutables; la Guía IV como plantilla; qué es difundir y con qué medios; acuse de lectura; resultados para las personas; la página permanente y su enlace; revisar la política con riesgo Medio o más). 5.1, 5.7 y la consulta de resultados (7.8) salen de lo que no se cubre. |
