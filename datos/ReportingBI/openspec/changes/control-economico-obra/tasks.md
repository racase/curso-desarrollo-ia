## 1. Revisar criterios y preparar acceso

- [ ] 1.1 Revisar los supuestos identificados en el diseño: porcentajes sobre venta/certificado y coste previsto directo incluyendo la revisión de 28.000 € sin fecha; resolver cualquier cambio antes de implementar medidas.
- [ ] 1.2 Comprobar acceso de solo lectura a NAVDEMO mediante `db/query.sh` y conexión al modelo Desktop mediante MCP; si aparece la licencia, solicitar que la acepte la persona.

## 2. Implementar vistas de reporting

- [ ] 2.1 Crear y aplicar `sql/rpt/01_dim_obra.sql` con `rpt.DimObra`, etiquetas de estado confirmadas y filtros resueltos con dimensiones y recursos; comprobar las 10 obras y excluir la empresa de pruebas.
- [ ] 2.2 Crear y aplicar `sql/rpt/02_dim_partida.sql` con `rpt.DimPartida`, claves explícitas y clasificación directo/indirecto; comprobar 100 partidas y exclusión de cabeceras y totales.
- [ ] 2.3 Crear y aplicar `sql/rpt/03_fact_presupuesto.sql` con presupuesto completo y base de venta para producción separados; conciliar coste 30.103.000 € y venta 33.735.000 €, incluyendo la revisión sin fecha solo en las bases acordadas.
- [ ] 2.4 Crear y aplicar `sql/rpt/04_fact_movimiento.sql` con coste y certificado por fecha y partida; conciliar por obra con NAV y totales de coste 25.054.416,01 € y certificado 25.365.250,43 € al 31/10/2026.
- [ ] 2.5 Crear y aplicar `sql/rpt/05_fact_avance.sql` con cierres mensuales, avance cero antes de la primera medición y arrastre posterior; comprobar casos terminados, ausencia de actividad y mediciones únicas por fecha.
- [ ] 2.6 Guardar en el cambio las consultas de conciliación de cada vista frente a tablas NAV y sus resultados por obra; comprobar integridad y unicidad de claves sin modificar origen ni datos generados.

## 3. Crear modelo y medidas mediante MCP

- [ ] 3.1 Preparar `Obras.pbip` y el modelo conectado en Desktop; crear mediante MCP los parámetros `Servidor` y `BaseDatos`, tablas importadas y tipos, sin credenciales en ficheros.
- [ ] 3.2 Crear mediante MCP el calendario y relaciones unidireccionales según diseño; comprobar ausencia de rutas ambiguas y conservación de obras sin actividad.
- [ ] 3.3 Crear medidas de presupuestos, coste real y certificado acumulados y mensuales, preservando presupuesto completo al filtrar fechas; asignar tabla de hechos, descripción de criterio y formato español a cada medida.
- [ ] 3.4 Crear producción acumulada y mensual, avance por partida y avance global ponderado por venta, excluyendo la revisión sin fecha de su base e incluyendo partidas medibles aún no iniciadas.
- [ ] 3.5 Crear coste previsto directo e indirecto por obra, desviación en euros y porcentaje, alerta estricta `> 5 %` y aviso «Coste sin avance»; comprobar que filtrar indirectos no reduce el denominador global de la obra.
- [ ] 3.6 Crear pendiente de certificar y márgenes previstos y reales, con divisiones seguras y totales recalculados desde importes; aplicar redondeo comercial por partida donde exige la spec.

## 4. Validar indicadores y filtros

- [ ] 4.1 Ejecutar SQL independiente sobre NAV y DAX mediante MCP para cada importe por obra y total; guardar consultas, resultados, corte, filtros y diferencias en `validacion.md` dentro de este cambio, sin marcar la tarea hasta obtener diferencia cero.
- [ ] 4.2 Contrastar el cálculo de indirectos en `validacion.md` con una obra de ejemplo ponderado (100.000 € al 100 % y 300.000 € al 0 %), filtro de partida `10` y agregación de varias obras; verificar avance 25 % y coste previsto 5.000 € sobre indirectos de 20.000 €.
- [ ] 4.3 Verificar con casos calculados de solo lectura el umbral: previsto 1.000 €, real 1.050 € sin alerta y 1.050,01 € con alerta; previsto cero con coste positivo muestra aviso y porcentaje vacío, y ambos costes cero no alertan.
- [ ] 4.4 Validar revisión sin fecha, obra sin actividad `OB-26003`, pérdida de `OB-25002`, mediciones acumuladas y producción mensual tras terminar; contrastar también un corte anterior y los filtros de delegación, tipo, jefe y estado.

## 5. Completar informe y entrega con la persona

- [ ] 5.1 Entregar a la persona las medidas y criterios de formato condicional; montar en Desktop resumen, detalle obra × partida y evolución mensual, sin edición PBIR por el agente salvo petición expresa.
- [ ] 5.2 Comprobar ordenación por pendiente, resaltado de pérdidas y desviaciones, aviso sin avance y coherencia de filtros en las páginas; guardar evidencia en `validacion.md`.
- [ ] 5.3 Verificar apertura y actualización de `Obras.pbip` en Desktop sin errores y ausencia de credenciales en los ficheros de entrega.
- [ ] 5.4 Confirmar el área de trabajo con la persona y publicar la versión validada; registrar evidencia de publicación y mantener esta tarea pendiente mientras no exista dicha evidencia.
