# Criterios de aplicación de la NOM-037-STPS-2023 en Humetría

Versión 0.2 (borrador para aprobación) · 30 de octubre de 2026 · Norma base: NOM-037-STPS-2023, DOF del 8 de junio de
2023, en vigor desde el 5 de diciembre de 2023 (versión de la lista de verificación en el sistema: `dof-2023-06-08`).

Este documento explica **cómo aplica Humetría la NOM-037 y por qué**. Cada criterio dice qué hace la plataforma, qué
dice la norma (citada literal), en qué se basa la decisión y la fuente. Sirve igual que el de la NOM-035: como
**sustento** ante una inspección, un cliente o un auditor; como **manual** para RH, y como **lista de revisión** si la
norma cambia.

**Este documento manda** sobre cualquier otro del repositorio en cuanto a cómo se interpreta la NOM-037. Si el código
o un diseño lo contradicen, se corrige el código o el diseño, o se actualiza este documento con su justificación.

> **Estado: borrador.** Los criterios marcados **⚑ Por decidir** son una propuesta y esperan la aprobación del usuario;
> los demás se derivan directamente de la norma o de la interpretación oficial. Al aprobarse, el documento pasa a
> versión 1.

Fuentes (copias en [`fuentes/`](fuentes/README.md)):

- **[DOF]** NOM-037-STPS-2023, *Teletrabajo-Condiciones de seguridad y salud en el trabajo*, DOF 08/06/2023.
  <https://dof.gob.mx/nota_detalle.php?codigo=5691672&fecha=08/06/2023>
- **[RC]** STPS, *Respuestas a los comentarios recibidos en el periodo de consulta pública del PROY-NOM-037-STPS-2022*,
  DOF 15/05/2023. Es la interpretación oficial del Comité que aprobó la norma. Se cita como «[RC, promovente N, l. X]»:
  X es la línea de `fuentes/dof-2023-05-15-respuestas-a-comentarios.txt`.
  <https://dof.gob.mx/nota_detalle.php?codigo=5688683&fecha=15/05/2023>
- **[LFT]** Ley Federal del Trabajo, capítulo XII Bis «Teletrabajo» (arts. 330-A a 330-K), DOF 11/01/2021, sin
  cambios hasta la reforma del 14/05/2026.
- **[NOM-019]** NOM-019-STPS-2011, comisiones de seguridad e higiene.
- **[LFPDPPP]** Ley Federal de Protección de Datos Personales en Posesión de los Particulares, DOF 20/03/2025.

A diferencia de la NOM-035, **la STPS no ha publicado preguntas frecuentes ni guías de la NOM-037**. Su única
interpretación oficial son las respuestas [RC]. Lo que publican despachos y proveedores no se usa como fuente.

El texto literal de la norma y de la lista de verificación del Apéndice 3 está en
[`texto-de-referencia.md`](texto-de-referencia.md).

Cada criterio indica su **tipo**:
- **Norma**: lo dice la norma (o la LFT) de forma expresa.
- **STPS**: lo dice la interpretación oficial [RC].
- **Interpretación**: la norma no lo resuelve y Humetría adopta la lectura más apegada a su texto.
- **Humetría**: va más allá de la norma, normalmente para proteger a las personas o para cerrar un hueco del Apéndice.

---

## Cómo es la NOM-037 (y en qué se distingue de la NOM-035)

La NOM-035 es un **instrumento**: cuestionarios con calificación exacta. La NOM-037 es una **norma de gestión
documental**: casi todo lo que verifica la inspección es «Documental» (tabla del numeral 10.3), y se cumple con
evidencia de que existen un listado, una política, una lista de verificación validada, capacitación, equipo entregado
y mecanismos escritos. Por eso Humetría la aplica como un **expediente por centro de trabajo** con una pieza por
obligación, más una **lista de verificación** que contesta cada persona por cada lugar donde teletrabaja.

La lista de verificación **no se califica con puntos**: no tiene tabla de calificación ni rangos. Quien decide si un
lugar es apto es la Comisión de Seguridad e Higiene (C-12).

| Obligación | Numeral | Qué genera Humetría |
|---|---|---|
| Listado de personas teletrabajadoras | 5.1 | Padrón por centro con los 11 datos, historial de altas y bajas |
| Lugares de trabajo fijos y aptos | 5.1.1, 5.1.2 | Lugares por persona, cada uno con su validación |
| Política de Teletrabajo | 5.2 | Documento con los incisos a) a l) y acuses de difusión |
| Informar riesgos | 5.3 | Material informativo con acuses de personas y de la Comisión |
| Lista de verificación y validación | 5.4, 5.5, 7 | Lista de la Sección I (aspirante) y Sección II (periódica), validación de la Comisión |
| Segunda versión periódica | 5.5.1 | Aplicación de la Sección II con la periodicidad que fije la empresa |
| Proceso presencial ↔ teletrabajo | 5.6 | Documento con los incisos a) a d) |
| Equipo entregado | 5.7, 5.1 k) | Listado de equipo por persona con acuse o negativa justificada |
| Mantenimiento de equipos TIC | 5.8 | Programa documentado |
| Capacitación anual | 5.9, 8 | Registro de cursos, temas y asistencia; alerta a los 12 meses |
| Reversibilidad | 5.10 | Mecanismo documentado y registro de cada regreso |
| Exámenes médicos y avisos de accidente | 5.11 | Solo el registro de avisos (C-26); los exámenes, fuera de alcance |
| Compartir con la Comisión | 5.12 | Acceso de la Comisión al expediente |
| Violencia familiar | 5.13 | Mecanismo documentado y canal de aviso |
| Participación en comisiones | 5.14 | Evidencia documental |
| Avisos de la persona trabajadora | 6.3, 6.7, 6.9 | Formularios con el contenido mínimo, dirigidos a la empresa y a la Comisión |
| Evidencias por un año | 10.4 | Conservación y expediente para la inspección |

---

## A. Alcance

### C-01. La NOM-037 aplica a quien teletrabaja más del 40 % de su jornada · Norma + STPS

**Qué hacemos.** Una persona entra al padrón de teletrabajo cuando su porcentaje de teletrabajo, medido sobre la
jornada de su contrato, es **mayor a 40 %**. Con 40 % exacto no es teletrabajo; con 41 % sí. El trabajo ocasional o
esporádico no se registra.

**Qué dice la ley.** «Se regirán por las disposiciones del presente Capítulo las relaciones laborales que se
desarrollen más del cuarenta por ciento del tiempo en el domicilio de la persona trabajadora bajo la modalidad de
teletrabajo, o en el domicilio elegido por ésta. No será considerado teletrabajo aquel que se realice de forma
ocasional o esporádica.» [LFT, art. 330-A]. La norma pide registrar el «Tiempo (en porcentaje) de la relación laboral
que usa para realizar Teletrabajo» [DOF, 5.1 f)] y su Apéndice 2 repite el umbral «más del 40 por ciento».

