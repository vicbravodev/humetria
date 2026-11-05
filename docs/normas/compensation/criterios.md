# Criterios fiscales y de ley de la calculadora de contrato colectivo

Versión 1.0 · 3 de noviembre de 2026 · Valores consultados el **2026-11-03** (UMA y salario mínimo vigentes en 2026).

Este documento es la **única fuente de tasas** de la calculadora de compensación (módulo `Compensation`): la Task 8 siembra
`fiscal_parameters` a partir de él, y los ejemplos de las pruebas usan los valores de 2026 que aparecen aquí. Cada valor lleva su
vigencia, su fuente oficial y la fecha de consulta. **Manda** sobre cualquier otro documento del repositorio en cuanto a tasas: si el
código o un diseño lo contradicen, se corrige el código o se actualiza este documento con su justificación.

> **Estado: borrador para aprobación.** Los valores marcados «por verificar» (sección 7) no se siembran hasta confirmarlos.

Fuentes (copias y resúmenes en [`fuentes/`](fuentes/README.md)). Todas son oficiales; los despachos, blogs y notas de prensa
**no** se usan como fuente de ningún valor:

- **[UMA]** INEGI, avisos del valor de la UMA, DOF 10/01/2024, 10/01/2025 y 09/01/2026.
- **[SM]** CONASAMI, Resolución que fija los salarios mínimos 2026, DOF 09/12/2025.
- **[DESIND]** Decreto de desindexación del salario mínimo, DOF 27/01/2016.
- **[LSS]** Ley del Seguro Social, texto vigente (última reforma DOF 15-01-2026), y el Decreto de reforma de pensiones DOF 16/12/2020.
- **[IMSS]** IMSS, Informe al Ejecutivo Federal y al Congreso 2022-2023, anexo A (sirve para cruzar las tasas de la LSS).
- **[LFT]** Ley Federal del Trabajo, texto vigente (última reforma DOF 14-05-2026).
- **[INFONAVIT]** Ley del Infonavit (última reforma DOF 21-02-2025).
- **[ISN]** Ley de Hacienda, Código Fiscal o Financiero de cada estado, en el portal de su Congreso, Periódico Oficial o
  Secretaría de Finanzas (un archivo por estado en `fuentes/isn/`).

## 1. Unidades de referencia

| Valor | Cifra | Vigencia | Fuente | Consulta |
|---|---|---|---|---|
| UMA diaria | **$117.31** (mensual $3,566.22, anual $42,794.64) | del 1-feb-2026 al 31-ene-2027 | [UMA] DOF 09/01/2026 | 2026-11-03 |
| UMA diaria anterior | $113.14 (mensual $3,439.46) | del 1-feb-2025 al 31-ene-2026 | [UMA] DOF 10/01/2025 | 2026-11-03 |
| UMA diaria de 2024 | $108.57 (mensual $3,300.53) | del 1-feb-2024 al 31-ene-2025 | [UMA] DOF 10/01/2024 | 2026-11-03 |
| Salario mínimo general (ZSMG) | **$315.04** diarios (mensual $9,582.47) | desde el 1-ene-2026 | [SM] DOF 09/12/2025, considerando 3.º | 2026-11-03 |
| Salario mínimo, Zona Libre de la Frontera Norte | $440.87 diarios (mensual $13,409.80) | desde el 1-ene-2026 | [SM] DOF 09/12/2025 | 2026-11-03 |
| Tope del SBC (25 UMA) | **$2,932.75** diarios con la UMA de 2026; $2,828.50 con la de 2025 | sigue a la UMA | [LSS] art. 28 + [DESIND] trans. 3.º + [IMSS] anexo A, nota 6 | 2026-11-03 |
| Piso del SBC | el salario mínimo general del área geográfica | sigue al salario mínimo | [LSS] art. 28 | 2026-11-03 |

- La UMA cambia el **1 de febrero** de cada año (se publica a principios de enero); el salario mínimo, el **1 de enero**. En enero rige
  todavía la UMA del año anterior. Más allá del último valor conocido se arrastra el último y el reporte avisa (spec, decisión 11).
- El salario mínimo es **mayor** que la UMA (2026: $315.04 = 2.69 UMA). Cada ley dice cuál usa: el SBC y las cuotas del IMSS usan UMA; la
  prima de antigüedad usa salario mínimo (LFT art. 486).
