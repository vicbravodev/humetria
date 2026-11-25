# Criterios de aplicación de la reforma de jornada y registro electrónico en Humetría

Versión 0.5 · 24 de noviembre de 2026 · Base: Decreto DOF 01/05/2026 que reforma la Ley Federal del Trabajo
«en materia de reducción de la jornada laboral» (texto literal en [`fuentes/`](fuentes/README.md)).

Este documento explica **cómo aplica Humetría la reforma y por qué**. Manda sobre cualquier otro documento del
repositorio en cuanto a su interpretación: si el código o un diseño lo contradicen, se corrige el código o el diseño, o
se actualiza este documento con su justificación.

> **Estado: aprobado** por el usuario el 2026-11-07 (incluidos 2.8 y 7.1). Falta que la STPS publique las
> disposiciones generales del art. 132-XXXIV (ámbito de aplicación y excepciones). Hasta entonces el sistema **supone que todas las personas
> trabajadoras deben registrar su jornada** (la lectura más estricta) y guarda las reglas como datos configurables.
> Las páginas públicas dicen la fecha como «entra en vigor» y que la STPS definirá a qué empresas aplica y si será gradual
> (la prensa reporta una adopción gradual, empezando por grandes y medianas); la multa del art. 994-IV Bis aplica a quien
> la STPS determine como obligado. Aprobado por el usuario el 2026-11-17.

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
semana. Los topes son de la ley y no se configuran por empresa ni por centro: rebasarlos no se bloquea, se registra, se
paga y se avisa. Rebasar el tope semanal del año enciende su propia bandera desde el primer minuto, no solo cuando el
triple pasa de 4 h (2.4). Una marca sin conexión cuenta para las horas extra y los topes con su hora contada (3.3.1), también
mientras está por verificar.

**2.4 Tiempo extra que rebasa el tope: triple** · Norma · art. 68

> «[...] no podrá ser mayor de cuatro horas a la semana y obliga a la persona empleadora a pagar un doscientos por
> ciento más [...]» · «La suma de las jornadas ordinaria y extraordinaria, en ningún caso podrá ser mayor a doce horas
> diarias.»

**2.5 Día de descanso y prima dominical** · Norma · art. 69 («por cada seis días de trabajo [...] por lo menos, un día
de descanso con goce de salario íntegro») y art. 71 (prima dominical de «un veinticinco por ciento, por lo menos»).

**2.6 Distribución del horario por acuerdo** · Norma · art. 58, segundo párrafo: la jornada «podrá ser distribuida de
común acuerdo». El horario de cada persona es un dato propio, no uno por empresa.

*Vigencia del horario* · Humetría · decisión del dueño, 2026-11-23 · el horario de una persona **tiene fecha de inicio** y
un horario nuevo **no borra el anterior**: cada día se calcula con el horario vigente ese día (el último que empezó en esa
fecha o antes). Así el reporte para la autoridad de un periodo pasado da lo mismo hoy que dentro de un año, aunque el
horario cambie después. RH elige la fecha de inicio (hoy por omisión, en el calendario de la empresa); puede ser una fecha
pasada, para corregir un registro, y entonces la bitácora guarda el horario que regía ese día y el que quedó. Un horario **no puede empezar** dentro de una semana que la persona ya confirmó u objetó, ni antes de ella (cambiaría lo
que firmó y el reporte de esas semanas); la primera fecha posible es el lunes siguiente a la última semana confirmada u
objetada, y las semanas sin responder sí se pueden corregir. Un lote de personas empieza todo en una sola fecha. Los
horarios que existían antes de las fechas de inicio valen «desde siempre». Un día anterior al primer horario de una persona
no tiene horario (se cuenta como quien no lo tiene: 8 h diarias sin descanso). Un turno que cruza la medianoche el día del
cambio pertenece al día en que empezó, y se lee con el horario de ese día. **Fuera de la vigencia**, por ahora: la
tolerancia de retardo y la política de la empresa (4.1), que se leen siempre con su valor de hoy. El tipo de turno de una
semana con cambio a mitad se toma del horario vigente su domingo (hoy no cambia ningún cálculo, 2.9).

