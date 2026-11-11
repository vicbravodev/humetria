# Clima y pulso: banco de preguntas (borrador v1)

Fecha: 2026-11-09 · Estado: **borrador v1, tras una revisión externa; pendiente de aprobación de Victor**.
Acompaña a `2026-11-09-vacations-orgchart-pulse-climate-design.md`.

Es un banco razonado para diagnóstico. **No es un instrumento validado** y no se vende como tal hasta tener la
evidencia de la sección "Validación".

## Cómo está hecho

- **Redacción propia.** Los constructos (reconocimiento, autonomía, etc.) no tienen derechos de autor; la
  redacción de Gallup Q12, UWES, JSS o Great Place to Work sí. La v1 reescribió o quitó los ítems cuya
  formulación se parecía a esos instrumentos. Antes de lanzar conviene una revisión de originalidad independiente.
- **Estructura**: indicadores de resultado + 10 drivers de empresa + un bloque sobre el jefe directo.
- **Reglas de redacción**: una sola idea por ítem; en positivo; sobre algo que la persona puede observar por sí
  misma (no sobre "toda la empresa" ni sobre el trabajo ajeno); sobre algo que la empresa o el jefe pueden
  cambiar; lenguaje llano; ítems en primera persona e instrucciones de "usted".
- **Escala**: acuerdo de 5 puntos (Totalmente en desacuerdo · En desacuerdo · Ni de acuerdo ni en desacuerdo · De
  acuerdo · Totalmente de acuerdo). Solo la pregunta de recomendación usa 0–10. Sin ítems invertidos.
- **Toda pregunta se puede dejar sin contestar.** Quien no tiene información (sobre aumentos o la dirección, por
  ejemplo) la omite en vez de marcar el punto medio. No hay una sexta opción "no sé". El mínimo por grupo se
  aplica a las respuestas de cada ítem, no a las encuestas recibidas.
- **Puntaje**: % favorable (de acuerdo + totalmente de acuerdo), siempre junto con la distribución y el número de
  respuestas.
- **Ⓟ** marca el ítem ancla de cada driver: el que usa el pulso cuando rota ese driver. Un ancla da una señal,
  no equivale al resultado del driver completo.

### Frontera con NOM-035

La Guía III no solo mide violencia y carga: también pregunta por capacitación, apoyo de compañeros, pago,
reconocimiento, pertenencia y trabajo-familia. Regla: **compartir el tema está permitido; repetir la pregunta no.**
Se quitaron los ítems que duplicaban uno de la guía (verificado contra `docs/normas/nom-035/texto-de-referencia.md`):

| Quitado | Ítem de Guía III que ya lo pregunta |
|---|---|
| Equilibrio trabajo-vida | 19 "…el tiempo en el trabajo es mucho y perjudica mis actividades familiares o personales" |
| Capacitación recibida | 36 "Recibo capacitación útil para hacer mi trabajo" |
| Apoyo de compañeros | 46 "Mis compañeros de trabajo me ayudan cuando tengo dificultades" |
| Pertenencia | dominio "sentido de pertenencia" (55, 56) |
| Ritmo sostenible para la salud | dominio de carga de trabajo |

Se conservan a sabiendas dos temas compartidos, con otra formulación y otro uso: el orgullo (ítem 55) como
parte del índice, y el sueldo (ítem 50) preguntado contra las responsabilidades del puesto. Los resultados de
clima nunca se presentan como sustituto ni equivalente de la calificación de riesgo de la NOM-035.

Tamaño: **41 ítems cerrados** (40 en la primera ola) + 4 abiertas opcionales. La duración real se mide en el
piloto con personal operativo en su propio celular.

## A. Indicadores de resultado

| Clave | Ítem | Uso |
|---|---|---|
| `eng.pride` | Me siento orgulloso(a) de trabajar en esta empresa. | índice |
| `eng.motivation` | Me siento motivado(a) para hacer bien mi trabajo. | índice |
| `eng.connection` | Quiero aportar a que esta empresa mejore. | índice |
| `eng.recommend` | ¿Qué tan probable es que recomiende esta empresa como lugar para trabajar? (0 = nada probable, 10 = muy probable) | aparte |
| `eng.stay` | Me gustaría seguir trabajando en esta empresa durante el próximo año. | aparte, solo en clima |

- **Índice de compromiso** provisional: promedio del % favorable de los tres primeros. Si la validación muestra
  que no miden una sola cosa, se reportan por separado en vez de forzar un número.
- **Permanencia** (`eng.stay`) queda fuera del índice: es un resultado distinto y es lo que el índice debería
  ayudar a explicar.
- **Índice de recomendación**: % de 9–10 menos % de 0–6, solo con 30 o más respuestas, y nunca para hacer
  rankings de jefes o áreas (unas pocas respuestas lo mueven mucho).

Los cuatro primeros van en cada pulso.

## B. Drivers de empresa

### 1. Claridad y alineación
| Clave | Ítem |
|---|---|
| `clarity.goals` | Conozco las principales metas que busca alcanzar esta empresa. |
| `clarity.contribution` Ⓟ | Sé cómo mi trabajo contribuye a los objetivos de la empresa. |
| `clarity.priorities` | Tengo claro qué es lo más importante de mi trabajo en este momento. |