- La [LSS] conserva en su texto «salario mínimo general diario del Distrito Federal» (arts. 27 fr. VI, 28 y 106). Por el trans. 3.º de
  [DESIND] se leen como UMA, y el IMSS lo confirma: desde 2017 la cuota fija, el excedente y el límite superior del SBC «consideran el
  valor de la UMA» ([IMSS] anexo A).

## 2. Cuotas y aportaciones patronales

Patrón. Base: SBC diario con tope de 25 UMA, salvo lo que dice la columna «Base». Cuota de enfermedad y maternidad «en especie»: fija
por persona, no depende del salario.

| Concepto | Tasa | Base | Fuente | Vigencia |
|---|---|---|---|---|
| Enf. y mat., cuota fija | **20.40 %** de la UMA diaria, por persona asegurada | UMA, no SBC | [LSS] art. 106 fr. I + trans. 19.º de la ley de 1995 (13.9 % + 10 × 0.65); [IMSS] cuadro A.3 | desde el 1-jul-2007 |
| Enf. y mat., cuota excedente | **1.10 %** | (SBC − 3 UMA), solo si SBC > 3 UMA | [LSS] art. 106 fr. II + trans. 19.º (6 − 10 × 0.49); [IMSS] | desde el 1-jul-2007 |
| Enf. y mat., gastos médicos de pensionados | **1.05 %** | SBC | [LSS] art. 25 párr. 2.º | texto vigente |
| Enf. y mat., prestaciones en dinero | **0.70 %** | SBC | [LSS] art. 107 fr. I (70 % de 1 %) | texto vigente |
| Invalidez y vida | **1.75 %** | SBC | [LSS] art. 147 | texto vigente |
| Guarderías y prestaciones sociales | **1.00 %** | SBC | [LSS] arts. 211-212 | texto vigente |
| Retiro | **2.00 %** | SBC | [LSS] art. 168 fr. I | texto vigente |
| Cesantía en edad avanzada y vejez | **escalonada**: ver sección 2.1 | SBC | [LSS] art. 168 fr. II a) + decreto DOF 16/12/2020, trans. 2.º | 2023-2030 |
| Riesgos de trabajo | **no hay valor único**: ver sección 2.2 | SBC | [LSS] arts. 71-74 | por empresa y año |
| Infonavit | **5.00 %** | SBC con el mismo tope de 25 UMA | [INFONAVIT] art. 29 fr. II | texto vigente |

«Texto vigente» = tal como aparece en la [LSS] consultada el 2026-11-03 (la reforma del 15-01-2026 no toca estos artículos) y cruzado con
el cuadro A.3 del [IMSS] (2022). Total patronal sin riesgo de trabajo ni cesantía y vejez: 0.70 + 1.05 + 1.75 + 1 + 2 = 6.50 % del SBC
más la cuota fija y el excedente más cesantía y vejez. Las cuotas del trabajador y la cuota social del Gobierno no son costo del patrón.

### 2.1 Cesantía y vejez, cuota patronal (% del SBC)

Del 1-ene-2021 al 31-dic-2022 fue 3.150 % para todos. Desde 2023 sube cada 1 de enero según el rango de SBC y el año (decreto DOF
16/12/2020, trans. 2.º; la columna 2030 es la del art. 168 fr. II a) vigente y queda fija después). **Tabla transcrita de la imagen de
la página 32 del DOF** (copia en `fuentes/dof-2020-12-16-reforma-lss-sar.pdf`; detalle en `fuentes/lss-transitorio-2020-cesantia-vejez.txt`).