*Hora de entrada y retardo* · Interpretación · la LFT no fija una tolerancia de retardo ni obliga a registrar la hora de
entrada. Humetría deja que RH escriba, si quiere, la hora de entrada de cada día del horario y que la empresa fije una
**tolerancia** (por omisión 10 minutos, de 0 a 120). El retardo es la diferencia entre esa hora y la **primera marca de
entrada** del día, cuando pasa de la tolerancia (con la tolerancia exacta no hay retardo). **Solo se muestra**: no
descuenta minutos, no genera faltas y no cambia lo trabajado ni lo programado. Una marca de entrada sin conexión cuenta para
el retardo con su hora contada (3.3.1), también mientras está por verificar. Un día de descanso, con una ausencia
aprobada, sin marca o sin hora de entrada no tiene retardo. La tolerancia es de la empresa porque la ley no la fija.

**2.7 Sin merma salarial** · Norma · transitorio séptimo: la reducción no baja sueldos, salarios ni prestaciones. Para
el costo en vivo de horas extra, la base del cálculo no cambia por la reducción.

**2.8 Interpretación abierta: dónde empieza el «triple»** · Interpretación · el art. 68 dice «que supere lo establecido
en el artículo 66», y el art. 66 se alcanza gradualmente (9 h en 2027). Humetría toma como límite del tiempo doble **el
tope de ese año** y marca como triple lo que lo supere, hasta 4 h por semana. Si la STPS o un criterio posterior lo
leen distinto, se cambia aquí y en la tabla por año.

**2.9 Qué es tiempo extra en una semana** · Interpretación · arts. 58, 59, 66, 68 y 69 · *pendiente de revisión del
usuario*

La ley fija los topes pero no dice cómo se cuenta el extra de cada día. Humetría lo cuenta así, en minutos y sin
redondear:

- El tiempo extra de un día es lo trabajado **por encima de lo programado ese día**. Un día corto no se compensa con el
  extra de otro.
- Lo trabajado en un **día de descanso** se cuenta aparte (para el pago del art. 73) y no es tiempo extra.
- Lo programado es ordinario aunque un día pase del máximo diario del art. 61, porque el horario se distribuye por
  acuerdo (2.6). Pero si la suma de lo ordinario de la semana rebasa el **máximo semanal del año** (2.1), el exceso pasa
  a tiempo extra, del día en que se cruza el máximo en adelante.
- Consecuencia para los turnos **nocturno y mixto** (*pendiente de revisión del usuario*): el tipo de turno no cambia el
  cálculo. Una persona de turno nocturno programada 6 × 8 h en 2026 (48 h) no tiene tiempo extra; la lectura estricta del
  art. 61 (7 h la nocturna) le daría 1 h extra cada día, 6 h a la semana. Si se adopta esa lectura, se cambia aquí y en
  la calculadora.
- Lo trabajado en día de descanso no cuenta para el tope semanal de tiempo extra ni para el aviso de más de 4 días con
  extra.
- El extra de la semana se paga **doble hasta el tope semanal del año** (2.3) y **triple lo que lo rebase** (2.4, 2.8).
  Si el triple pasa de 4 h a la semana, se avisa: esos minutos siguen contando como triple, porque trabajados se pagan.
- Se avisa también cuando un día pasa de 12 h trabajadas (incluso en descanso), cuando un día tiene más de 4 h extra y
  cuando hay extra en más de 4 días de la semana.
- Un **día sin cubrir** (5.2) no es un día corto ni cambia el cálculo del extra: solo impide confirmar la semana. Quien no
  tiene horario vigente ese día (ninguno, o un día anterior a su primer horario, 2.6) no tiene días sin cubrir.

## 3. Qué registra Humetría y qué no