### 2. Confianza en la dirección
| Clave | Ítem |
|---|---|
| `leadership.trust` | Confío en las decisiones que toma la dirección de la empresa. |
| `leadership.reasons` Ⓟ | La dirección explica los motivos de sus decisiones importantes. |
| `leadership.consistency` | Lo que la dirección dice coincide con lo que hace. |

### 3. Comunicación y cambios
| Clave | Ítem |
|---|---|
| `communication.informed` Ⓟ | Me entero a tiempo de los cambios que afectan mi trabajo. |
| `communication.who` | Sé a quién acudir cuando necesito información para mi trabajo. |
| `communication.from_areas` | Recibo de otras áreas la información que necesito para hacer mi trabajo. |
| `communication.change` | Cuando cambia la forma de hacer mi trabajo, entiendo qué debo hacer diferente. *(candidato)* |

### 4. Reconocimiento
| Clave | Ítem |
|---|---|
| `recognition.specific` Ⓟ | Cuando obtengo buenos resultados, me explican qué hice bien. |
| `recognition.criteria` | Conozco los criterios que se usan para reconocer un buen trabajo. |

### 5. Crecimiento
| Clave | Ítem |
|---|---|
| `growth.apply` Ⓟ | Puedo poner en práctica lo nuevo que aprendo para mejorar mi trabajo. |
| `growth.path` | Veo oportunidades de crecer dentro de esta empresa. |

### 6. Autonomía
| Clave | Ítem |
|---|---|
| `autonomy.organize` Ⓟ | Tengo margen para organizar mis tareas dentro de las reglas de mi puesto. |
| `autonomy.decisions` | Puedo tomar las decisiones que mi puesto requiere sin pedir permiso para todo. |

### 7. Recursos y procesos
| Clave | Ítem |
|---|---|
| `resources.tools` Ⓟ | Las herramientas que uso para trabajar funcionan bien. |
| `resources.processes` | Los pasos que sigo para completar mis tareas están bien organizados. |
| `resources.staffing` | En mi equipo tenemos suficientes personas para cumplir con las tareas. |

### 8. Trabajo en equipo
| Clave | Ítem |
|---|---|
| `team.respect` | En mi equipo nos tratamos con respeto. |
| `team.coordination` Ⓟ | Puedo coordinar mis tareas con las personas de mi equipo. |

### 9. Voz y trato justo
| Clave | Ítem |
|---|---|
| `voice.disagree` Ⓟ | Puedo expresar desacuerdos sobre el trabajo sin que eso me perjudique. |
| `voice.ideas` | Puedo proponer mejoras relacionadas con mi trabajo. |
| `voice.fair_rules` | Siento que las reglas se aplican de forma justa en mi área. |

### 10. Compensación y prestaciones
| Clave | Ítem |
|---|---|
| `reward.pay` Ⓟ | Considero que mi sueldo corresponde a las responsabilidades de mi puesto. |
| `reward.raises` | Conozco los criterios que se usan para dar aumentos de sueldo. |
| `reward.benefits` | Las prestaciones que recibo me resultan útiles. *(candidato)* |

Los drivers con dos ítems (4, 5, 6 y 8) se reportan de forma descriptiva, ítem por ítem, hasta que la validación
confirme que funcionan como dimensión. Los marcados *candidato* se conservan solo si aportan información distinta.

## C. Su jefe directo (retroalimentación al jefe)

Instrucción en pantalla: *"Las siguientes preguntas son sobre su jefe directo: [nombre]. Sus respuestas se juntan
con las de sus compañeros y su jefe solo ve el resultado del grupo. El sistema no guarda quién contestó qué."*

Solo conductas que el jefe controla y que la persona puede observar; no se le evalúa por aumentos, ascensos ni
plantilla, que suelen decidirse más arriba.

| Clave | Ítem |
|---|---|
| `manager.priorities` | Mi jefe me aclara las prioridades cuando tengo varias tareas pendientes. |
| `manager.guidance` Ⓟ | Mi jefe me dice qué puedo mejorar usando ejemplos de mi trabajo. |
| `manager.listening` | Mi jefe escucha mis ideas y opiniones. |
| `manager.respect` | Mi jefe me trata con respeto. |
| `manager.obstacles` | Mi jefe me ayuda a resolver obstáculos que no puedo solucionar por mi cuenta. |
| `manager.task_criteria` | Mi jefe explica los criterios que usa para repartir las tareas. |
| `manager.learning` | Mi jefe me orienta sobre qué necesito aprender para asumir nuevas responsabilidades. |
| `manager.followthrough` | Mi jefe cumple los acuerdos que hace conmigo. |

`manager.respect` probablemente saldrá muy alto casi siempre; se conserva como señal de alerta. Una caída es
motivo para que RH pregunte, no una acusación ni base para una sanción.

Abiertas (opcionales):
- `manager.keep`: ¿Qué hace bien su jefe y debería seguir haciendo?
- `manager.change`: ¿Qué podría hacer distinto su jefe para apoyar mejor al equipo?