| Rango de SBC | 2023 | 2024 | 2025 | **2026** | 2027 | 2028 | 2029 | 2030 |
|---|---|---|---|---|---|---|---|---|
| 1.0 SM | 3.150 | 3.150 | 3.150 | **3.150** | 3.150 | 3.150 | 3.150 | 3.150 |
| 1.01 SM a 1.50 UMA | 3.281 | 3.413 | 3.544 | **3.676** | 3.807 | 3.939 | 4.070 | 4.202 |
| 1.51 a 2.00 UMA | 3.575 | 4.000 | 4.426 | **4.851** | 5.276 | 5.701 | 6.126 | 6.552 |
| 2.01 a 2.50 UMA | 3.751 | 4.353 | 4.954 | **5.556** | 6.157 | 6.759 | 7.360 | 7.962 |
| 2.51 a 3.00 UMA | 3.869 | 4.588 | 5.307 | **6.026** | 6.745 | 7.464 | 8.183 | 8.902 |
| 3.01 a 3.50 UMA | 3.953 | 4.756 | 5.559 | **6.361** | 7.164 | 7.967 | 8.770 | 9.573 |
| 3.51 a 4.00 UMA | 4.016 | 4.882 | 5.747 | **6.613** | 7.479 | 8.345 | 9.211 | 10.077 |
| 4.01 UMA en adelante | 4.241 | 5.331 | 6.422 | **7.513** | 8.603 | 9.694 | 10.784 | 11.875 |

Reglas para el cálculo (interpretación de Humetría, ver sección 7): el rango se elige con SBC ÷ UMA diaria; el límite superior de cada
rango es inclusivo (2.00 UMA exactos van en «1.51 a 2.00»); y el rango «1.0 SM» aplica a quien cotiza exactamente el salario mínimo.

### 2.2 Prima del seguro de riesgos de trabajo

No existe una tasa nacional: la fija el IMSS **por empresa** (por registro patronal) y se revisa cada año con la siniestralidad. Lo que
dice la [LSS]:

- Art. 72: `Prima = [(S/365) + V × (I + D)] × (F/N) + M`, con V = 28 años, F = 2.3 (2.2 si el centro tiene un sistema de administración y
  seguridad acreditado por la STPS), M = 0.005 (prima mínima); S = días subsidiados, I = suma de los porcentajes de incapacidad
  permanente ÷ 100, D = defunciones, N = trabajadores promedio expuestos.
- Art. 73: al inscribirse o cambiar de actividad se paga la prima media de su clase: I 0.54355 %, II 1.13065 %, III 2.59840 %,
  IV 4.65325 %, V 7.58875 %.
- Art. 74: cada año puede subir o bajar hasta un punto respecto de la anterior; mínimo 0.5 %, máximo 15 % del SBC.
- Referencia informativa, **no se siembra**: la prima promedio ponderada nacional fue 1.907 % en el periodo mar-2022 a feb-2023 ([IMSS] cuadro A.3).

En la calculadora es un dato **por cuenta, con vigencia** (spec, decisión 9); sin él, la línea de riesgo de trabajo no se calcula.

## 3. Integración del salario (SBC)

- **Factor de integración** = `1 + aguinaldo/365 + vacaciones × prima vacacional / 365`, con los valores reales de cada persona.
  Con los mínimos de ley (15 días, 12 días, 25 %): `1 + 15/365 + 12 × 0.25/365 = 383/365 = 1.0493150…` (1.0493).
- **SBC diario** = salario diario × factor + otras prestaciones que integran, **truncado al centavo** (decisión del spec, probada abajo),
  con piso en el salario mínimo y tope de 25 UMA.
- Conceptos que **no** integran ([LSS] art. 27, siempre que estén en la contabilidad del patrón): instrumentos de trabajo; fondo de
  ahorro con depósito igual del trabajador y de la empresa y retiro máximo dos veces al año; aportaciones del patrón al retiro;
  cuotas del patrón, Infonavit y PTU; alimentación y habitación onerosas; **despensas** hasta el **40 % de la UMA diaria**
  ($46.92 con la UMA de 2026); premios de asistencia y puntualidad hasta 10 % del SBC; planes de pensiones que cumplan los requisitos de la
  CONSAR; tiempo extra dentro de los márgenes de la LFT. En despensas, premios y tiempo extra, **solo el excedente** integra.
- El tope de despensas se compara por día: 40 % × UMA diaria × días del periodo. El efecto en el ISR de vales y fondo de ahorro **no** se
  calcula; el spec solo lo señala (por verificar si se requiere, sección 7).

## 4. Mínimos de ley