**3.1 Inicio y fin de jornada** · Norma (1.1). **3.2 Pausa de comida y descansos** · Humetría: la ley no los pide, pero sin
ellos no se puede calcular tiempo efectivo ni horas extra de forma defendible. Se registran como marcas de pausa. Las pausas también pueden marcarse sin conexión, con la regla de 3.3.1.

**3.3 Hora del servidor** · Humetría: la hora de una marca en línea la pone el servidor; la de una marca sin conexión la
declara la persona y el servidor la acota (3.3.1). El reloj del teléfono nunca es prueba.

**3.3.1 Marca sin conexión** · Humetría · una marca hecha sin conexión es válida, cuenta en horas y horas extra, y lleva la
etiqueta «Sin conexión · por verificar» hasta que Recursos Humanos la revisa. La persona declara la hora y el servidor la
acota: no puede ser **anterior** al último contacto del teléfono con el servidor (el boleto), al inicio de la ventana del QR
del centro si lo leyó (el QR prueba que el código existía: la marca es «no antes de» esa ventana, no «a esa hora»), ni a la
marca anterior; y no puede ser **posterior** a la recepción ni a la marca siguiente. Cuenta la hora declarada llevada a ese
intervalo, si entre el boleto y la recepción pasaron hasta 72 horas; si pasaron más, cuenta la cota probada (la más temprana
posible) hasta que Recursos Humanos la verifique o la corrija. La hora declarada es **no probada**. Un QR leído más de 30
minutos antes de la hora declarada no sube la evidencia (la marca queda como sin QR). Si el intervalo queda vacío, la marca no
se acepta. Se señalan para revisión primero las marcas con intervalo de más de 240 minutos, con un QR viejo, sin posición,
fuera de la zona del centro, en un centro sin zonas configuradas (`zones_not_configured`: hay posición, pero nada contra qué compararla) o cuya hora contada difiere de la declarada. El boleto vale hasta 35 días (el horizonte de la
corrección); más viejo, no se acepta. Una marca declarada después de que se revocó la llave que la firmó no se acepta. Toda
marca no aceptada queda en el teléfono como «No aceptada» con su motivo; si traía boleto y firma válidos, también queda
registrada para Recursos Humanos. Nada aceptable se pierde en silencio (salvo que se borren los datos del teléfono antes de
entregarla). *Pendiente de revisión del usuario: los umbrales de 240 minutos, 30 minutos, 72 horas y 35 días.*

**3.4 Ubicación** · Humetría · se guardan **las coordenadas, su precisión y la zona de la geocerca** donde se marcó, para
poder demostrar dónde se hizo dentro de un centro grande. En un centro que marca **en campo** (4.5) se guardan las
coordenadas y su precisión aunque la marca caiga fuera de toda zona. Es un dato personal; por eso se informa en la aceptación
(criterio 5.1), solo se captura **al marcar** (nunca en segundo plano ni entre marcas; sin conexión se toma en el teléfono al marcar, viaja con
la marca y se guarda igual), solo lo ven los roles
autorizados, y se conserva por el plazo del criterio 7. Base: LFPDPPP (copia en
`../nom-037/fuentes/lfpdppp-extracto.txt`: arts. 3-VI, 7, 8 y 9).

## 4. A quién le toca registrar, y cómo

**4.1 Todas las personas empleadas, por defecto** · Interpretación (1.2). Una **política por empresa** define por grupo
(`StaffGroup`) y por modalidad (presencial / teletrabajo) qué métodos de marcaje son válidos. Es la base de todos los
centros; un centro solo puede cambiar **cómo se marca** en él (4.5). La conservación, la tolerancia de retardo y el
personal de confianza siguen siendo de la empresa.

**4.2 Presencial** · el método fuerte es **QR rotativo del centro + geocerca** a la vez; solo uno de los dos es evidencia
más débil (criterio 5.3). **Por defecto se bloquea marcar fuera de toda zona**; la empresa puede cambiar su política a
«permitir y señalar» (la marca queda como evidencia media y señalada). Aun bloqueando, la persona puede pedir una corrección. Sin conexión **no se bloquea** fuera de zona: la marca se señala
(3.3.1; en un centro sin zonas, como «sin zonas configuradas»), porque la persona no puede saber si la zona la aceptaría.