Quien no tiene jefe registrado (la dirección general) no ve este bloque.

## D. Cierre

| Clave | Ítem |
|---|---|
| `loop.action` | He visto mejoras que la empresa presentó como resultado de la encuesta anterior. *(a partir de la segunda ola)* |
| `open.best` | ¿Qué es lo mejor de trabajar aquí? *(abierta, opcional)* |
| `open.improve` | Si pudiera cambiar una sola cosa de la empresa, ¿cuál sería? *(abierta, opcional)* |

Aviso junto a cada pregunta abierta: *"No escriba nombres ni datos que permitan identificarle a usted o a otra
persona. Para reportar acoso o violencia use el canal de quejas."*

## Cómo arma el pulso su cuestionario (máximo 11 ítems)

- Siempre 5: `eng.pride`, `eng.motivation`, `eng.connection`, `eng.recommend` y `loop.action` (este último desde
  la segunda ola de la empresa; antes son 4 fijos).
- Más hasta 6 rotativos, en una de tres modalidades por pulso (nunca combinadas):
  - **Seguimiento**: los ítems de los 2 drivers priorizados tras el último clima (hasta 6).
  - **Recorrido**: 5–6 ítems ancla Ⓟ, con un calendario que cubre los 10 drivers cada 2 pulsos.
  - **Jefe** (una vez por trimestre): `manager.guidance` y `manager.priorities` + 3–4 anclas.
- La tendencia de un ítem solo se calcula entre olas donde se preguntó con redacción idéntica.

## Comentarios escritos: lo que se promete y lo que no

- No se promete "nadie sabrá nunca quién lo escribió": un comentario puede delatar a su autor por lo que cuenta,
  aunque haya 30 respuestas. Se promete lo que el sistema sí garantiza: no guarda quién contestó y solo muestra
  resultados de grupo.
- **El jefe no recibe los textos originales** sobre él (decisión de Victor). Ve sus puntajes y un resumen de los
  comentarios hecho con IA: temas parafraseados con su conteo, solo con 10 o más comentarios, generado una vez al
  cerrar la encuesta. Las reglas completas están en el diseño, en "Retroalimentación al jefe".
- El mínimo por grupo protege también las exportaciones, las comparaciones entre olas y los filtros combinados.

## Validación (qué falta para llamarlo "validado")

Los tamaños son metas de diseño, no requisitos universales.

1. **Contenido y comprensión**: 3–5 especialistas califican cada ítem por relevancia y claridad; 15–24
   entrevistas con personal operativo y administrativo, pidiéndoles explicar con sus palabras qué entendieron
   (sobre todo "dirección", "criterios", "prestaciones", "margen").
2. **Piloto**: 80–150 respuestas en 3–5 empresas. Duración mediana y percentil 90, abandono por pregunta,
   omisiones, ítems con más de 90 % favorable o exceso de punto medio.
3. **Estructura**: 800–1,200 respuestas de varias empresas; análisis factorial exploratorio y confirmatorio en
   mitades independientes, con correlaciones policóricas. Comparar los 10 drivers contra modelos más simples.
4. **Confiabilidad**: omega por driver (0.70 como referencia, no como regla), correlación ítem-total, y
   redundancia cuando dos ítems correlacionan arriba de 0.85.
5. **Índice y anclas**: comprobar que los tres ítems del índice forman una dimensión y cómo se relacionan con
   recomendación y permanencia; comprobar que cada ancla representa a su driver.
6. **Equivalencia** entre operativos y administrativos (200–300 respuestas por grupo) antes de comparar.
7. **Rotación**: como la respuesta no está ligada a la persona, no se puede predecir quién se irá. Sí se puede
   comparar el resultado de un área con su rotación posterior, con 30–50 áreas o más y 6–12 meses.

Los pasos 3 a 7 corren fuera de la aplicación, sobre datos agregados de varias empresas; cómo y con qué
consentimiento es una decisión pendiente.

## Cambios de v0 a v1

- Conteo corregido: la v0 tenía 46 ítems cerrados, no 53, y su pulso podía llegar a 13.
- Quitados (10): `recognition.valued`, `growth.conversation`, `resources.training`, `workload.sustainable`,
  `workload.balance`, `team.support`, `inclusion.belonging`, `reward.wellbeing`, `manager.trust`, y el driver
  "Carga y equilibrio" completo (su ítem de plantilla pasó a Recursos).
- Nuevos (4): `eng.connection`, `communication.change`, `reward.benefits`, `manager.followthrough`.
- Reescritos (24): casi todos los demás, para que pregunten algo observable y no se parezcan a ítems con derechos.
- `eng.stay` salió del índice y bajó de dos años a uno.
- No apliqué: bajar el bloque del jefe a 7 (el propio revisor lo corrigió a 8).
- Las citas del revisor llegaron ilegibles; los traslapes con NOM-035 sí los verifiqué contra el texto de la
  norma, y los parecidos con Gallup y JSS los tomé como criterio prudente, no como hecho comprobado.