**Criterio STPS.** El porcentaje se mide sobre la jornada del contrato: «se ajustó al interior de la NOM para que el
porcentaje (…) sea en función de la jornada laboral contenida en el contrato.» [RC, promovente 27, l. 171]. Y quien
trabaja más del 60 % en el centro es trabajo presencial [RC, promovente 73, l. 1947].

**Prueba obligatoria.** El límite 40/41 se prueba igual que los límites 15/16 y 50/51 de la NOM-035.

### C-02. Se aplica por centro de trabajo y por razón social · Norma

**Qué hacemos.** Cada persona teletrabajadora pertenece a un centro de trabajo de una empresa, y el padrón, la
política y el expediente son de ese centro. Es el mismo modelo de la NOM-035.

**Qué dice la norma.** «aplica a todos los centros de trabajo que cuenten con personas trabajadoras bajo la modalidad
de Teletrabajo» [DOF, 2]. El listado incluye la «Razón social y domicilio del centro de trabajo» [DOF, 5.1 j)]. La
evaluación de la conformidad se hace «en el domicilio del centro de trabajo» [DOF, 10.3] y «no visitarán lugares de
trabajo» [DOF, 9].

**Nota.** La norma solo aplica si la empresa decide tener teletrabajo: «es el patrón quien en principio tomar de
decisión de si en su centro de trabajo se cuenta con las posibilidades y necesidades de realizar teletrabajo, de ser
el caso, le aplicará la norma» [RC, promovente 68, l. 3533]. Un centro sin personas teletrabajadoras no tiene
obligaciones de NOM-037.

### C-03. El lugar de trabajo es fijo y privado; una persona puede tener varios · Norma + STPS

**Qué hacemos.** Cada persona registra uno o más **lugares de trabajo** (domicilio u otro lugar fijo, como una oficina
en renta), cada uno con su dirección. Un lugar público (un café, un parque) no se acepta. **Cada lugar** tiene su
propia lista de verificación y su propia validación. El lugar lo propone la persona; la empresa lo acepta o no.

**Qué dice la norma.** «Lugar de trabajo: Espacio físico fijo y privado, fuera del centro de trabajo y ajeno a éste,
convenido con el patrón» [DOF, 4.18]. «Es válido que, para una sola persona (…) exista en el listado más de un
domicilio acordado» [DOF, 10.3, obs. a 5.1]. Si son varios, «para cada uno de esos lugares, se deberá de contar con la
validación» [DOF, 10.3, obs. a 5.4 y 7], y la empresa debe tener por escrito «en qué casos la persona (…) puede
cambiar de uno a otro lugar de trabajo de los acordados» [DOF, 10.3, obs. a 5.1.1].

**Criterio STPS.** Ante la propuesta de excluir los espacios públicos: «Procede su comentario.» [RC, promovente 25,
l. 1186]. «es sólo el trabajador (…) quien tiene la potestad de definir el lugar» [RC, promovente 71].

---

## B. Listado de personas teletrabajadoras (5.1)

### C-04. El padrón tiene los 11 datos de la norma · Norma

**Qué hacemos.** El padrón de cada centro guarda, por persona: a) nombre; b) género; c) estado civil; d) actividades a
desarrollar; e) nombre y perfil del puesto; f) porcentaje de teletrabajo; g) teléfono de contacto; h) domicilio;
i) lugares de trabajo convenidos; j) razón social y domicilio del centro (los toma del centro); k) equipo de cómputo y
ergonómico otorgado (C-22). Nombre, género y puesto ya existen en Empleados; el resto es nuevo.

**Qué dice la norma.** [DOF, 5.1 a) a k)], literal en el anexo. La inspección acepta un dato faltante si se completa
«durante el desahogo de la diligencia» [DOF, 10.3, obs. a 5.1], pero Humetría no permite agregar a nadie al padrón sin
los datos completos.

**No pedimos.** Discapacidad ni datos de salud: la STPS rechazó incluirlos porque «pudiere considerarse como una
condición de segregación o discriminación» [RC, promovente 23, l. 1953].

### C-05. El padrón se actualiza con cada alta y baja, y guarda su historia · Norma

**Qué hacemos.** Cada vez que una persona entra o sale del teletrabajo, el padrón queda con la fecha. Se puede consultar
el padrón vigente y su historial.

**Qué dice la norma.** «La actualización del listado (…) se da cuando una nueva persona trabajadora se incorpora a
esta modalidad, o cuando una de las que ya están en el listado deja de estarlo.» [DOF, 10.3, obs. a 5.1]

### C-06. Confidencialidad del padrón · Norma + Humetría

**Qué hacemos.** El padrón lo ven solo los roles autorizados (C-25). El domicilio, el estado civil y el teléfono
no aparecen en listas generales ni en exportaciones que no los necesiten.

**Qué dice la norma.** «La confidencialidad de los datos contenidos en el listado (…) estará bajo la responsabilidad
del patrón.» [DOF, 5.1, nota]

---

## C. Lista de verificación (5.4, 5.5, 5.5.1 y capítulo 7)

### C-07. Cada centro edita su lista, partiendo de la base de Humetría · Norma + STPS + Humetría

**Qué hacemos.** Cada centro de trabajo tiene su lista de verificación, que la Comisión puede editar desde el primer
día. Arranca con la lista base de Humetría, que tiene dos secciones:

- **Sección I** (antes de teletrabajar): las 48 preguntas del Apéndice 3, Sección I, literales.
- **Sección II** (comprobación periódica): las 61 preguntas del Apéndice 3, Sección II, literales.
- **Preguntas agregadas por Humetría** (C-08), marcadas como tales, para cubrir lo que el capítulo 7 y el numeral 5.1.1
  exigen y el Apéndice no pregunta.

La Comisión puede **agregar, quitar y modificar** preguntas, y cambiar su respuesta favorable (C-10). Reglas:

- **Versiones.** Cada vez que la Comisión publica cambios se crea una versión nueva, con quién la publicó y cuándo. Una
  lista ya contestada conserva la versión con la que se contestó; los cambios solo aplican a las siguientes.
- **Origen visible.** Cada pregunta indica si es del Apéndice 3 literal, del Apéndice 3 modificada, de Humetría o de la
  Comisión. Solo las primeras se presentan como texto del DOF.
- **Cobertura.** Cada pregunta base lleva el requisito que cubre (tabla de C-08). Si una edición deja algún requisito
  del capítulo 7 o del 5.1.1 sin preguntas en alguna sección, Humetría lo advierte antes de publicar, porque la
  inspección lo revisa. La Comisión puede publicar de todos modos: la decisión es suya.
- **Forma.** Toda pregunta, nueva o editada, se contesta Sí / No / No aplica (C-09).

**Qué dice la norma.** La lista es una «Herramienta elaborada por la Comisión de Seguridad e Higiene» [DOF, 4.17]. El
Apéndice 3 es «informativo y no es de cumplimiento obligatorio», y «El patrón puede adicionar, eliminar o modificar
las preguntas» [DOF, Apéndice 3].

**Criterio STPS.** Las preguntas las elabora la Comisión, «y quien también analizará y en su caso validará su
contenido» [RC, promovente 72, l. 2485]; «cada centro de trabajo podrá elaborar las propias y eliminar o modificar las
que considere convenientes» [RC, l. 6456]; «es preferible contar con preguntas dicotómicas y no abiertas» [RC,
promovente 34, l. 3445].