| Prestación | Mínimo | Fuente | Vigencia |
|---|---|---|---|
| Aguinaldo | 15 días de salario, antes del 20 de diciembre; proporcional si no se cumplió el año | [LFT] art. 87 | texto vigente (reforma 14-05-2026 no lo toca) |
| Prima vacacional | 25 % de los salarios del periodo de vacaciones | [LFT] art. 80 | texto vigente |
| Vacaciones | 12 días laborables al año de servicio, +2 cada año hasta 20; desde el 6.º año, +2 cada cinco años de servicio | [LFT] art. 76 (reforma DOF 27-12-2022) | texto vigente |
| Prima de antigüedad | 12 días de salario por año de servicio; salario máximo = **2 salarios mínimos** del área | [LFT] art. 162 frs. I y II + arts. 485 y 486 | texto vigente |

**Tabla de vacaciones** (derivada del art. 76; el artículo no la trae en forma de tabla):

| Años cumplidos de servicio | 1 | 2 | 3 | 4 | 5 | 6-10 | 11-15 | 16-20 | 21-25 | 26-30 | 31-35 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Días laborables | 12 | 14 | 16 | 18 | 20 | 22 | 24 | 26 | 28 | 30 | 32 |

- Con menos de un año de servicio no hay derecho a vacaciones completas (art. 76 empieza con «más de un año»; el art. 79 da una
  remuneración proporcional solo si la relación termina antes del año).
- **Prima de antigüedad**: se paga a quien se separa voluntariamente con **15 años o más**, a quien se separa por causa justificada y a
  quien es separado, justificado o no el despido, y a los beneficiarios por muerte (art. 162 frs. III y V). La calculadora la rotula como
  estimación y solo la cuenta para quien cumple la condición. Tope con el salario mínimo de 2026: 2 × $315.04 = **$630.08** diarios
  (en la Zona Libre de la Frontera Norte, 2 × $440.87 = $881.74).
- La antigüedad cambia el día del aniversario exacto: ese día pasa a los días de vacaciones y a la prima del nuevo año (Review Focus 4 del plan).

## 5. Ejemplos trabajados (valores de 2026)

UMA $117.31, salario mínimo $315.04.

**a) SBC.** Salario diario $500, aguinaldo 15 días, vacaciones 12 días, prima vacacional 25 %: factor = 383/365 = 1.049315…;
SBC = 500 × 1.049315… = 524.6575… → **$524.65** (truncado al centavo; con redondeo normal sería $524.66). Está por debajo del tope
($2,932.75) y equivale a 524.65 ÷ 117.31 = **4.47 UMA**, así que cae en el rango «4.01 UMA en adelante».

**b) Cuotas patronales diarias sobre ese SBC** (redondeo a centavos solo para mostrar; el motor trabaja en centavos enteros o
`brick/math` y redondea al final):

| Concepto | Cálculo | Por día |
|---|---|---|
| Cuota fija | 20.40 % × 117.31 | $23.93 |
| Excedente | 1.10 % × (524.65 − 351.93) | $1.90 |
| Pensionados | 1.05 % × 524.65 | $5.51 |
| Prestaciones en dinero | 0.70 % × 524.65 | $3.67 |
| Invalidez y vida | 1.75 % × 524.65 | $9.18 |
| Guarderías | 1.00 % × 524.65 | $5.25 |
| Retiro | 2.00 % × 524.65 | $10.49 |
| Cesantía y vejez (2026, 4.01 UMA en adelante) | 7.513 % × 524.65 | $39.42 |
| Infonavit | 5.00 % × 524.65 | $26.23 |
| **Total sin riesgo de trabajo ni ISN** | | **$125.58** (23.94 % del SBC) |

3 UMA = $351.93. El mismo cálculo con SBC = 3 UMA justos no paga excedente (SBC − 3 UMA = 0); un centavo arriba, sí.

**c) Tope del SBC.** 25 × 117.31 = **$2,932.75**; con SBC = $2,932.75 se cotiza completo y con $2,932.76 se cotiza $2,932.75.

**d) Vales de despensa.** Tope 40 % × 117.31 = $46.924 por día; la LSS no dice cómo redondearlo y se adopta el criterio del SBC
(truncar al centavo): **$46.92**. Con $46.92 diarios no integra nada; con $47.92 (un peso arriba) integra **$1.00** al SBC.

