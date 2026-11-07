# Criterios de aplicación de la reforma de jornada y registro electrónico en Humetría

Versión 0.1 (borrador) · 7 de noviembre de 2026 · Base: Decreto DOF 01/05/2026 que reforma la Ley Federal del Trabajo
«en materia de reducción de la jornada laboral» (texto literal en [`fuentes/`](fuentes/README.md)).

Este documento explica **cómo aplica Humetría la reforma y por qué**. Manda sobre cualquier otro documento del
repositorio en cuanto a su interpretación: si el código o un diseño lo contradicen, se corrige el código o el diseño, o
se actualiza este documento con su justificación.

> **Estado: aprobado** por el usuario el 2026-11-07 (incluidos 2.8 y 7.1). Falta que la STPS publique las
> disposiciones generales del art. 132-XXXIV (ámbito de aplicación y excepciones). Hasta entonces el sistema **supone que todas las personas
> trabajadoras deben registrar su jornada** (la lectura más estricta) y guarda las reglas como datos configurables.

Cada criterio indica su **tipo**: **Norma** (lo dice la ley), **Interpretación** (la ley no lo resuelve y Humetría
adopta la lectura más apegada al texto) o **Humetría** (va más allá de la ley para que el registro sea defendible).

## 1. Qué exige la ley

**1.1 Registro electrónico de la jornada** · Norma · LFT art. 132-XXXIV (adicionado)

> «Registrar de manera electrónica la jornada laboral de cada persona trabajadora, incluyendo el horario de inicio y
> finalización; así como proporcionarlo a la autoridad cuando se le requiera.»

Humetría registra cada jornada con hora de inicio y de fin, y arma el reporte para la autoridad (criterio 6).

**1.2 Ámbito y excepciones: las define la STPS** · Norma · art. 132-XXXIV, párrafo segundo; transitorio quinto

> «La Secretaría del Trabajo y Previsión Social expedirá las disposiciones de carácter general que determinen el ámbito
> de aplicación y excepción a la obligación [...]» · «[...] entrarán en vigor a partir del 1 de enero de 2027.»

La ley **no excluye** al personal de confianza, a quien trabaja a distancia ni a las empresas pequeñas. Mientras la STPS
no publique las reglas, Humetría no excluye a nadie. El sistema guarda, por empresa, **qué grupos registran y cómo**
(criterio 4), de modo que una excepción de la STPS se aplica cambiando una política, no el código.

**1.3 El registro «hará prueba plena si se acredita que fue acordado»** · Norma · art. 132-XXXIV, párrafo tercero

> «El contenido del registro electrónico hará prueba plena si se acredita que fue acordado entre la persona
> trabajadora y empleadora.»

Es la frase que más pesa en el diseño. El registro solo vale como prueba plena si se puede **demostrar el acuerdo**.
Humetría lo hace con dos evidencias (criterio 5): la **aceptación** de la persona al vincular su dispositivo, con el
texto aceptado y su versión, y la **conformidad semanal** del registro.

**1.4 Multa** · Norma · art. 994-IV Bis: de 250 a 5,000 UMA a la persona empleadora que incumpla el 132-XXXIV.

## 2. Qué calcula Humetría sobre la jornada

**2.1 Jornada ordinaria semanal máxima, por año** · Norma · art. 59 y transitorio segundo

48 h en 2026, 46 en 2027, 44 en 2028, 42 en 2029 y 40 en 2030, «a partir del 1 de enero del año que corresponda».
Es una **tabla por año**, no una constante.

**2.2 Jornada diaria máxima** · Norma · art. 61: 8 h diurna, 7 h nocturna, 7.5 h mixta.

**2.3 Horas extra** · Norma · art. 66 y transitorio cuarto

> «El trabajo extraordinario no excederá de doce horas en una semana, las cuales podrán distribuirse en hasta cuatro
> horas diarias, en un máximo de cuatro días en ese periodo.» Se paga «un cien por ciento más».

Tope semanal de horas extra por año (transitorio cuarto): 9 en 2026 y 2027, 10 en 2028, 11 en 2029, 12 en 2030. Humetría
avisa cuando una persona se acerca o rebasa: el tope semanal del año, 4 horas extra en un día, 4 días con extras en la
semana.

**2.4 Tiempo extra que rebasa el tope: triple** · Norma · art. 68

> «[...] no podrá ser mayor de cuatro horas a la semana y obliga a la persona empleadora a pagar un doscientos por
> ciento más [...]» · «La suma de las jornadas ordinaria y extraordinaria, en ningún caso podrá ser mayor a doce horas
> diarias.»

**2.5 Día de descanso y prima dominical** · Norma · art. 69 («por cada seis días de trabajo [...] por lo menos, un día
de descanso con goce de salario íntegro») y art. 71 (prima dominical de «un veinticinco por ciento, por lo menos»).

**2.6 Distribución del horario por acuerdo** · Norma · art. 58, segundo párrafo: la jornada «podrá ser distribuida de
común acuerdo». El horario de cada persona es un dato propio, no uno por empresa.

**2.7 Sin merma salarial** · Norma · transitorio séptimo: la reducción no baja sueldos, salarios ni prestaciones. Para
el costo en vivo de horas extra, la base del cálculo no cambia por la reducción.