**Decisión del usuario (2026-10-30).** La lista es editable desde el principio.

### C-08. Lo que el Apéndice 3 no pregunta y la inspección sí revisa · Norma + Humetría

**Por qué.** La inspección del numeral 5.4 y 7 revisa que la lista «precisa» cada condición del capítulo 7 [DOF, 10.3].
Si se usa el Apéndice 3 tal cual, faltan estas:

| Requisito | Numeral | Sección I | Sección II | Pregunta que agrega Humetría |
|---|---|---|---|---|
| Conectividad para las TIC | 5.1.1 a) | No | No | Ambas: si el lugar tiene conexión a internet suficiente para trabajar |
| Área limpia y ordenada | 7.1 a) | Parcial (`S1-30`, pisos) | Sí (`S2-22`) | Sección I: si el área estaría limpia y ordenada, sin objetos innecesarios |
| Apoyar los brazos y nada que estorbe bajo la mesa | 7.2 a) | No (solo la altura, `S1-44`) | Sí (`S2-57`, `S2-58`) | Sección I |
| Aditamentos ergonómicos | 7.2 c) | No | Sí (`S2-19`, `S2-48`, `S2-49`) | Sección I: si necesitaría alguno (informativa: dispara 5.7 c) |
| Conciliar trabajo y familia, jornada flexible | 7.3 b) | Parcial (`S1-46`, `S1-48`) | Parcial (`S2-59`) | Ambas |
| Mecanismo para avisar de violencia familiar | 7.3 c) | No | No | Ambas: si conoce el mecanismo de la empresa |
| Pausas y descansos | 7.3 d) | No | Parcial (`S2-60`) | Ambas |
| Derecho a la desconexión | 7.3 e) | No | No | Ambas |

El texto exacto de las preguntas agregadas se fija en el diseño (spec) con la misma forma del Apéndice: dicotómicas,
trato de «usted», Sí / No / No aplica. Nunca se presentan como texto del DOF.

### C-09. Respuestas: Sí, No o No aplica · Norma

**Qué hacemos.** Cada pregunta acepta una sola respuesta: Sí, No o No aplica. Las preguntas que el Apéndice formula
«en caso de que» (`S1-22`, `S2-09`, `S2-19`, `S2-47`) admiten «No aplica»; las demás también lo permiten, porque así
lo dice la instrucción.

**Qué dice la norma.** «La persona trabajadora (…) solamente puede optar por una anotación ("X") en la celda ("SI",
"NO" o "NO APLICA)".» [DOF, Apéndice 3, instrucción 3]

### C-10. Respuesta desfavorable: se documenta el riesgo · Norma + Interpretación

**Qué hacemos.** Cada pregunta tiene una **respuesta favorable** (anexo de este documento). Cuando la persona contesta
la otra, la pregunta queda marcada y se le pide describir el riesgo, con una foto si quiere (C-15). Las preguntas
**informativas** (tipo de lámpara, si hay aire acondicionado, si hay personas a su cuidado) no marcan nada: describen
el lugar. «No aplica» tampoco marca.

**Qué dice la norma.** «En caso de que la respuesta infiera una condición desfavorable para realizar actividades de
Teletrabajo (…) deberá documentar el tipo de riesgo identificado y, en su caso, el patrón podrá acordar un plan de
acción para poder eliminarla. Lo mínimo que debe tener esta notificación es evidencia documental (de ser posible
evidencia fotográfica)» [DOF, Apéndice 3, instrucción 3]

**Por qué es interpretación.** El Apéndice no dice cuál respuesta es la desfavorable, y la polaridad cambia de una
pregunta a otra (tener extintor es favorable; tener cables expuestos, no). La tabla del anexo la fija con base en
la condición del capítulo 7 que cada pregunta revisa. Esa tabla es solo **el valor inicial** de la lista base: como
la lista es editable (C-07), la Comisión puede cambiar la respuesta favorable de cualquier pregunta.
**⚑ Por decidir:** aprobar la tabla del anexo como valor inicial.

### C-11. La persona contesta; no decide · Norma + STPS

**Qué hacemos.** Al terminar, la persona ve que su lista fue enviada a la Comisión, **no** un «aprobado» ni un
«reprobado». Antes de empezar se le muestra que contestar la lista no la hace responsable de las condiciones del lugar
ni de pagar adecuaciones.

**Qué dice la norma.** «La autoaplicación (…) no significa el traslado de la responsabilidad a las personas
trabajadoras (…) ni de la adecuación a cargo de su patrimonio.» [DOF, 5.5]

**Criterio STPS.** La persona «no será quien determine si se cumple o no» [RC, promovente 8, l. 4844].

### C-12. La Comisión valida o no valida cada lugar · Norma + STPS

**Qué hacemos.** Un integrante de la Comisión revisa cada lista con sus respuestas marcadas, sus descripciones y
fotos, y registra uno de tres resultados, con fecha, nombre y observaciones:

1. **Validado.** El lugar es apto.
2. **Validado con plan de acción.** El lugar es apto con medidas y fecha compromiso (p. ej., entregar un reposapiés).
3. **No validado.** El lugar no es apto: no se puede teletrabajar ahí (C-13).

Antes de decidir, la Comisión puede pedir una comprobación a distancia (videollamada) o fotos o video del lugar, y
Humetría registra la solicitud y lo que se recibió. Humetría **no** calcula el resultado: lo propone la Comisión, con
las marcas como apoyo.

**Qué dice la norma.** «Validación (…): Confirmación de las condiciones (…) que realiza la Comisión de seguridad e
higiene a las condiciones declaradas, evaluadas y evidenciadas (…) y que, en su caso, da por aceptables» [DOF, 4.27].
La Comisión «podrá solicitar (…) una comprobación a distancia del sitio de trabajo, apoyándose con las TIC, o
evidencias fotográficas o de video» [DOF, 5.5].

**Criterio STPS.** La revisión «no depende de que exista una rúbrica, una firma un sello o un visto bueno» [RC,
promovente 17, l. 1093]. Por eso no hay puntaje.

**Visita presencial.** La norma permite, como alternativa a la lista, una visita de la Comisión con autorización
previa y por escrito de la persona [DOF, 5.5 a); 6.1]. Humetría la registra como otra forma de validar (fecha, quién
fue, autorización y resultado). No la sustituye: la lista se contesta de todos modos, porque es la evidencia que se
guarda [DOF, 10.2].

### C-13. Sin validación no hay teletrabajo en ese lugar · Norma

**Qué hacemos.** Un lugar sin validación vigente aparece «Pendiente de validar», y uno no validado aparece «No apto».
Si la persona ya teletrabaja y la Sección II de un lugar sale «No validado», Humetría lo señala para iniciar la
reversibilidad (5.10) o cambiar de lugar.

**Qué dice la norma.** «En caso de que el lugar de trabajo propuesto (…) no contara con las condiciones propias de
seguridad y salud en el Trabajo, no se podrá realizar Teletrabajo.» [DOF, 5.5]