**e) Prima de antigüedad.** $500 diarios, 20 años: 12 × 20 × 500 = **$120,000**. Con +5 % al salario ($525 diarios): 12 × 20 × 525 =
**$126,000**, un efecto de +$6,000. Ambos salarios están por debajo del tope de $630.08, así que no se recortan; con $700 diarios el
cálculo usa $630.08 y el aumento no cambia la prima.

## 6. Impuesto sobre nómina (ISN) por estado, 2026

El ISN es **estatal**: cada entidad fija sujeto, base, tasa y exenciones en su ley. La tasa efectiva depende del estado donde se
**presta** el trabajo. Todas las cifras salen del texto oficial de la ley o código (archivo por estado en `fuentes/isn/`).

La **base** es, en todos los estados, el total de pagos por trabajo personal subordinado (más asimilados a salarios), pagados o causados
en el mes. En general **sí** integran la base: sueldos, aguinaldo, prima vacacional, bonos, comisiones y despensas en efectivo; y
**no**: PTU, indemnizaciones por terminación o rescisión, cuotas del IMSS e Infonavit. El fondo de ahorro queda fuera si el depósito del
trabajador es igual al de la empresa, y las despensas suelen tener tope (40 % de la UMA diaria en varios estados). Los tratamientos
distintos están en la columna de notas; donde la ley no nombra un concepto y su tratamiento es lectura nuestra, está marcado en el
archivo del estado.