**2.8 Interpretación abierta: dónde empieza el «triple»** · Interpretación · el art. 68 dice «que supere lo establecido
en el artículo 66», y el art. 66 se alcanza gradualmente (9 h en 2027). Humetría toma como límite del tiempo doble **el
tope de ese año** y marca como triple lo que lo supere, hasta 4 h por semana. Si la STPS o un criterio posterior lo
leen distinto, se cambia aquí y en la tabla por año.

## 3. Qué registra Humetría y qué no

**3.1 Inicio y fin de jornada** · Norma (1.1). **3.2 Pausa de comida y descansos** · Humetría: la ley no los pide, pero sin
ellos no se puede calcular tiempo efectivo ni horas extra de forma defendible. Se registran como marcas de pausa.

**3.3 Hora del servidor** · Humetría: la hora de cada marca la pone siempre el servidor, nunca el reloj del teléfono.

**3.4 Ubicación** · Humetría · se guardan **las coordenadas, su precisión y la zona de la geocerca** donde se marcó, para
poder demostrar dónde se hizo dentro de un centro grande. Es un dato personal; por eso se informa en la aceptación
(criterio 5.1), solo se captura **al marcar** (nunca en segundo plano ni entre marcas), solo lo ven los roles
autorizados, y se conserva por el plazo del criterio 7. Base: LFPDPPP (copia en
`../nom-037/fuentes/lfpdppp-extracto.txt`: arts. 3-VI, 7, 8 y 9).

## 4. A quién le toca registrar, y cómo

**4.1 Todas las personas empleadas, por defecto** · Interpretación (1.2). Una **política por empresa** define por grupo
(`StaffGroup`) y por modalidad (presencial / teletrabajo) qué métodos de marcaje son válidos.

**4.2 Presencial** · el método fuerte es **QR rotativo del centro + geocerca** a la vez; solo uno de los dos es evidencia
más débil (criterio 5.3). **Por defecto se bloquea marcar fuera de toda zona**; la empresa puede cambiar su política a
«permitir y señalar» (la marca queda como evidencia débil). Aun bloqueando, la persona puede pedir una corrección.

**4.3 Teletrabajo (NOM-037)** · sin geocerca; marca desde su dispositivo con la confirmación del dispositivo vinculado.

**4.4 Personal de confianza** · son empleados (`Employee`) como todos; **no necesitan cuenta de usuario**. Registran desde
el navegador de la computadora con el mismo dispositivo vinculado o con una llave de acceso en esa computadora. Si luego
la STPS los exceptúa, basta cambiar la política.

## 5. Acuerdo y evidencia

**5.1 Aceptación al vincular** · Humetría · la primera vez, la persona acepta el acuerdo de registro electrónico:
medios, datos que se registran (incluida la ubicación), cómo se corrige, quién ve, plazo de conservación y su valor de
prueba. Se guarda el texto, su versión y el momento.

**5.2 Conformidad semanal** · Humetría · cada semana la persona revisa su registro y lo **confirma** u **objeta** con
motivo. Lo confirmado es contenido «acordado» (1.3). Lo objetado queda a la vista de RH hasta resolverse.

**5.3 Nivel de evidencia por marca** · Humetría · cada marca guarda cómo se hizo (QR más geocerca, solo geocerca, solo QR,
navegador de confianza, remoto, corrección) y el reporte lo muestra.

**5.4 Registro inalterable** · Humetría · las marcas son de **solo inserción** (trigger de Postgres, como `audit_entries`).
Una corrección es una **marca nueva** con autor, motivo y referencia a la original; la original nunca se edita ni se
borra. Esto responde a lo que se exige para sostener el valor probatorio: identificar quién registró y cuándo, detectar
alteraciones, y explicar quién puede modificar y qué controles lo impiden.

## 6. Reporte para la autoridad

**6.1** · Norma (1.1) · Un reporte por centro de trabajo y periodo con: persona, fecha, hora de inicio y de fin, pausas,
horas ordinarias y extra, nivel de evidencia, correcciones con su motivo y estado de la conformidad. Cada descarga se
registra en la bitácora (acción sensible).

## 7. Conservación

**7.1** · Interpretación · el art. 804 de la LFT obliga a conservar los registros de asistencia durante la relación
laboral y un año después (dato de fuente secundaria; **falta cotejarlo con el texto vigente de la LFT**). La reforma
no fija un plazo nuevo. Humetría propone conservar **mientras dure la relación y un año después como mínimo**, y que la
empresa pueda ampliarlo; el plazo se dice en la aceptación. *Por decidir con el usuario y revisar si la STPS fija otro.*

## 8. Acceso de la persona a su registro

**8.1** · Humetría · la persona ve siempre su propio registro, el detalle de cada marca y sus correcciones. La ley no lo
exige, pero sin esto no se puede hablar de «acuerdo».

## Historial

- 2026-11-07 · v0.1 · borrador a partir del decreto del DOF 01/05/2026. Decisiones del usuario incorporadas: enlace
  personal con dispositivo vinculado y seguro, sin correo ni contraseña; personal de confianza como `Employee`; se
  guardan las coordenadas y la zona de la geocerca; QR rotativo y geocerca en la primera fase.