### C-14. Sección I antes de teletrabajar; Sección II periódica · Norma + STPS

**Qué hacemos.** La Sección I se contesta antes de que la persona entre al padrón, por cada lugar propuesto. La
Sección II se aplica periódicamente a cada persona del padrón, por cada lugar.

**Qué dice la norma.** «Una segunda versión de la lista de verificación (…) deberá de servir para aplicarla
periódicamente y constatar la continuidad de las condiciones» [DOF, 5.5.1]. La persona da la lista «antes de figurar
como persona trabajadora bajo la modalidad de Teletrabajo, y después de serlo, con la periodicidad que corresponda»
[DOF, 6.1].

**Criterio STPS.** La periodicidad no está fija: «corresponderá al patrón establecer dicha periodicidad» [RC,
promovente 3, l. 3193; promovente 62, l. 3316].

**Decisión del usuario (2026-10-30).** Cada empresa elige la periodicidad en la configuración de NOM-037: un periodo
predefinido (3, 6, 12 o 24 meses) o uno propio en meses. Por defecto, **12 meses**. Humetría avisa cuando una
validación está por vencer y, al vencer, el lugar vuelve a «Pendiente de validar». La Sección II también se aplica
cuando cambia el lugar (6.7).

### C-15. Fotos y video: opcionales y con consentimiento · Norma + Humetría

**Qué hacemos.** La persona puede adjuntar fotos a la lista o a una respuesta desfavorable, y la Comisión puede
pedirlas (C-12). Antes de subirlas, la persona acepta de forma expresa. Las fotos solo las ven la Comisión y los roles
autorizados, nunca un enlace público, y se recomienda no mostrar personas.

**Qué dice la norma.** «Si está de acuerdo, adjunte (…) fotografías del lugar trabajo» [DOF, Apéndice 3]. Visita o
comprobación «si está de acuerdo el trabajador» [DOF, 6.1].

### C-16. La lista y su resultado no son anónimos · Norma

**Qué hacemos.** A diferencia de las Guías II y III de la NOM-035, la lista de verificación es **identificada**: la
Comisión necesita saber de quién es el lugar para validarlo [DOF, 4.27; 5.12]. No se aplica el mínimo de respondentes.
El invariante de anonimato de CLAUDE.md no aplica a la NOM-037 (solo a Guía II/III y a la encuesta de Ley Silla), pero
sí aplican las reglas de privacidad de C-25.

---

## D. Comisión de Seguridad e Higiene

### C-17. Todo centro con teletrabajo tiene Comisión · Norma + STPS

**Qué hacemos.** Para usar NOM-037 en un centro, la empresa registra a los integrantes de su Comisión de Seguridad e
Higiene, con la fecha de su acta de constitución. En centros con menos de 15 trabajadores la Comisión es una persona
trabajadora y la persona empleadora o su representante; Humetría no exige más.

**Qué dice la norma.** «Constituir e integrar al menos una comisión en el centro de trabajo» [NOM-019, 5.1]; «Un
trabajador y el patrón o su representante, cuando el centro de trabajo cuente con menos de 15 trabajadores» [NOM-019,
7.1 a)].

**Criterio STPS.** «en la norma se da por hecho que en el centro de trabajo en donde laboran personas trabajadoras bajo
la modalidad de teletrabajo, existe comisión de seguridad e higiene» [RC, promovente 44, l. 4826].

**Nota.** La tabla 10.3 menciona la validación «por el propio patrón cuando no exista en el centro de trabajo comisión
de seguridad e higiene debido a que por la cantidad de trabajadores de ese centro de trabajo no lo amerite» [DOF, 10.3,
obs. a 5.4 y 7]. La NOM-019 no exime a nadie, así que Humetría no ofrece ese atajo.

**Decisión del usuario (2026-10-30).** Un rol nuevo, **Comisión de seguridad e higiene** (Spatie, como los roles
actuales). Solo ve la NOM-037 de su centro: edita la lista (C-07), valida lugares (C-12) y recibe los avisos (C-26). No
ve la NOM-035 ni el resto de la empresa.

### C-18. La Comisión tiene acceso a la documentación · Norma

**Qué hacemos.** El rol de la Comisión ve, de su centro: las listas, sus validaciones, las fotos y el equipo entregado.

**Qué dice la norma.** «Compartir la documentación que resulte del cumplimiento de la presente Norma a la Comisión»
[DOF, 5.12 a) a d)].

---

## E. Política y documentos de la empresa

### C-19. Política de Teletrabajo con los incisos a) a l) · Norma

**Qué hacemos.** La empresa **sube su Política de Teletrabajo en PDF**. Antes de publicarla, RH marca qué incisos de
5.2 a) a l) cubre, y Humetría advierte cuáles faltan. Cada versión queda guardada con su fecha. Humetría no redacta la
política ni ofrece plantilla (decisión del usuario, 2026-10-30).

**Qué dice la norma.** [DOF, 5.2 a) a l)], literal en el anexo. La política puede ir sola o dentro de otra política
[DOF, 10.3, obs. a 5.2]. El Apéndice 1 es solo de referencia: «no se considerará como incumplimiento si el patrón
elaboró su propia política (…) sin tomar en consideración el contenido de dicho Apéndice» [DOF, 10.3, obs. a 5.2].
Debe cumplir con el contrato colectivo o el reglamento interior [DOF, 5.2; LFT, 330-C y 330-D].

**Nota para clientes.** El Apéndice 1 sugiere revisar la política «al menos cada trimestre». Es informativo, no
obligatorio: Humetría no lo exige, pero muestra la fecha de la última revisión.

### C-20. La política se difunde y se puede demostrar · Norma

**Qué hacemos.** Al publicar una versión, Humetría la envía a todas las personas del centro (no solo a las que
teletrabajan) y registra quién confirmó haberla recibido y leído, con fecha.

**Qué dice la norma.** La inspección revisa que la política se «Establece», «Implanta», «Mantiene» y «Difunde» [DOF,
10.3, 5.2]. «la deben conocer todos los trabajadores del centro de trabajo (incluidas las personas trabajadoras bajo
la modalidad de teletrabajo)» [DOF, 10.3, obs. a 5.2].

### C-21. Los otros documentos que pide la norma · Norma

**Qué hacemos.** El expediente tiene un lugar, con su lista de contenido mínimo, para cada documento:

| Documento | Numeral | Contenido mínimo |
|---|---|---|
| Proceso de cambio presencial ↔ teletrabajo | 5.6 | Comunicación; cuándo asiste al centro; cómo se supervisa; mantenimiento del equipo |
| Programa de mantenimiento de equipos TIC | 5.8 | Fechas y tipo de actividades [DOF, 10.3, obs. a 5.8] |
| Mecanismo de reversibilidad | 5.10 | Cuándo y cómo se regresa a presencial (LFT 330-G) |
| Mecanismo de atención de violencia familiar | 5.13 | Incluye regresar a presencial, temporal o permanente |
| Apoyo para participar en las comisiones | 5.14 | Evidencia documental |