| Estado | Ley y artículo(s) de la tasa | Tasa 2026 | Notas que cambian el costo | Verificación |
|---|---|---|---|---|
| Aguascalientes | Ley de Hacienda, art. 67 (base art. 66) | **2.5 %** | Estímulo de 50 % solo para ciertos grupos (Ley de Ingresos 2026, art. 41) | Confirmado |
| Baja California | Ley de Hacienda, art. 151-16 (arts. 151-13 a 151-20) | **4.25 %** | Excluye previsión social, PTU e indemnizaciones (151-18); la Ley de Ingresos 2026 ya no fija tasa | Confirmado, salvedad: no hay consolidado posterior a dic-2023 |
| Baja California Sur | Ley de Hacienda, art. 36 (reforma BOGE 31-dic-2025) | **3 %** (antes 2.5 %) | Despensas hasta 40 % de la UMA diaria excluidas (art. 35) | Confirmado |
| Campeche | Ley de Hacienda, art. 24; adicional art. 53-D | **3 %** + 33 % adicional sobre el impuesto = **3.99 %** efectiva | Base incluye aguinaldo, despensa, fondo de ahorro y prima vacacional (art. 20) | Confirmado; el consolidado de la Consejería es «documento de consulta» |
| Chiapas | Código Fiscal, art. 235 (reforma P.O. 9-dic-2025) | **3 %** (antes 2 %) | Vales, aguinaldo, PTU y fondo de ahorro gravados sin tope (art. 229) | Confirmado |
| Chihuahua | Ley de Hacienda, art. 75 (3 %) y trans. 2.º del Decreto LXVIII/RFLYC/0462/2025 | **4 %** en 2026 y 2027 (vuelve a 3 % en 2028) | Exenta despensa en vales hasta 40 % de la UMA, fondo de ahorro igual (art. 77) | Confirmado |
| Ciudad de México | Código Fiscal, art. 158 (reforma G.O. 27-dic-2024) | **4 %** | Art. 278: reducción «equivalente al 1 %» por crecer planta 33 %; ambigua (sección 7) | Confirmado la tasa |
| Coahuila | Ley de Hacienda, art. 24 (Decreto 563, 2023) | **3 %** | Reducción de 20-50 % por empleo de adultos mayores y personas con discapacidad (arts. 30-31) | Confirmado |
| Colima | Ley de Hacienda, art. 41 Q (Decreto 199, P.O. 18-dic-2025) | **3 %** (antes 2 %) | Aguinaldo y prima vacacional incluidos; fondo de ahorro igual al del trabajador, excluido | Confirmado |
| Durango | Ley de Hacienda, art. 7 | **3 %** | Despensa excluida hasta 40 % de la UMA; posible 2 % para hasta 10 trabajadores (sección 7) | Tasa confirmada |
| Guanajuato | Ley de Hacienda, art. 10 | **3 %** | Despensa gravada solo por el excedente de 40 % de la UMA diaria (art. 9 fr. XI) | Confirmado |
| Guerrero | Ley 419 de Hacienda, art. 44; adicionales arts. 51, 53 y 54 | **3 %** nominal; con tres adicionales de 15 % sobre el impuesto, **4.35 %** efectiva | La efectiva es cálculo de Humetría a partir de los artículos; ver sección 7 | Tasa nominal confirmada |
| Hidalgo | Ley de Hacienda, art. 24 | **3 %** | Despensa, alimentación y transporte excluidos en conjunto hasta 40 % del salario (art. 27) | Confirmado |
| Jalisco | Ley de Ingresos 2026, art. 13 (Ley de Hacienda, art. 42, remite a ella) | **3 %** | Exención 100 % el primer año y 50 % el segundo por primer empleo formal | Tasa confirmada; la Ley de Hacienda compilada es de abr-2024 |
| México | Código Financiero del Estado de México y Municipios, art. 57 | **3 %** | Personas físicas con hasta 4 trabajadores: cuota de $250 por trabajador; aguinaldo, fondo de ahorro y despensa gravados sin tope | Confirmado |
| Michoacán | Ley de Hacienda, art. 45 (Ley de Ingresos 2026, art. 14) | **3 %** | Despensa excluida hasta 40 % de la UMA; prima vacacional dudosa por la exclusión de «las vacaciones» | Confirmado |
| Morelos | Ley General de Hacienda, art. 58 BIS-4 (Decreto 997, P.O. 22-dic-2025) | **3 %** (antes 2.5 %) | Aguinaldo incluido; despensa exenta hasta 30 % del salario mínimo mensual; PTU se lee gravada | Confirmado |
| Nayarit | Ley de Hacienda, art. 87 | **3 %** | Aguinaldo y PTU exentos | Confirmado |
| Nuevo León | Ley de Hacienda, art. 157 | **3 %** | El alza a 4 % propuesta para 2026 no se aprobó; aguinaldo incluido; despensas y fondo de ahorro exentos (art. 160) | Confirmado con salvedad: no se halló el P.O. de dic-2025 |
| Oaxaca | Ley Estatal de Hacienda, art. 66 (Ley de Ingresos 2026, arts. 17-20) | **3 %** | Estímulos 2026: 25 % a microempresas (1-10) y 10 % a 11-50 trabajadores | Confirmado |
| Puebla | Ley de Ingresos 2026, art. 16 (Ley de Hacienda, art. 14, remite a ella) | **3 %** | 25 % de estímulo con hasta 4 trabajadores; despensa solo por monedero y hasta 40 % del salario mínimo estatal | Confirmado |
| Querétaro | Ley de Hacienda, art. 72 | **3 %** | Se resta una deducción de 8 salarios mínimos mensuales; despensa hasta 40 % del salario mínimo | Confirmado con salvedad: reforma P.O. 23-dic-2025 no leída |
| Quintana Roo | Ley del Impuesto sobre Nóminas, art. 6 | **4 %** | Prima vacacional incluida; indemnizaciones y despensas «cuando se cobren al trabajador» no gravadas (art. 17) | Confirmado |
| San Luis Potosí | Ley de Hacienda, art. 23 | **3 %** | Fondo de ahorro, vales, PTU e indemnizaciones exceptuados (art. 27) | Confirmado |
| Sinaloa | Ley de Hacienda, art. 18 | **Tarifa mensual progresiva** (no es tasa plana): hasta $500,000 → 2.40 %; $500,000.01 a $700,000 → cuota $12,000 + 2.60 % del excedente; $700,000.01 a $900,000 → $17,200 + 2.80 %; más de $900,000 → $22,800 + 3.00 % | Aguinaldo y gratificaciones generales excluidos hasta 30 UMA; previsión social hasta lo exento del ISR | Confirmado con salvedad: no se confirmó que el art. 22 no haya cambiado desde 2023 |
| Sonora | Ley de Hacienda, art. 216; adicional art. 292 BIS-9 | **3 %** + **1 %** adicional con 100 o más trabajadores en el IMSS en Sonora = 4 % | Exención de 1 UMA mensual por trabajador (hasta 6) con hasta 20 trabajadores (art. 218 BIS-2) | Confirmado |
| Tabasco | Ley de Hacienda, art. 30 | **3.5 %** (3.0 % para poderes, órganos autónomos y municipios) | Reforma P.O. 18-dic-2024; excluye PTU, pensiones, viáticos | Confirmado |
| Tamaulipas | Ley de Hacienda, art. 49 | **3 %** | Aguinaldo y vales de despensa gravados; PTU exenta hasta 15 salarios mínimos | Confirmado |
| Tlaxcala | Código Financiero, art. 134 | **3 %**; con el incentivo de 33 % (Ley de Ingresos 2026, art. 24) queda en 2.01 % | El incentivo exige solicitud y autorización de Finanzas; no es automático | Confirmado |
| Veracruz | Código Financiero, art. 101 | **3 %** | El texto con reforma del 13-ene-2026 dice 3 %; no se confirmó un aumento para 2026 | Confirmado el Código; Ley de Ingresos 2026 no localizada |
| Yucatán | Ley General de Hacienda, art. 24 (Decreto 138/2025) | **3.75 %** (+1 % para poderes y organismos estatales) | Decreto 147/2025: micro y pequeñas (hasta 50 trabajadores) pagan efectiva **3 %** en 2026; vence el 31-ene-2027 | Confirmado |
| Zacatecas | Ley de Hacienda, art. 40 (Decreto 22, P.O. 28-dic-2024) | **3.5 %** | Aguinaldo, PTU, despensa, fondo de ahorro y prima vacacional gravados expresamente | Confirmado |

