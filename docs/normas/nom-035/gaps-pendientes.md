# NOM-035: gaps pendientes

Lo que la norma pide y Humetría todavía no gestiona.
Sale de correr `Nom035EvaluationSeeder` (ver abajo) y revisar las pantallas contra el DOF del 23/10/2018.
Las Guías I, II y III, su calificación, la muestra, la privacidad y el informe 7.7 **sí** cumplen: lo verifican
`tests/Feature/Seeders/Nom035EvaluationSeederTest.php` y las pruebas citadas en [`criterios.md`](criterios.md).

**Ya no queda ningún gap de la norma por cerrar.** La política de prevención, su difusión y la página permanente de las
personas trabajadoras (5.1, 5.7 y 7.8) se cerraron con los criterios C-50 a C-56 de [`criterios.md`](criterios.md); el Programa
de intervención con C-30 a C-36 y el canal de quejas con C-40 a C-49. Si aparece uno nuevo, es **un trabajo propio** (un PR,
según `CLAUDE.md`), empieza con diseño y, al cerrarlo, se actualiza «Lo que la plataforma no cubre» de `criterios.md` y se borra de aquí.

## Cómo ver el estado actual

```bash
sail artisan migrate:fresh --seed --seeder=Nom035EvaluationSeeder
```

Crea `demo-nom035` (`owner@`, `hr@` y `viewer@demo-nom035.test`) con cinco centros: 15, 16, 30, 51 y 100 personas, con
campañas en borrador, activas y cerradas. No manda correos; imprime los enlaces abiertos de las campañas activas.

## Fuera de alcance, por ahora

- **Integración al diagnóstico de la NOM-030 (7.6).** Depende de otra norma y no tiene demanda clara.
- **Unidades de verificación (numeral 9).** Son opcionales para el patrón; basta con que la evidencia sea exportable.
- **Que la Guía III avance sola al contestar (celular).** Ahorraría un toque por reactivo, pero contradice «la encuesta
  nunca avanza sola» (design system, `CLAUDE.md`): quien contesta debe poder corregir antes de seguir.
- **Exámenes médicos y evaluaciones psicológicas (5.6).** Los hace un tercero; el registro de canalización (C-28 de
  `criterios.md`) ya deja la constancia.
- **La exención por el certificado de la NMX-R-025-SCFI-2015** (numeral 2, último párrafo): no se calcula. Es un dato que solo el
  cliente puede afirmar y está sujeta a que el certificado esté vigente; basta con que publique el texto de 5.7 d.
- **PDF de la política ya firmada**: la política se publica como texto. Adjuntarlo pediría un disco privado y un servicio de archivos.
- **Una raíz única del portal personal** que junte los pendientes de todas las herramientas: hoy la persona llega a su política
  por el enlace del correo (`portal/{token}/policy`), como llega a Ley Silla. Un `PortalSection` público que cada módulo
  implemente es la solución de fondo.
- **Mover `PortalAccess` y `PortalIdentity` a `Organization`** (son de la persona, no de NOM-037): cambia imports en Telework,
  Ley Silla y Disclosure, sin riesgo de datos.
- **Un módulo `Acknowledgements` genérico**: Telework y Disclosure duplican con cuidado el patrón «versión × audiencia por centro»
  (cada uno comenta al otro). Cuando aparezca un tercer módulo de la misma forma, se diseña una vez y se migran los dos.
- **Un solo `FormDialog`**: `components/humetria/form-dialog.tsx` y `components/interventions/form-dialog.tsx` hacen lo mismo.
- **Una marca de «visible para las personas» en el Programa**: hoy RH elige mostrar las medidas de todo el centro al publicar
  los resultados (ver «Decisiones al implementar» del diseño de la política). Si se quiere decidir acción por acción, la bandera
  vive en `Interventions` y el adaptador de `Disclosure` la lee.