Pueden ser secciones de la misma política o PDF aparte. Humetría no redacta textos legales: guarda el documento y
muestra la lista de contenido mínimo.

### C-22. Equipo entregado: listado, acuse o negativa · Norma

**Qué hacemos.** Por persona, el listado de equipo entregado (silla ergonómica u otra apropiada, insumos, aditamentos
y equipo de cómputo), cada uno con fecha y el **acuse de la persona**. Si la persona no acepta un artículo, firma la
negativa con su motivo.

**Qué dice la norma.** [DOF, 5.7 a) a c); 5.1 k)]. «Es válido que el patrón presente (…) un documento en el que figure
el listado de equipo proporcionado (…) firmado por ésta como evidencia de recibido» y la negativa se firma
«justificando los motivos de su decisión» [DOF, 10.3, obs. a 5.7]. La ley pide además «Llevar registro de los insumos
entregados» [LFT, 330-E fr. IV].

**Criterio STPS.** Si la empresa no entrega, «deberá justificar ante la autoridad del trabajo los motivos, pero
estaría en incumplimiento» [RC, promovente 58, l. 3528].

---

## F. Información y capacitación

### C-23. Informar los riesgos a las personas y a la Comisión · Norma

**Qué hacemos.** La empresa publica su material sobre riesgos del teletrabajo (agentes físicos, químicos y biológicos;
factores ergonómicos y psicosociales). Humetría ofrece un texto base y registra el acuse de cada persona
teletrabajadora y de cada integrante de la Comisión.

**Qué dice la norma.** [DOF, 5.3] y la tabla 10.3, que lo verifica como «Documental y entrevista» y acepta «charlas» o
«material impreso o digital».

### C-24. Capacitación al menos una vez al año · Norma

**Qué hacemos.** Registro de cada capacitación: fecha, modalidad, temas y asistentes. Humetría avisa cuando a una
persona del padrón le faltan menos de 30 días para cumplir 12 meses sin capacitación. Al registrarla, RH marca los
temas del 8.1 que cubrió, y Humetría señala los que faltan.

**Qué dice la norma.** «capacitación al menos una vez al año» [DOF, 5.9]. Temas: reconocer peligros con la lista de
verificación (riesgos por condiciones peligrosas e inseguras, ergonómicos, psicosociales), la Política de
Teletrabajo y el manejo de las TIC [DOF, 8.1]. Puede ser «remota, presencial o mixta, como parte de la jornada de
trabajo» [DOF, 8.1, nota].

**Decisión del usuario (2026-10-30).** Humetría **registra** la capacitación; no la imparte.

---

## G. Privacidad y avisos de la persona trabajadora

### C-25. Datos personales y quién ve qué · Norma + Humetría

**Qué hacemos.** El expediente NOM-037 lo ven `owner`, `hr` y la Comisión de su centro; `viewer` no ve datos
personales (domicilio, estado civil, teléfono, fotos ni respuestas). La lista contiene datos que pueden ser sensibles:
la pregunta `S2-61` («ansiedad, irritabilidad, estados depresivos, o fatiga mental») y la nota de «necesidad especial»
(lesión, enfermedad o dolencia) revelan estado de salud. Esas respuestas se piden con **consentimiento expreso
electrónico** dentro de la lista, y se pueden dejar en blanco.

**Qué dice la ley.** Los datos que revelan el «estado de salud presente o futuro» son sensibles [LFPDPPP, art. 3
fr. VI], y su tratamiento requiere «consentimiento expreso y por escrito (…) a través de su firma autógrafa, firma
electrónica, o cualquier mecanismo de autenticación» [LFPDPPP, art. 8]. No se requiere consentimiento para cumplir
obligaciones de la relación laboral [LFPDPPP, art. 9 fr. IV], lo que cubre el padrón del 5.1.

**Decisión del usuario (2026-10-30).** Humetría incluye un texto de consentimiento base (salud y fotos) que la persona
acepta dentro de la lista, con fecha y versión del texto. La empresa es la responsable de los datos y Humetría el
encargado: se le recomienda revisar el texto con su abogado, y puede reemplazarlo por el suyo.

### C-26. Los tres avisos de la persona, con su contenido mínimo · Norma

**Qué hacemos.** La persona teletrabajadora tiene tres formularios. Los tres piden lo que exige la norma (fecha de
elaboración, nombre, fecha de ocurrencia y descripción) y llegan a la empresa y a la Comisión:

| Aviso | Numeral | Qué dispara en Humetría |
|---|---|---|
| Alteración de las condiciones, fuerza mayor o acontecimiento traumático severo | 6.3 | Revisión de la Comisión; si es un acontecimiento traumático, ver C-27 |
| Cambio de domicilio, definitivo o temporal | 6.7 | Nuevo lugar con su lista Sección I y validación antes de usarlo |
| Riesgo de trabajo (accidente o enfermedad) | 6.9 | Registro para el seguimiento del 5.11 |

**Qué dice la norma.** «El escrito deberá contener al menos: la fecha de elaboración; el nombre de la persona (…); la
fecha de ocurrencia, y la descripción» [DOF, 6.3, 6.7 y 6.9]. El cambio de domicilio se avisa «por escrito y con
antelación» [DOF, 6.7].

**Criterio STPS.** La Comisión valida el escrito y «puede solicitar más evidencias» [RC, promovente 50, l. 4568].

### C-27. Acontecimiento traumático severo: conexión con la NOM-035 · Norma + Humetría

**Qué hacemos.** Si el aviso 6.3 reporta un acontecimiento traumático severo, Humetría propone a RH lanzar a esa
persona una campaña de **solo Guía I** de la NOM-035, la misma de su C-05.

**Qué dice la norma.** La NOM-037 usa la misma definición de acontecimiento traumático severo [DOF, 4.2] y pide que la
persona lo informe [DOF, 6.3]. La NOM-035 pide aplicar la Guía I a quien lo sufra o presencie (criterio C-05 de la
NOM-035).

### C-28. La NOM-037 no exige un cuestionario psicosocial · STPS

**Qué hacemos.** La NOM-037 no agrega cuestionarios de factores psicosociales. Las personas teletrabajadoras siguen en
la plantilla de su centro y cuentan para la Evaluación NOM-035 como cualquier otra (CLAUDE.md: la NOM-035 aplica por
centro).

**Criterio STPS.** «no se requiere por el momento en la norma lo que usted denomina cuestionarios, dado que ello
complicaría, en principio, la selección de los aspirantes» [RC, promovente 48, l. 5207].

---

## H. Evidencias e inspección

### C-29. Evidencia digital, conservada al menos un año · Norma

**Qué hacemos.** Todo registro de NOM-037 (listas, validaciones, fotos, acuses, avisos, versiones de la política,
capacitaciones y equipo) se guarda en Humetría sin poder borrarse durante al menos un año desde su fecha. Lo vigente se
conserva mientras lo esté.

**Qué dice la norma.** «Las evidencias de tipo documental o los registros (…) podrán exhibirse de manera impresa o en
medios informáticos o digitales, y se deberán conservar al menos durante un año.» [DOF, 10.4]. Las listas recibidas
«deberán estar a disposición de la autoridad laboral cuando ésta lo solicite» [DOF, 10.2].