**4.3 Teletrabajo (NOM-037)** · sin geocerca; marca desde su dispositivo con la confirmación del dispositivo vinculado.

**4.4 Personal de confianza** · son empleados (`Employee`) como todos; **no necesitan cuenta de usuario**. Registran desde
el navegador de la computadora con el mismo dispositivo vinculado o con una llave de acceso en esa computadora. Si luego
la STPS los exceptúa, basta cambiar la política.

**4.5 Centro en campo** · Humetría · cada centro marca **igual que la empresa** (por omisión) o **en campo** (una obra,
rutas, visitas). En campo no se pide zona ni QR: la persona marca desde su celular donde esté y se guarda su ubicación
(3.4) sin zona que la limite. El QR sigue disponible y es opcional (uno que no verifica se rechaza). Un centro en campo
no necesita zonas ni pantalla para que su gente marque, y una persona nueva en él hereda el modo sin tocar su horario; la
modalidad remota por persona (4.3) queda para quien teletrabaja en un centro presencial y manda sobre el modo del
centro. La hora sigue siendo la del servidor (3.3). Cambiar el modo de un centro va a la bitácora.

## 5. Acuerdo y evidencia

**5.1 Aceptación antes de la primera marca** · Humetría · antes de su primera marca, la persona acepta el acuerdo de
registro electrónico: medios, datos que se registran (incluida la ubicación), cómo se corrige, quién ve, plazo de
conservación y su valor de prueba. Lo acepta al marcar, con su propia llave (una firma de esa sola acción); sin la
aceptación de la **versión vigente** no puede marcar. Si el texto cambia (por ejemplo, el plazo de conservación), la
siguiente marca pide aceptar la versión nueva. Se guarda el texto, su versión, la llave con que firmó y el momento. Sin conexión: la marca puede guardarse en el teléfono
hasta entregarse; la hora la declara la persona y el servidor la acota (3.3.1); la posición se captura en el teléfono; y existe
un plan B con una llave propia del teléfono para quien su llave de acceso no firma sin conexión. Una versión del acuerdo
publicada mientras la persona estaba sin conexión no rechaza una marca anterior.

**5.2 Conformidad semanal** · Humetría · cada semana la persona revisa su registro y lo **confirma** u **objeta** con
motivo. Lo confirmado es contenido «acordado» (1.3). Lo objetado queda a la vista de RH hasta resolverse: RH ve el
motivo, recibe un aviso y la resuelve con una nota que la persona lee; la semana vuelve a pendiente para que la persona la
confirme o la objete otra vez. Lo acordado incluye las ausencias aprobadas de la semana: aprobar o cancelar una ausencia,
o corregir una marca, reabre la semana confirmada u objetada; una marca sin conexión que llega después de confirmar una
semana la reabre, y la persona la confirma de nuevo.

*Días sin cubrir* · Humetría · no se confirma una semana con **días sin cubrir**: un día laborable de su horario vigente
ese día, ya terminado, sin marcas (o con una entrada sin salida), que no sea de descanso, festivo ni anterior a su fecha
de alta, ni tenga una ausencia aprobada en Vacaciones y permisos. Una entrada sin salida del día de ayer aún no cuenta como día
sin cubrir: puede ser un turno de noche en curso, y «Día abierto» detiene la semana hasta que se marque la salida. Se suma a los bloqueos que ya existen (`no_marks`, y «Día
abierto», `day_open`: la última jornada sin marca de fin): «Día abierto» habla de una jornada empezada; «día sin cubrir», de un
día sin ninguna marca. Aplica a las empresas con el módulo de Vacaciones y permisos (sin él nadie puede justificar el día, y no aplica); la plataforma puede apagar el bloqueo (`attendance.week.block_uncovered_days`)
y entonces solo lo registra. La persona puede objetar la semana, pedir una corrección (agregar la marca que falta) o pedir a
Recursos Humanos que registre la ausencia como permiso. *Decisión del dueño, 2026-11-24: solo con Vacaciones y permisos.*