Estados con tasa distinta de 3 % para 2026: Aguascalientes 2.5, Baja California 4.25, Campeche 3.99 efectiva, Chihuahua 4, Ciudad de México 4,
Guerrero 4.35 efectiva (por verificar), Quintana Roo 4, Sinaloa (tarifa), Sonora 3-4, Tabasco 3.5, Yucatán 3.75, Zacatecas 3.5.
**Cambios 2025 → 2026**: Baja California Sur, Chiapas, Colima y Morelos subieron a 3 %; Chihuahua subió a 4 % por un transitorio.
Sin cambio publicado para 2027 en ningún estado a la fecha (las leyes de ingresos 2027 se publican en diciembre): **revisar cada diciembre**.

## 7. Por verificar

Nada de esto se siembra como definitivo hasta confirmarlo. Para cada punto, lo que se intentó.

1. **Cesantía y vejez, rango «1.0 SM» cuando SM > 1.5 UMA.** El salario mínimo de 2026 ($315.04) es 2.69 UMA, así que los rangos «1.01 SM a
   1.50 UMA», «1.51 a 2.00» y «2.01 a 2.50» no los alcanza ningún SBC ≥ salario mínimo. Con el texto literal, quien cotiza exactamente
   el mínimo paga 3.150 % y quien cotiza un centavo más cae en «2.51 a 3.00 UMA» (6.026 % en 2026). No se halló un criterio publicado por el IMSS
   sobre este salto (buscado en la [LSS] y en el informe del [IMSS]). Mientras no se confirme, la calculadora aplica el texto literal y el
   límite superior inclusivo de cada rango; el reporte lo declara como supuesto.
2. **Guerrero, 4.35 % efectiva.** Los arts. 51, 53 y 54 imponen tres adicionales de 15 % que incluyen el impuesto sobre remuneraciones en su
   base; la tasa efectiva es nuestro cálculo (3 % × 1.45). Falta confirmarla con el formato oficial de declaración de la Secretaría de Finanzas.
3. **Durango, 2 % para hasta 10 trabajadores.** Un dictamen de la Ley de Ingresos 2023 lo menciona; el art. 7 vigente no lo trae. Se usa 3 %.
4. **Ciudad de México, art. 278.** La reducción «equivalente al 1 %» puede ser un punto de la tasa (efectiva 3 %) o 1 % del impuesto. No se
   siembra la reducción.
5. **Estímulos de las leyes de ingresos 2026** de Chiapas, Chihuahua, Coahuila, Baja California Sur, Veracruz y de las leyes de fomento de
   Baja California y Campeche no se revisaron: no se aplican estímulos en ningún estado salvo los de la tabla, y el reporte lo dice.