### C-30. Expediente para la inspección, ordenado como la tabla 10.3 · Humetría

**Qué hacemos.** Un expediente por centro, imprimible desde el navegador (como el informe 7.7 de la NOM-035), con una
sección por cada renglón de la tabla 10.3 (5.1, 5.1.1, 5.1.2, 5.2, 5.3, 5.4 y 7, 5.5, 5.5.1, 5.6, 5.7, 5.8, 5.9 y 8,
5.10, 5.11, 5.12, 5.13 y 5.14), con su evidencia o con «Falta» si no la hay. El tablero de NOM-037 muestra el mismo
avance.

**Por qué.** La inspección se hace en el centro de trabajo «mediante la constatación física, revisión documental,
registros o entrevistas» [DOF, 10.3]. Ordenar el expediente como la tabla es la forma más directa de demostrar cada
renglón.

**Sobre la columna «Riesgo».** La tabla 10.3 tiene una columna «Riesgo» que en el DOF viene **vacía** en las tres
versiones (HTML, Word y PDF de la edición) y que [RC] no explica. Humetría no la llena ni inventa una clasificación.

---

## Anexo: respuesta favorable por pregunta (valor inicial, ⚑ propuesta)

Valor inicial de la lista base; la Comisión lo puede cambiar (C-07). Respuesta que **no** marca riesgo. Si la persona contesta la otra, la pregunta se marca (C-10). «Informativa»: no
marca en ningún caso. «No aplica» nunca marca. Los criterios usados:

- Sí es favorable cuando la pregunta describe una condición del capítulo 7 o del 5.1.1 (iluminación, extintor,
  silla ajustable…), y No cuando describe un peligro (cables expuestos, olores, ruido, bordes afilados…).
- `S1-07` (más del 40 %) y `S1-01`/`S1-02` (voluntario, no impuesto) no hablan del lugar sino del alcance
  (LFT 330-A y 330-G). Se marcan para que la Comisión y RH lo vean.
- Las preguntas de **necesidad** (`S2-48` reposapiés, `S2-49` apoyo lumbar) marcan con «Sí» porque obligan a la
  empresa a proporcionar el aditamento [DOF, 5.7 c)].
- `S1-35`/`S2-28` (multicontactos), `S1-37`/`S2-30` (contacto a más de un metro) y `S2-33` (extensión) marcan con «Sí»
  porque el Apéndice las pregunta como condición de riesgo eléctrico o de tropiezo.
- `S2-53` pide de 30 a 50 cm entre la cara y el monitor, y el Apéndice 5 (método ROSA) dice de 40 a 75 cm. Se respeta
  el texto de la pregunta; la incongruencia queda anotada.