**5.3 Nivel de evidencia por marca** · Humetría · cada marca guarda cómo se hizo (QR más geocerca, solo geocerca, solo QR,
navegador de confianza, remoto, en campo, corrección) y el reporte lo muestra. «Fuerte» es la marca **completa para su
modo**: QR más geocerca en un centro presencial; en un centro en campo, una ubicación con precisión dentro del máximo de
la política (método `field`). En campo sin ubicación utilizable (ninguna, o menos precisa que el máximo, que igual se
guarda) es «Media» (método `field_unlocated`), o «solo QR» si escaneó un QR válido. El reporte señala esos días con
«Marcó en campo», como información y no como aviso. Las marcas sin conexión usan el método `offline_qr` («Media») o
`offline_declared` («Básica»): la evidencia es el mínimo entre la base de tiempo y la identidad (llave de acceso fuerte,
llave del teléfono media), y la revisión de Recursos Humanos no la sube. *Pendiente de revisión del usuario: el plan B con
evidencia media.*

**5.4 Registro inalterable** · Humetría · las marcas son de **solo inserción** (trigger de Postgres, como `audit_entries`).
Una corrección es una **marca nueva** con autor, motivo y referencia a la original; la original nunca se edita ni se
borra. Esto responde a lo que se exige para sostener el valor probatorio: identificar quién registró y cuándo, detectar
alteraciones, y explicar quién puede modificar y qué controles lo impiden.

*Revisión de una marca sin conexión* · Humetría · la revisión es un registro nuevo enlazado a la marca, de solo inserción; la
vigente es la última. «Correcta» solo agrega esa revisión y no cambia la hora. «Incorrecta» siempre va con su corrección
(anular la marca o una marca nueva a la hora que escribe Recursos Humanos), en una sola operación.
Mientras no se revisa, la marca cuenta. Recursos Humanos se entera de las que tienen banderas o llevan más de 7 días sin
verificar (`attendance.offline.review_age_days`) en «Necesita tu atención» del panel y por Humi, que solo da el conteo;
no hay correo.

## 6. Reporte para la autoridad

**6.1** · Norma (1.1) · Un reporte por centro de trabajo y periodo con: persona, fecha, hora de inicio y de fin, pausas,
horas ordinarias y extra, nivel de evidencia, correcciones con su motivo y estado de la conformidad. Cada descarga se
registra en la bitácora (acción sensible). El reporte muestra la etiqueta «Sin conexión» con la llave que firmó (también en
una marca corregida, por su marca original), si la marca sigue por verificar o Recursos Humanos ya la verificó, y cuántos días
siguen por verificar (cuentan en las horas mientras tanto). **Nombra a quien revisó cada marca sin conexión y cuándo**
(decisión del dueño, 2026-11-24): la persona de RH, la decisión (verificada o incorrecta, esta última siempre con su
corrección) y la fecha y hora local de esa revisión, la última vigente, por marca, en la pantalla y en el CSV (columnas
«Revisión sin conexión», «Revisó» y «Revisada el»); si esa persona ya no es miembro de la empresa dice «Persona de RH (ya
no tiene acceso)». Nunca la nota de la persona ni la de RH. A quien corrige no se le nombra (dice «por RH»), y la bitácora
de la empresa no cambia. Una entrega no aceptada no es una marca y no entra al reporte;
Recursos Humanos la ve en Correcciones, con su motivo.

## 7. Conservación

**7.1** · Interpretación · el art. 804 de la LFT obliga a conservar los registros de asistencia durante la relación
laboral y un año después (dato de fuente secundaria; **falta cotejarlo con el texto vigente de la LFT**). La reforma
no fija un plazo nuevo. Humetría propone conservar **mientras dure la relación y un año después como mínimo**, y que la
empresa pueda ampliarlo; el plazo se dice en la aceptación. *Por decidir con el usuario y revisar si la STPS fija otro.* La nota de la persona en una marca sin conexión (hasta 500
caracteres) se conserva con el registro, solo la ve Recursos Humanos y nunca va a logs; las marcas sin conexión se conservan
como las marcas, sin poda en esta versión.