6. **Baja California** (sin consolidado posterior a dic-2023), **Nuevo León** (sin P.O. de dic-2025), **Querétaro** (reforma P.O. 23-dic-2025 no
   leída), **Jalisco** (Ley de Hacienda compilada de abr-2024), **Sinaloa** (art. 22), **Veracruz** (Ley de Ingresos 2026 no localizada): no se
   descarta una reforma posterior a la fecha del texto leído.
7. **Aguinaldo, prima vacacional, fondo de ahorro y despensa en el ISN** de los estados donde la ley no los nombra: se tratan como gravados
   por la cláusula general (lectura nuestra, marcada en el archivo de cada estado). Se verifica con cada Secretaría antes de afinarlo.
8. **Prima de riesgo de trabajo**: el Reglamento de la LSS en materia de afiliación, clasificación de empresas, recaudación y fiscalización
   (clases por actividad, determinación anual) no se revisó; no hace falta mientras el valor sea un dato de la cuenta.
9. **Cuota fija: forma de contar los días.** La [LSS] fija una cuota **diaria**; cómo se prorratea por mes (días naturales cotizados, art. 39
   y su reglamento) no se revisó. La calculadora usa 365/12 días por mes como aproximación y lo declara.
10. **ISR de vales de despensa y fondo de ahorro** (LISR): no se revisó porque el spec solo lo señala sin calcularlo.
11. **Salario mínimo 2025** ($278.80): solo comprobado por aritmética con la resolución de 2026; no es necesario para 2026.
12. **Parámetros 2027**: UMA (aviso DOF de enero de 2027), salario mínimo (resolución de diciembre de 2026) y leyes de ingresos estatales aún no
    existen a la fecha de consulta. Es un trabajo de actualización anual: UMA en enero, salario mínimo en diciembre, ISN en diciembre.

## 7.1 Decisiones tomadas con datos (2026-11-04)

Las toma Humetría con las fuentes de `fuentes/supuestos-largo-plazo.txt`; cada una se revisa si cambia el dato.

| Punto | Decisión | Dato en que se apoya |
|---|---|---|
| Cesantía y vejez, rango «1.0 SM» | Se mantiene el texto literal del art. 168 fr. II; el reporte lo declara como supuesto | La ley rotula ese rango en salarios mínimos y los demás en UMA; ningún criterio del IMSS ni publicación profesional resuelve el caso SM > 1.5 UMA |
| Sinaloa | El impuesto sobre nómina de Sinaloa queda «no calculado» y el reporte lo marca | Es una tarifa mensual progresiva (2.40 a 3.00 %) sobre el total de remuneraciones del patrón en el estado; la calculadora no suma por estado, y una tasa plana falsearía el costo |
| Ingreso el 29 de febrero | Aniversario el 28 de febrero en un año sin 29 | LFT art. 18: la duda se resuelve a favor del trabajador |
| Aguinaldo de quien ingresa | Sin prorrateo | LFT art. 87 lo prorratea al que no cumplió el año, pero la calculadora costea a quien ya estaba al inicio de la vigencia |
| Despensa (40 % de la UMA diaria, solo el excedente integra) | Tal como está | LSS art. 27 fr. VI |
| Inflación | 3 % | Meta del Banco de México |
| Revisiones futuras | 7.3 % | STPS: promedio nominal de 2,896 revisiones, enero a octubre de 2025 |
| Crecimiento de la plantilla | 1.3 % | IMSS: empleo formal 2025 |
| Rotación | 0 % | No hay dato oficial utilizable; la cuenta captura la suya |

Premio de asistencia o puntualidad «hasta el tope de la ley» (sin tope capturado): integra al SBC solo lo que pasa de 10 % del SBC sin
el premio (LSS art. 27 fr. VII y el párrafo final, que integra el excedente). El 10 % se toma del SBC de la persona sin el premio, con el que
el premio no se cuenta a sí mismo; si el contrato captura un tope, rige ese tope. Sin dato del SBC integra completo.

## 8. Historial

- 1.0 · 2026-11-03: primera versión. UMA, salario mínimo, cuotas IMSS e Infonavit, cesantía y vejez 2023-2030, mínimos de ley, prima de
  antigüedad, ejemplos de 2026 y los 32 ISN con fuente oficial.