| Pregunta | Texto (abreviado) | Respuesta favorable |
|---|---|---|
| `S1-01` | ¿Usted trabajaría en la modalidad de Teletrabajo de forma voluntaria? | Sí |
| `S1-02` | ¿Usted se incorporaría a la modalidad de Teletrabajo por imposición del patrón? | No |
| `S1-03` | ¿Ha pensado Usted en algún lugar que pudiera seleccionar como lugar de trabajo para realizar… | Sí |
| `S1-04` | ¿El lugar que usted pudiera tener en mente como lugar de trabajo para realizar Teletrabajo e… | Sí |
| `S1-05` | ¿En el lugar de trabajo que usted ocuparía para realizar el Teletrabajo, existen menores de… | Informativa |
| `S1-06` | ¿Para realizar su trabajo solamente utiliza las TIC´s [Tecnologías de la Información y Comun… | Sí |
| `S1-07` | ¿El porcentaje de tiempo de su jornada laboral contenida en su contrato de trabajo, que dese… | Sí |
| `S1-08` | En el supuesto de que se incorpore a la lista de personas trabajadoras bajo la modalidad de… | Sí |
| `S1-09` | ¿Considera que podría encontrarse laborando en la modalidad de Teletrabajo con supervisión m… | Sí |
| `S1-10` | ¿Para incursionar en la modalidad de Teletrabajo requiere necesariamente que el patrón le pr… | Informativa |
| `S1-11` | ¿Para incursionar en la modalidad de Teletrabajo requiere necesariamente que el patrón le pr… | Informativa |
| `S1-12` | ¿Para incursionar en la modalidad de Teletrabajo requiere necesariamente que el patrón le pr… | Informativa |
| `S1-13` | ¿Al estar en la modalidad de trabajo, considera que podría cumplir con sus metas y proyectos… | Sí |
| `S1-14` | ¿El lugar de trabajo seleccionado para realizar Teletrabajo dispone de un espacio físico de… | Sí |
| `S1-15` | ¿El lugar de trabajo seleccionado para realizar Teletrabajo dispone de un espacio físico de… | Sí |
| `S1-16` | ¿El lugar de trabajo que usted destinaría para el Teletrabajo cuenta con iluminación natural… | Sí |
| `S1-17` | ¿Considera que la luz natural en el lugar de trabajo, con la que realizaría Teletrabajo, pod… | No |
| `S1-18` | ¿La posición de las lámparas, focos o luminarias del lugar de trabajo en las que realizaría… | No |
| `S1-19` | ¿El lugar de trabajo que usted utilizaría para realizar el Teletrabajo cuenta con lámparas d… | Informativa |
| `S1-20` | ¿En el lugar de trabajo, donde colocaría los equipos de las TIC, se evitaría el deslumbramie… | Sí |
| `S1-21` | ¿El lugar de trabajo en donde realizaría el Teletrabajo cuenta con ventanas cerca del plano… | Informativa |
| `S1-22` | ¿En caso de que cuente con ventanas el lugar de trabajo en donde realizaría el Teletrabajo,… | Informativa |
| `S1-23` | ¿En el lugar de trabajo en el que realizaría Teletrabajo cuenta con aire acondicionado? | Informativa |
| `S1-24` | ¿La temperatura del lugar de trabajo que utilizaría para realizar Teletrabajo le parece muy… | No |
| `S1-25` | ¿Considera que la temperatura de lugar de trabajo que destinaría para realizar Teletrabajo e… | No |
| `S1-26` | ¿La altura del lugar de trabajo que utilizaría para el Teletrabajo es de más de dos metros y… | Sí |
| `S1-27` | ¿En el lugar de trabajo que ocuparía para realizar Teletrabajo se percibe ruido de la calle,… | No |
| `S1-28` | ¿En el lugar de trabajo que utilizaría para el Teletrabajo se percibe un volumen alto de los… | No |
| `S1-29` | ¿El área de trabajo que destinaría como lugar de trabajo está separada de otras áreas por el… | Sí |
| `S1-30` | ¿Los pisos del lugar de trabajo que destinaría al Teletrabajo están despejados y libres de e… | Sí |
| `S1-31` | ¿Dispondría usted de un botiquín de primeros auxilios cerca al lugar de trabajo donde realiz… | Sí |
| `S1-32` | ¿En el lugar de trabajo que usaría para realizar Teletrabajo existe mobiliario con esquinas… | No |
| `S1-33` | ¿Dispone cerca del lugar de trabajo de un directorio telefónico de números de emergencia? | Sí |
| `S1-34` | ¿Dispone de instalaciones eléctricas (contactos eléctricos) en el lugar de trabajo que utili… | Sí |
| `S1-35` | ¿En el lugar de trabajo que destinaría para el Teletrabajo utilizaría multicontactos para co… | No |
| `S1-36` | ¿En el lugar de trabajo que utilizaría para realizar Teletrabajo las instalaciones eléctrica… | No |
| `S1-37` | ¿En el lugar de trabajo que ocuparía para el Teletrabajo, el equipo de las TIC se conectaría… | No |
| `S1-38` | ¿En el lugar de trabajo que destinaría para realizar el Teletrabajo, sabe si el contacto elé… | Sí |
| `S1-39` | ¿Dispone de un extintor disponible cerca de su lugar de trabajo que destinaría para realizar… | Sí |
| `S1-40` | ¿En el espacio físico que destinaría como lugar de trabajo, se perciben olores de sustancias… | No |
| `S1-41` | ¿Usted o alguien más fuma dentro del lugar de trabajo que utilizaría para realizar el Teletr… | No |
| `S1-42` | ¿En el lugar de trabajo que usted ocuparía para realizar el Teletrabajo, se concentran los o… | No |
| `S1-43` | ¿Cuenta en el lugar de trabajo que destinaría para el Teletrabajo con alguna silla o asiento… | Sí |
| `S1-44` | ¿La mesa, escritorio o superficie del lugar de trabajo que ocuparía para el Teletrabajo tien… | Sí |
| `S1-45` | ¿Usted estaría dispuesto a realizar Teletrabajo para su patrón? | Sí |
| `S1-46` | ¿En caso de que usted realizara Teletrabajo, tendría inconvenientes para llevarlo a cabo sin… | No |
| `S1-47` | ¿En el supuesto de que usted realizara Teletrabajo, considera que se sentiría apartado y deg… | No |
| `S1-48` | ¿Usted considera que si, incursiona en la modalidad de Teletrabajo, éste podría interferir c… | No |
| `S2-01` | ¿El lugar de trabajo que usted determinó para el Teletrabajo tiene iluminación natural? (luz… | Informativa |
| `S2-02` | ¿El lugar de trabajo que usted utiliza para realizar el Teletrabajo cuenta con lámparas inca… | Informativa |
| `S2-03` | ¿El lugar de trabajo que usted destinó para realizar el Teletrabajo cuenta con lámparas de f… | Informativa |
| `S2-04` | ¿Considera que la luz con la que realiza Teletrabajo en su lugar de trabajo es molesta para… | No |
| `S2-05` | ¿La posición de las lámparas, focos o luminarias del lugar de trabajo en las que realiza Tel… | No |
| `S2-06` | ¿El lugar de trabajo que usted utiliza para el Teletrabajo tiene lámparas de LED? | Informativa |
| `S2-07` | ¿En el lugar de trabajo, donde coloca los equipos de las TIC, evita el deslumbramiento de la… | Sí |
| `S2-08` | ¿El lugar de trabajo en donde lleva a cabo el Teletrabajo cuenta con ventanas cerca? | Informativa |
| `S2-09` | ¿En caso de que cuente con ventanas el lugar de trabajo en donde realiza Teletrabajo, éstas… | Informativa |
| `S2-10` | ¿En el lugar de trabajo en el que desarrolla Teletrabajo cuenta con aire acondicionado? | Informativa |
| `S2-11` | ¿La temperatura del lugar de trabajo que emplea para el Teletrabajo le parece muy fría como… | No |
| `S2-12` | ¿Considera que la temperatura de lugar de trabajo que utiliza para el Teletrabajo es alta co… | No |
| `S2-13` | ¿La altura del lugar de trabajo que emplea para el Teletrabajo es de más de dos metros y medio? | Sí |
| `S2-14` | ¿En el lugar de trabajo que ocupa para realizar Teletrabajo se percibe ruido de la calle, de… | No |
| `S2-15` | ¿El espacio destinado al Teletrabajo está alejado del ruido o de otras distracciones que pud… | Sí |
| `S2-16` | ¿En el lugar de trabajo que escogió para realizar el Teletrabajo se percibe un volumen alto… | No |
| `S2-17` | ¿El área que destinó como lugar de trabajo está separada de otras áreas por elementos físico… | Sí |
| `S2-18` | ¿La superficie de su plano de trabajo (mesa, escritorio, barra u otro elemento similar) le p… | Sí |
| `S2-19` | ¿En caso de que sus tareas de Teletrabajo, requiera de transcripción de documentos impresos… | Sí |
| `S2-20` | ¿En el lugar de trabajo los elementos de uso más frecuente como teléfono, documentos o acces… | Sí |
| `S2-21` | ¿Los pasillos y las puertas de su lugar de trabajo se encuentran permanentemente despejados… | Sí |
| `S2-22` | ¿El espacio de trabajo se mantiene generalmente libre de basura, desorden y líquidos inflama… | Sí |
| `S2-23` | ¿Los pisos del lugar de trabajo que determinó para el Teletrabajo están despejados y libres… | Sí |
| `S2-24` | ¿En su caso, los cables que alimentan los equipos de las TIC se encuentran en el camino de a… | No |
| `S2-25` | ¿En el lugar de trabajo que ocupa para realizar Teletrabajo existe mobiliario con esquinas o… | No |
| `S2-26` | ¿Cuenta con un directorio telefónico de números de emergencia cerca de su lugar de trabajo? | Sí |
| `S2-27` | ¿Dispone en su lugar de trabajo de instalaciones eléctricas (contactos eléctricos) para cone… | Sí |
| `S2-28` | ¿En su lugar de trabajo para el Teletrabajo utiliza multicontactos para conectar el equipo TIC? | No |
| `S2-29` | ¿Las instalaciones eléctricas de su lugar de trabajo para realizar Teletrabajo en tiene cabl… | No |
| `S2-30` | ¿Los equipos utilizados para el Teletrabajo se conectan a un contacto eléctrico a una distan… | No |
| `S2-31` | ¿Sabe usted si el contacto eléctrico que emplea para alimentar el equipo de las TIC en su lu… | Sí |
| `S2-32` | ¿Los contactos eléctricos de su lugar de trabajo se encuentran dañados, en malas condiciones… | No |
| `S2-33` | ¿En el lugar de trabajo donde realiza el Teletrabajo, requiere del uso de cables conocidos c… | No |
| `S2-34` | ¿Cuando no está en uso el equipo que usa para realizar el Teletrabajo, lo mantiene apagado o… | Sí |
| `S2-35` | ¿Dispone en el lugar de trabajo de un regulador contra sobretensiones eléctricas para proteg… | Sí |
| `S2-36` | ¿En el espacio físico que dispone como lugar de trabajo para realizar Teletrabajo se percibe… | No |
| `S2-37` | ¿Usted o alguien más fuma dentro del lugar de trabajo que tiene destinado como lugar de trab… | No |
| `S2-38` | ¿El lugar de trabajo está ubicado en un ambiente libre de humo? | Sí |
| `S2-39` | ¿La mesa, escritorio o superficie del lugar de trabajo que utiliza para el Teletrabajo tiene… | Sí |
| `S2-40` | ¿La silla que utiliza en su lugar de trabajo cuenta con cinco ruedas? | Sí |
| `S2-41` | ¿Es posible ajustar la altura del asiento de su silla con respecto al piso? | Sí |
| `S2-42` | ¿Si es posible ajustar la altura del asiento de su silla con respecto al piso, al hacerlo la… | Sí |
| `S2-43` | ¿Su espalda queda completamente apoyada en el respaldo de la silla? | Sí |
| `S2-44` | ¿Es posible ajustar el respaldo de su silla para apoyar completamente la espalda? | Sí |
| `S2-45` | ¿El respaldo de la silla es capaz de soportar la curva lumbar de la espalda? (curva lumbar o… | Sí |
| `S2-46` | ¿La silla del lugar de trabajo en donde realiza el Teletrabajo cuenta con descansabrazos? | Sí |
| `S2-47` | ¿En caso de que la silla del lugar de trabajo en donde realiza el Teletrabajo tenga descansa… | Sí |
| `S2-48` | ¿Cuando está sentado la silla, en su lugar de trabajo, requiere de un reposapiés para apoyar… | No |
| `S2-49` | ¿Considera usted que requiere de un apoyo lumbar en la silla del lugar de trabajo, es decir… | No |
| `S2-50` | ¿Cuando realiza trabajos con equipos de TIC tiene posturas prolongadas en la misma posición,… | No |
| `S2-51` | ¿El lugar de trabajo se adapta al espacio físico, al equipo y al material relacionado con el… | Sí |
| `S2-52` | ¿La brillantez que emiten los equipos para el manejo de las TIC le fatiga la vista al realiz… | No |
| `S2-53` | ¿Al momento de teletrabajar en el lugar de trabajo, la distancia entre el monitor del equipo… | Sí |
| `S2-54` | ¿De acuerdo con las tareas a realizar en el lugar de trabajo, cuenta con un ratón o mouse in… | Sí |
| `S2-55` | ¿En el lugar de trabajo la distancia entre el teclado y los codos es insuficiente, de tal ma… | No |
| `S2-56` | ¿El lugar de trabajo es suficientemente amplio para que realice movimientos en el Teletrabaj… | Sí |
| `S2-57` | ¿Tiene suficiente espacio debajo de la mesa de trabajo para evitar que las piernas se golpeen? | Sí |
| `S2-58` | ¿Hay espacio en su lugar de trabajo para descansar los brazos cuando no teclea? | Sí |
| `S2-59` | ¿Al realizar Teletrabajo, tiene inconvenientes para desarrollarlo sin que ello afecte o inte… | No |
| `S2-60` | ¿Conoce algo sobre los ciclos de descanso de acuerdo con su actividad en el Teletrabajo? | Sí |
| `S2-61` | Considera usted que el Teletrabajo le genera ansiedad, irritabilidad, estados depresivos, o… | No |

---

## Cómo se prueba

1. **Textos literales.** La prueba del instrumento afirma que las 109 preguntas del Apéndice 3 son idénticas a
   [`texto-de-referencia.md`](texto-de-referencia.md), en orden y con su sección, como se hizo con las Guías de la
   NOM-035. Las preguntas agregadas por Humetría se prueban aparte y nunca se mezclan con las del DOF.
2. **Límite del alcance.** 40 % no es teletrabajo y 41 % sí (C-01).
3. **Polaridad.** Una prueba por pregunta: la respuesta favorable no marca y la otra sí; «No aplica» e «Informativa»
   nunca marcan (C-10).
4. **Validación.** Sin validación no hay lugar apto; «No validado» bloquea; una Sección II vencida vuelve el lugar
   «Pendiente» (C-12 a C-14).
5. **Contenido mínimo.** Cada aviso (6.3, 6.7, 6.9) y cada documento (5.2, 5.6) rechaza guardarse sin sus campos
   obligatorios.
6. **Aislamiento y privacidad.** Pruebas de tenant (A no ve a B), del rol de la Comisión (solo su centro) y de que
   `viewer` no recibe datos personales en las props.

## Lo que la plataforma no cubre (por ahora)

- **Exámenes médicos** de la NOM-030 [DOF, 5.11]: se registran los avisos de accidente, no los exámenes.
- **Unidades de inspección y dictamen** [DOF, 9]: son opcionales para la empresa. Humetría puede guardar el dictamen
  como documento, pero no lo emite.
- **Contrato de teletrabajo** [LFT, 330-B], **contrato colectivo o reglamento interior** [LFT, 330-C y 330-D] y el
  **pago de internet y luz** [LFT, 330-E fr. III]. Son obligaciones de la LFT, no de la NOM-037, y la STPS lo aclaró:
  «las obligaciones especiales contenidas en el artículo 330-E, de la Ley, en lo que hace al pago de servicios, no son
  motivo de esta NOM» [RC, promovente 3, l. 2299].
- **Impartir la capacitación** (C-24) y **redactar textos legales** (C-19, C-21).
- **Método ROSA** del Apéndice 5: es informativo y requiere observar la postura. No se automatiza.

## Si la norma cambia

1. **La lista está versionada.** Cada aplicación guarda la versión con la que se contestó (`dof-2023-06-08`). Una
   versión nueva no cambia las listas anteriores ni sus validaciones.
2. **Se revisa cada criterio**: ¿cambió el texto citado? Si es «Interpretación», ¿la nueva norma o un criterio de la
   STPS lo resuelve distinto? Se actualiza aquí con su nueva fuente, se sube la versión y se agrega una entrada al
   historial.
3. **Se guarda la fuente nueva** en [`fuentes/`](fuentes/README.md), con fecha de descarga y huella SHA-256.
4. **Las pruebas se escriben contra el anexo**: un cambio en la lista obliga a cambiar primero el anexo y las pruebas,
   y después el código.
5. **Revisión periódica.** Al planear cada versión de Humetría, revisar el DOF y el Programa Nacional de
   Infraestructura de la Calidad del año. Al 2026-10-30 no hay una modificación de la NOM-037 en curso.

## Historial

| Versión | Fecha | Cambio |
|---|---|---|
| 0.2 | 2026-10-30 | Decisiones del usuario: lista editable desde el principio con versiones (C-07, C-10), periodicidad predefinida o propia con 12 meses por defecto (C-14), rol de la Comisión (C-17), política en PDF (C-19, C-21), solo registro de capacitación (C-24), consentimiento base (C-25). Un solo PR para toda la NOM-037. Queda por aprobar el anexo. |
| 0.1 | 2026-10-30 | Borrador con base en el DOF del 08/06/2023, las respuestas a los comentarios (DOF 15/05/2023), la LFT vigente, la NOM-019 y la LFPDPPP. Pendiente de aprobación de los criterios marcados ⚑. |