## 8. Acceso de la persona a su registro

**8.1** · Humetría · la persona ve siempre su propio registro, el detalle de cada marca y sus correcciones. La ley no lo
exige, pero sin esto no se puede hablar de «acuerdo». La persona ve en su teléfono y en Mi semana el estado de cada marca sin conexión:
guardada sin enviar, por verificar, verificada, incorrecta (con la nota de Recursos Humanos) o no aceptada (con su motivo).

## Historial

- 2026-11-07 · v0.1 · borrador a partir del decreto del DOF 01/05/2026. Decisiones del usuario incorporadas: enlace
  personal con dispositivo vinculado y seguro, sin correo ni contraseña; personal de confianza como `Employee`; se
  guardan las coordenadas y la zona de la geocerca; QR rotativo y geocerca en la primera fase.
- 2026-11-07 · v0.2 · se agrega 2.9 (qué es tiempo extra en una semana), pendiente de revisión del usuario.
- 2026-11-07 · v0.3 · cambios hechos durante la construcción, por decisión del revisor o del constructor. Los marcados
  **⚠ pendiente del usuario** esperan su decisión; el código sigue lo descrito hasta entonces.
  - **2.9** (agregado en v0.2) quedó así en el cálculo: lo programado es ordinario aunque pase del máximo diario del
    art. 61; solo el máximo semanal del año lo vuelve extra. El tipo de turno **no cambia el cálculo**: un turno nocturno
    o mixto programado por encima de 7 h o 7 h 30 min no genera extra por ese motivo (la lectura estricta del art. 61 sí
    lo haría). Lo trabajado en día de descanso va aparte y no cuenta para los topes de extra. Desde v0.3 el formulario de
    horarios avisa, sin bloquear, cuando un día nocturno o mixto pasa de su máximo diario y cuando el extra de la semana
    pasa del tope semanal del año. **⚠ pendiente del usuario:** confirmar esta lectura de 2.9 y su consecuencia para los
    turnos nocturno y mixto.
  - **Sin horario** (2.9): un día con 0 minutos en el horario es día de descanso; una persona **sin horario acordado** se
    cuenta con 8 h cada día y sin día de descanso hasta que RH fije el suyo. El panel la señala con «Sin horario».
    **⚠ pendiente del usuario:** confirmar esos valores por defecto.
  - **Llaves sincronizadas** (5.1, 5.4): se aceptan las llaves de acceso que el teléfono respalda en la cuenta de la
    persona (iCloud, Google); rechazarlas impediría vincular la mayoría de los teléfonos. Consecuencia: la llave está
    ligada a la cuenta de llaves de la persona y se desbloquea con su huella, rostro o PIN, **no se puede demostrar que
    viva en un solo teléfono**. Se guarda si la llave puede respaldarse y RH lo ve por dispositivo.
  - **RH no ve el enlace de alta** (5.1): si la persona tiene correo, su enlace para vincular el teléfono solo le llega a
    ella; RH lo recibe para entregarlo en mano solo cuando la persona no tiene correo, y la bitácora dice que RH lo
    entregó y a qué dirección (enmascarada) se envió cada uno.
  - **Aviso de dispositivo vinculado** (5.1): cada vez que se vincula un dispositivo, la persona con correo recibe un aviso
    («si no fue usted, avise a RH»).
  - **Conformidad ligada a lo que se vio** (5.2): confirmar u objetar una semana lleva la huella (sha-256) de las marcas
    vigentes que la persona leyó; si cambiaron entre su lectura y su respuesta, se rechaza y vuelve a leer. La huella
    se guarda con la respuesta y en la bitácora: con las marcas de solo inserción, reconstruye exactamente lo acordado.
  - **Día abierto** (5.2): no se puede confirmar una semana mientras su última jornada siga sin marca de fin
    (`day_open`); la persona puede objetarla o pedir una corrección.
  - **Reporte** (6.1): además de lo listado, cada día dice qué se corrigió (hora reemplazada, marca anulada o agregada,
    quién la pidió y su motivo) y si la semana se confirmó con su huella; las horas se reparten con el horario vigente al
    generarlo. En pantalla se muestra un periodo de **hasta 93 días**; uno de 94 a 366 días se ofrece solo en CSV, que
    acepta **hasta 366 días** (ambos valores en `config/attendance.php`). Ver el reporte en pantalla (su primera página,
    sin búsqueda) y cada descarga van a la bitácora; ofrecer solo el CSV no lee marcas y no se anota.
  - **Centro en campo** (4.1, 4.5, 5.3), 2026-11-16, decisión del usuario: la política sigue siendo una por empresa y
    cada centro solo elige su modo; «Campo» es un método propio, no «remoto» (que es teletrabajo, NOM-037). Con buena
    ubicación cuenta como Fuerte (aprobado por el usuario); sin ubicación utilizable baja a Media (propuesta del
    constructor, **⚠ pendiente del usuario**). Se escoge que en campo el personal de confianza también marque con su
    ubicación en vez del navegador sin ubicación. El texto del acuerdo (5.1) se amplió para decir que en campo la ubicación
    se guarda aunque se marque fuera de zona; por ser texto nuevo, cada persona acepta la versión nueva en su siguiente
    marca.
  - **Conservación** (7.1): la empresa elige de 12 a **120 meses** después de terminada la relación. El tope de 120 lo
    fijó el constructor. **⚠ pendiente del usuario:** confirmar el tope (el criterio dice «ampliable», sin máximo).
