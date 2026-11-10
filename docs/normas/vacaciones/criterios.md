# Criterios de aplicación de las vacaciones (LFT, arts. 76 a 81) en Humetría

Versión 1 (propuesta) · 10 de noviembre de 2026 · Ley base: Ley Federal del Trabajo, texto vigente con última reforma
publicada en el DOF el 14-05-2026; artículos 76 y 78 según el decreto del DOF del 27-12-2022.

Este documento explica **cómo aplica Humetría las vacaciones y por qué**. Cada criterio dice qué hace la plataforma,
qué dice la ley (citada literal) y de dónde sale la decisión. **Manda** sobre cualquier otro documento del repositorio
en cuanto a la interpretación: si el código o un diseño lo contradicen, se corrige el código o el diseño, o se
actualiza este documento con su justificación.

Cada criterio marca su origen en el título:

- **Ley**: lo dice la LFT o el decreto, citado en [`texto-de-referencia.md`](texto-de-referencia.md).
- **Nuestro**: es una decisión de Humetría donde la ley calla o hay que elegir cómo aplicarla. Nunca se presenta en
  pantalla como texto de la ley.
- **Ley + Nuestro**: la obligación es de la ley y la forma de cumplirla es nuestra; el criterio separa una de otra.

Fuentes (copias en [`fuentes/`](fuentes/README.md)):

- **[LFT]** Ley Federal del Trabajo, PDF de la Cámara de Diputados, última reforma DOF 14-05-2026.
  <https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf>
- **[DOF-2022]** Decreto por el que se reforman los artículos 76 y 78 de la LFT, en materia de vacaciones, DOF
  27-12-2022. <https://dof.gob.mx/nota_detalle.php?codigo=5675889&fecha=27/12/2022>
- **[LFT-2022]** La LFT como estaba antes de esa reforma (última reforma DOF 18-05-2022), solo para la tabla anterior.

**Lo que no se pudo cotejar con su fuente primaria** (2026-11-10) y por eso no se usa como fuente:

- La jurisprudencia 2a./J. 1/97 de la Segunda Sala de la SCJN sobre la prescripción de vacaciones y prima vacacional.
  El Semanario Judicial de la Federación (<https://sjf2.scjn.gob.mx>) no entrega sus páginas a una consulta automática.
  Fuentes secundarias le dan el registro digital 199519; **ese número no está verificado**. Ver C-11.
- Las preguntas frecuentes de la STPS sobre la reforma de 2023 («Vacaciones dignas»): no se localizó una copia oficial
  que se pudiera descargar. Ver C-01 y C-02.
- Ninguna tesis sobre días festivos o incapacidad dentro del periodo de vacaciones. Ver C-04 y C-05.

Las firmas de abogados, los blogs y la prensa no se usan como fuente.

Fuera de todo el módulo: **ningún importe en dinero**. Humetría controla días, no calcula pagos (C-08, C-23).

---

## A. Cuántos días y cuándo nacen

### C-01. Días de vacaciones por año de servicio (tabla del artículo 76) · Ley

**Qué hacemos.** Cada año de servicio cumplido da estos días laborables, como mínimo:

| Años cumplidos | 1 | 2 | 3 | 4 | 5 | 6–10 | 11–15 | 16–20 | 21–25 | 26–30 | 31–35 | 36–40 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Días | 12 | 14 | 16 | 18 | 20 | 22 | 24 | 26 | 28 | 30 | 32 | 34 |

Y sigue igual: 2 días más por cada 5 años. En fórmula: hasta el año 5, `10 + 2 × años`; desde el año 6,
`20 + 2 × ⌈(años − 5) / 5⌉`.

**Qué dice la ley.** «Las personas trabajadoras que tengan más de un año de servicios disfrutarán de un periodo anual
de vacaciones pagadas, que en ningún caso podrá ser inferior a doce días laborables, y que aumentará en dos días
laborables, hasta llegar a veinte, por cada año subsecuente de servicios. A partir del sexto año, el periodo de
vacaciones aumentará en dos días por cada cinco de servicios.» [LFT, art. 76; DOF-2022].

**Por qué esa tabla.** La ley da el texto, no la tabla. El aumento «a partir del sexto año» se lee como un escalón que
empieza en el año 6: del 6 al 10 son 22 días. Es la tabla que se difunde como oficial; la publicación de la STPS que la
trae no se pudo descargar para cotejarla, así que aquí se sostiene en el texto del artículo.

**Prueba obligatoria.** Los límites 5/6, 10/11, 15/16 y 35/36, y que con menos de un año no hay días.

### C-02. Aniversarios anteriores al 1 de enero de 2023: tabla anterior · Ley + Nuestro

**Qué hacemos.** Un periodo cuyo aniversario cayó **antes del 2023-01-01** se cuenta con la tabla que estaba vigente
ese día: 6, 8, 10 y 12 días los primeros cuatro años; 14 del año 5 al 9; 16 del 10 al 14, y 2 más cada 5 años. Un
aniversario del 2023-01-01 en adelante usa la tabla de C-01. La tabla anterior solo se usa para los saldos iniciales
que una empresa carga de periodos viejos (C-21).

**Qué dice la ley.** El texto anterior: «Los trabajadores que tengan más de un año de servicios disfrutarán de un
período anual de vacaciones pagadas, que en ningún caso podrá ser inferior a seis días laborables, y que aumentará en
dos días laborables, hasta llegar a doce, por cada año subsecuente de servicios. Después del cuarto año, el período de
vacaciones aumentará en dos días por cada cinco de servicios.» [LFT-2022, art. 76]. El decreto: «entrará en vigor el
1.° de enero de 2023» [DOF-2022, transitorio Primero].

**Lo que es nuestro.** El decreto **no tiene un transitorio que diga que los periodos anteriores conservan la tabla
vieja**: solo trae la fecha de vigencia y el transitorio Segundo (C-13). La regla sale de dos cosas: el derecho nace
en el aniversario (C-03) y la reforma rige desde el 2023-01-01, sin efecto hacia atrás. Un periodo que nació en 2022
nació con la ley de 2022. Si la empresa quiere reconocer más días a un periodo viejo, lo hace con un ajuste a favor
(siempre puede dar más, C-13).

**Prueba obligatoria.** Aniversario `2022-12-31` con 1 año → 6; aniversario `2023-01-01` con 1 año → 12.

### C-03. El derecho nace completo en el aniversario · Ley + Nuestro

**Qué hacemos.** El periodo se otorga entero el día en que la persona cumple cada año de servicio. No hay días que se
ganen mes a mes: antes del primer aniversario el saldo es cero, y solo se calcula una parte proporcional al terminar la
relación (C-07). La antigüedad se cuenta desde la fecha que la empresa reconoce y, si no hay, desde el ingreso.

**Qué dice la ley.** El derecho es de quienes «tengan más de un año de servicios» [LFT, art. 76] y las vacaciones se
conceden «dentro de los seis meses siguientes al cumplimiento del año de servicios» [LFT, art. 81].

**Lo que es nuestro.** Que el sistema no lleve un devengo mensual: la ley no lo pide y un saldo «a cuenta» confunde lo
que la persona puede exigir. Quien quiera dar días antes del aniversario usa las vacaciones adelantadas (C-17).

### C-04. Días laborables: la semana de la política, sin descansos obligatorios ni festivos de la empresa · Ley + Nuestro

**Qué hacemos.** Una solicitud consume solo los días laborables entre sus dos fechas: los de la semana de la política
de la persona (lunes a viernes o lunes a sábado), menos los días de descanso obligatorio del artículo 74 y los festivos
que la empresa agregue. Un rango sin ningún día laborable se rechaza.

**Qué dice la ley.** Las vacaciones se cuentan en «días laborables» [LFT, art. 76]. Son días de descanso obligatorio:
el 1 de enero; el primer lunes de febrero; el tercer lunes de marzo; el 1 de mayo; el 16 de septiembre; el tercer lunes
de noviembre; «el 1o. de octubre de cada seis años, cuando corresponda a la transmisión del Poder Ejecutivo Federal»;
el 25 de diciembre, y «el que determinen las leyes federales y locales electorales, en el caso de elecciones
ordinarias, para efectuar la jornada electoral» [LFT, art. 74, fracción VII reformada DOF 30-09-2024].

**Lo que es nuestro.**

- Que un día de descanso obligatorio no cuenta como laborable. La ley no lo dice con esas palabras y no se cotejó
  ninguna tesis; se sigue del texto: en un día del artículo 74 no se trabaja, así que no es laborable.
- La semana laboral la fija la política de vacaciones (L–V o L–S). La política «Ley» usa lunes a viernes por defecto,
  que es la que menos días le consume a la persona; la empresa lo confirma o lo cambia al activar la herramienta.
- El 1 de octubre se calcula cada seis años contando desde 2024 (2024, 2030, 2036…).
- La jornada electoral (fracción IX) no se calcula: depende de leyes electorales federales y locales. La empresa la
  agrega como festivo propio.
- Un festivo que la empresa agrega después no cambia los días de una solicitud ya creada.

### C-05. Incapacidad dentro del periodo de vacaciones · Nuestro

**Qué hacemos.** Nada automático. Humetría no registra incapacidades del IMSS (C-23). Si una persona se incapacita
durante sus vacaciones, RH cancela o corrige la solicitud y los días regresan al saldo, con la corrección en la
bitácora.

**Por qué.** Los artículos 76 a 81 no dicen qué pasa en ese caso y no se cotejó ninguna tesis. Devolver los días es lo
que protege a la persona, y lo decide RH caso por caso, no el sistema.

### C-06. Doce días continuos: se avisa, no se bloquea · Ley + Nuestro

**Qué hacemos.** Si en un periodo la persona no tiene ninguna solicitud de al menos 12 días laborables seguidos, se le
avisa al pedir. No se le impide pedir menos días.

**Qué dice la ley.** «Del total del periodo que le corresponda conforme a lo previsto en el artículo 76 de esta Ley, la
persona trabajadora disfrutará de doce días de vacaciones continuos, por lo menos. Dicho periodo, a potestad de la
persona trabajadora podrá ser distribuido en la forma y tiempo que así lo requiera.» [LFT, art. 78].

**Lo que es nuestro.** Avisar en lugar de bloquear: repartir el periodo es potestad de la persona. Lo que la empresa no
puede es obligarla a fraccionarlo; por eso el aviso es para ella y queda a la vista de quien aprueba.

### C-07. No se pagan en lugar de disfrutarse; al terminar, la parte proporcional en días · Ley + Nuestro

**Qué hacemos.** No existe «pagar» o «vender» días mientras dura la relación. Al dar de baja a una persona, Humetría
calcula **en días** lo que queda pendiente: los saldos de sus periodos y la parte proporcional del año en curso,
`días del año en curso × días transcurridos / 365`, con dos decimales y sin pasar del total del año. Los días
transcurridos se cuentan desde el último aniversario o, si aún no hay ninguno, desde el ingreso.

**Qué dice la ley.** «Las vacaciones no podrán compensarse con una remuneración. Si la relación de trabajo termina
antes de que se cumpla el año de servicios, el trabajador tendrá derecho a una remuneración proporcionada al tiempo de
servicios prestados.» [LFT, art. 79].

**Lo que es nuestro.** Aplicar la misma proporción al año en curso de quien ya cumplió uno o más años (el artículo solo
habla del primero), la base de 365 días, el redondeo a dos decimales y que el resultado sea solo en días: el importe lo
calcula la nómina de la empresa.

### C-08. La prima vacacional no baja de 25 % y se guarda solo como porcentaje · Ley + Nuestro

**Qué hacemos.** Cada política trae su prima como un porcentaje entero, de 25 en adelante. La política «Ley» usa 25.
La validación y la base de datos rechazan un porcentaje menor. No se calcula cuánto dinero es.

**Qué dice la ley.** «Los trabajadores tendrán derecho a una prima no menor de veinticinco por ciento sobre los
salarios que les correspondan durante el período de vacaciones.» [LFT, art. 80].

**Lo que es nuestro.** Guardar solo el porcentaje: Humetría no conoce salarios y no maneja importes.

### C-09. Las vacaciones se conceden dentro de los seis meses siguientes al aniversario · Ley + Nuestro

**Qué hacemos.** Cada periodo guarda su fecha límite de disfrute: el aniversario más 6 meses. RH ve los periodos que
están por pasar esa fecha y los que ya la pasaron con saldo.

**Qué dice la ley.** «Las vacaciones deberán concederse a los trabajadores dentro de los seis meses siguientes al
cumplimiento del año de servicios.» [LFT, art. 81].

**Lo que es nuestro.** El aviso «por vencer» se muestra 60 días antes (configurable). Pasada la fecha, el saldo sigue
ahí: la obligación de concederlas es de la empresa y el sistema no le quita días a nadie por ese motivo.

### C-10. Constancia anual con acuse de la persona · Ley + Nuestro

**Qué hacemos.** Al otorgar cada periodo se emite una constancia con la antigüedad, los días que corresponden, el
periodo en que deben disfrutarse (del aniversario a la fecha límite de C-09) y la prima en porcentaje. La persona la
ve en su portal y confirma que la recibió; RH la puede imprimir.

**Qué dice la ley.** «Los patrones entregarán anualmente a sus trabajadores una constancia que contenga su antigüedad
y de acuerdo con ella el período de vacaciones que les corresponda y la fecha en que deberán disfrutarlo.»
[LFT, art. 81].

**Lo que es nuestro.** El acuse en el portal (la ley pide entregar, no dice cómo probarlo) y que la constancia sea una
hoja imprimible. Los saldos iniciales cargados por archivo no generan constancia: son de periodos anteriores al uso de
la herramienta.

### C-11. Prescripción a los 18 meses del aniversario: se marca, nunca se borra · Ley + Nuestro

**Qué hacemos.** Cada periodo guarda una fecha: el aniversario más 18 meses. Pasada esa fecha, lo que quede de saldo se
muestra como «prescribible». **No se descuenta ni se borra**: el saldo sigue contando y se puede disfrutar.

**Qué dice la ley.** «Las acciones de trabajo prescriben en un año, contado a partir del día siguiente a la fecha en
que la obligación sea exigible» [LFT, art. 516]. Las vacaciones se conceden dentro de los seis meses siguientes al año
de servicios [LFT, art. 81].

**Lo que es nuestro.** La cuenta: la obligación se vuelve exigible cuando terminan los seis meses del artículo 81, y
desde el día siguiente corre el año del 516; son 18 meses desde el aniversario. Fuentes secundarias atribuyen esta
lectura a la jurisprudencia 2a./J. 1/97 de la Segunda Sala de la SCJN; **no se pudo cotejar en el Semanario Judicial de
la Federación**, así que no se cita como fuente y queda pendiente de revisión a mano. También es nuestro no descontar
nada: la prescripción es una defensa que la empresa decide si hace valer en un juicio, no algo que el sistema aplique
por ella.

### C-12. La empresa prueba; nada se borra · Ley + Nuestro

**Qué hacemos.** Humetría guarda, por persona, lo que la empresa tendría que probar: fecha de ingreso y antigüedad,
cada periodo, cada solicitud con quién la aprobó y cuándo, cada movimiento del saldo (en un libro que solo admite
inserciones) y cada constancia con su acuse. En la versión 1 **nada de esto se borra**.

**Qué dice la ley.** Corresponde al patrón probar su dicho cuando haya controversia sobre la «fecha de ingreso del
trabajador», la «antigüedad del trabajador», el «disfrute y pago de las vacaciones» y el «pago de las primas dominical,
vacacional y de antigüedad» [LFT, art. 784, fracciones I, II, X y XI]. Debe conservar los «comprobantes de pago de
participación de utilidades, de vacaciones y de aguinaldos, así como las primas a que se refiere esta Ley» «durante el
último año y un año después de que se extinga la relación laboral» [LFT, art. 804, fracción IV y párrafo final]. Si no
lo hace, se presumen «ciertos los hechos que el actor exprese en su demanda, en relación con tales documentos, salvo la
prueba en contrario» [LFT, art. 805].

**Lo que es nuestro.** Conservar todo mientras dura la relación y después, sin purga: el plazo de la ley es un mínimo.
Los comprobantes de pago son de la nómina de la empresa; Humetría guarda la evidencia del disfrute.

## B. Políticas de la empresa

### C-13. La ley es el piso: una política solo puede mejorarla · Ley + Nuestro

**Qué hacemos.** La empresa crea políticas con su propia tabla de días, su prima y su semana laboral. Ningún renglón
puede dar menos días que C-01 ni una prima menor a 25 % (C-08): lo rechazan la validación y una restricción en la base
de datos. Siempre existe la política «Ley», que no se edita.

**Qué dice la ley.** Las condiciones de trabajo «en ningún caso podrán ser inferiores a las fijadas en esta Ley»
[LFT, art. 56]. Las modificaciones de 2022 se aplican a los contratos vigentes «siempre que resulten más favorables a
los derechos de las personas trabajadoras» [DOF-2022, transitorio Segundo].

**Lo que es nuestro.** Hacer cumplir el piso también en la base de datos, para que ningún camino lo salte.

### C-14. Días adicionales a los de ley: caducidad y tope los fija la política · Nuestro

**Qué hacemos.** Los días que una política da por encima de C-01 (tabla propia, cumpleaños, días personales) se llevan
en un tramo aparte, «adicional». La política puede fijarles una caducidad en meses desde el aniversario y un tope de
días que pasan al siguiente periodo. Sin esas reglas, se tratan igual que los de ley. **El tramo legal nunca caduca ni
tiene tope** (C-11).

**Por qué.** La LFT regula el mínimo; no dice nada de los días que la empresa da de más. Nos apoyamos en que lo que la
ley protege es el piso (art. 56 y transitorio Segundo del decreto, C-13) y en que una prestación superior se rige por
el documento que la otorga. No se cotejó jurisprudencia: es una lectura defendible, no un texto de ley, y **la empresa
debe tener esas reglas por escrito** (contrato, reglamento o política firmada). La pantalla lo dice así.

### C-15. Qué política le toca a cada persona · Nuestro

**Qué hacemos.** Una política se asigna por nivel de puesto, por grupo (sindicalizado o de confianza) o como la de
defecto de la empresa. Gana la más específica: nivel, luego grupo, luego defecto; si no hay ninguna, la política «Ley».
Cada periodo guarda el nombre y los términos de la política con que se otorgó: cambiar la política después no cambia
periodos ya otorgados.

**Por qué.** Es la forma en que las empresas dan prestaciones superiores (por contrato colectivo o por nivel). Guardar
los términos en el periodo evita que un cambio de política reescriba la historia.

### C-16. Orden de consumo: primero el periodo más antiguo y, dentro de él, el tramo legal · Nuestro

**Qué hacemos.** Los días de una solicitud salen del periodo más antiguo con saldo y, dentro de cada periodo, primero
del tramo legal y luego del adicional. Un periodo prescribible (C-11) no se salta: es el primero en consumirse.

**Por qué.** Consumir lo más antiguo primero es lo que más le conviene a la persona (lo que está más cerca de
prescribir o de caducar) y deja claro qué periodo cubre cada solicitud.

### C-17. Vacaciones adelantadas: solo si la política lo permite y con quién las autorizó · Nuestro

**Qué hacemos.** Una solicitud por más días de los que la persona tiene se puede enviar, pero solo se aprueba si su
política permite adelantar vacaciones (apagado por defecto). Los días adelantados quedan como saldo negativo explícito,
con el nombre de quien los autorizó y registro en la bitácora.

**Por qué.** La ley no prohíbe dar vacaciones antes del aniversario ni obliga a darlas. Como el derecho aún no nació
(C-03), tiene que quedar claro que fue una decisión de la empresa y de quién.

## C. Solicitudes y permisos

### C-18. Aprueba el jefe; RH es respaldo a los tres días hábiles · Nuestro

**Qué hacemos.** La solicitud le llega al jefe directo. Si está inactivo, al primer jefe activo hacia arriba. Le llega
a RH cuando no hay ningún jefe activo, o cuando el jefe no responde en 3 días hábiles (lunes a viernes, sin los
descansos obligatorios; configurable). RH puede ver y corregir cualquier solicitud en todo momento, y queda en la
bitácora.

**Por qué.** La ley obliga a la empresa a conceder las vacaciones (C-09), no dice quién dentro de ella. El plazo evita
que una solicitud se quede sin respuesta.

### C-19. Licencias de ley sembradas; el permiso por luto no está en la ley · Ley + Nuestro

**Qué hacemos.** Además de vacaciones, la persona puede pedir permisos que no consumen saldo. Vienen sembrados:
paternidad, maternidad y adopción (con goce), y «permiso especial» con y sin goce. La empresa agrega los suyos. No hay
reglas por tipo: se registra quién, cuándo, cuántos días y quién aprobó. **No se siembra un permiso por luto.**

**Qué dice la ley.** Paternidad: «permiso de paternidad de cinco días laborables con goce de sueldo, a los hombres
trabajadores, por el nacimiento de sus hijos y de igual manera en el caso de la adopción de un infante» [LFT, art. 132,
fracción XXVII Bis]. Maternidad: «un descanso de seis semanas anteriores y seis posteriores al parto» [LFT, art. 170,
fracción II]. Adopción: «un descanso de seis semanas con goce de sueldo, posteriores al día en que lo reciban»
[LFT, art. 170, fracción II Bis]. Cuidado de menores con cáncer: la licencia del artículo 140 Bis de la Ley del Seguro
Social [LFT, art. 170 Bis].

**Verificación del luto.** En el texto vigente de la LFT (última reforma DOF 14-05-2026, descargado el 2026-11-10) no
aparece ninguna licencia por fallecimiento de un familiar: se buscaron «luto», «duelo» y «fallec» en todo el texto y
ninguna coincidencia es un permiso. Si se publica, se agrega aquí y como tipo sembrado.

**Lo que es nuestro.** No validar la duración de cada licencia (los 5 días, las 6 semanas): la fija la ley y, en
maternidad, el certificado médico; el sistema solo deja el registro. La licencia por cáncer la otorga el IMSS y queda
fuera (C-23).

### C-20. Aniversario del 29 de febrero y meses más cortos · Nuestro

**Qué hacemos.** Quien ingresó un 29 de febrero cumple años de servicio el 28 de febrero en los años que no son
bisiestos y el 29 en los que sí. Al sumar 6 o 18 meses (C-09, C-11), si el día no existe en el mes de destino se usa el
último día de ese mes: un aniversario del 31 de agosto tiene como límite el 28 (o 29) de febrero. «Hoy» siempre es el
día en la hora del centro de México.

**Por qué.** La ley cuenta por años y meses y no resuelve estos casos. Se elige lo que no le quita el día a la persona
ni lo pasa al mes siguiente.

### C-21. Fecha de arranque y saldos iniciales · Nuestro

**Qué hacemos.** Cada empresa tiene una fecha de arranque: el día en que empieza a usar Vacaciones. Desde ese día el
sistema otorga los periodos en cada aniversario. Los periodos anteriores no se otorgan solos: la empresa carga por
archivo los días que cada persona tenía pendientes, periodo por periodo, y queda en la bitácora. Nunca hay dos periodos
del mismo año de servicio para una persona.

**Por qué.** Sin esto, una persona con 20 años recibiría 20 periodos con saldo completo. Lo que se debía antes del
arranque solo lo sabe la empresa.

### C-22. Quién ve el tipo de permiso · Nuestro

**Qué hacemos.** El tipo de un permiso (maternidad, permiso especial…) solo lo ven RH y quien aprueba. El calendario
del equipo, el organigrama y Asistencia solo saben si la persona está de «vacaciones» o con «permiso». El tipo, el
motivo de un rechazo y las notas nunca se escriben en los logs ni en la bitácora.

**Por qué.** Un tipo de permiso puede revelar un dato personal (un embarazo, una adopción). Los compañeros solo
necesitan saber que la persona no está.

### C-23. Fuera de alcance en la versión 1 · Nuestro

**Qué hacemos.** Humetría no cubre, y lo dice en pantalla donde aplica:

- **Dinero**: salarios de vacaciones, importe de la prima, finiquito y nómina. Solo días y un porcentaje.
- **Trabajos discontinuos y de temporada**, cuyo periodo es «en proporción al número de días de trabajos en el año»
  [LFT, art. 77].
- **Menores de dieciocho años**, que tienen «dieciocho días laborables, por lo menos» [LFT, art. 179]: no se guarda la
  fecha de nacimiento. La empresa lo resuelve con una política propia.
- **Regímenes especiales** (buques, tripulaciones aeronáuticas y otros trabajos especiales).
- **Incapacidades del IMSS** y la licencia por cuidados de menores con cáncer (C-05, C-19).
- **Compra y venta de días** (C-07) y **medios días**.

**Por qué.** Cada uno pide datos o reglas que la versión 1 no tiene. Se dejan explícitos para que nadie suponga que
están cubiertos.

---

## Historial

- **2026-11-10 · v1 (propuesta).** Primera versión, escrita contra la LFT (reforma 14-05-2026) y el decreto del
  27-12-2022. Hallazgo al verificar: el decreto no trae un transitorio que conserve la tabla anterior (C-02 quedó como
  «Ley + Nuestro»). Sin cotejar: la jurisprudencia 2a./J. 1/97 (C-11) y la tabla publicada por la STPS (C-01).