- 2026-11-21 · decisiones del usuario (auditoría de flujos, casos H01, H02, H30): una objeción se resuelve con una nota
  de RH y la semana **vuelve a pendiente** (no queda «resuelta» sin respuesta de la persona); la objetada de quien se dio
  de baja sigue a la vista. Las ausencias forman parte de lo que la persona confirmó (1.3): aprobar o cancelar una ausencia
  reabre la semana, y la huella de la semana incluye sus ausencias. El reporte para la autoridad (6.1) no marca retardo
  en un día con ausencia aprobada (2.6), dice «Vacaciones» o «Permiso» (nunca el tipo de permiso) y señala «Marcó durante
  una ausencia».
- 2026-11-23 · v0.4 · **2.6** agrega la vigencia del horario por fecha de inicio (decisión del dueño); el cambio de horario y
  el de centro, puesto, área o nivel del expediente quedan en la bitácora con su antes y su después.
- 2026-11-24 · v0.5 · **marcas sin conexión** (propuesta del diseño `docs/superpowers/specs/2026-11-20-worker-pwa-offline-design.md`;
  cada punto marcado **⚠ pendiente del usuario** espera su decisión y el código sigue lo descrito hasta entonces):
  - **3.3 y 3.3.1**: la hora de una marca en línea la pone el servidor; la de una marca sin conexión la declara la persona y el
    servidor la acota. **⚠ pendiente del usuario:** los umbrales de 240 minutos, 30 minutos, 72 horas y 35 días.
  - **3.2, 3.4, 4.2, 2.3, 2.6, 2.9, 5.1, 5.3, 5.4, 6.1, 7.1, 8.1**: lo que cambia para la marca sin conexión (se escribe en
    cada criterio).
  - **5.1**: el plan B con una llave propia del teléfono, con evidencia media. **⚠ pendiente del usuario.** Texto del acuerdo
    nuevo: **⚠ pendiente del usuario.**
  - **5.2**: el bloqueo por días sin cubrir vale para las empresas con Vacaciones y permisos (decisión del dueño, 2026-11-24). Una marca sin conexión que
    llega a una semana objetada la deja objetada. **⚠ pendiente del usuario.**
