# Validación de las vistas SQL — control económico de obra

Ejecución: 25/11/2026. Base `NAVDEMO`, usuario `reporting`, empresa `CONSTRUCTORA DEMO`. Corte de movimientos y avance: **31/10/2026**; presupuestos completos, incluidas fechas futuras y revisión sin fecha.

Se han aplicado las cinco vistas y comprobado sus resultados contra las tablas NAV. La sección inicial documenta las vistas SQL. La validación posterior del modelo, de DAX y del informe se recoge al final; únicamente la publicación sigue pendiente.

## Aplicación y reproducción

Cada comando de aplicación terminó con código 0. Ejecutar desde la raíz de ReportingBI, en este orden:

```bash
docker compose exec -T db bash db/query.sh -f sql/rpt/01_dim_obra.sql
docker compose exec -T db bash db/query.sh -f sql/rpt/02_dim_partida.sql
docker compose exec -T db bash db/query.sh -f sql/rpt/03_fact_presupuesto.sql
docker compose exec -T db bash db/query.sh -f sql/rpt/04_fact_movimiento.sql
docker compose exec -T db bash db/query.sh -f sql/rpt/05_fact_avance.sql
docker compose exec -T db bash db/query.sh -f openspec/changes/archive/2026-11-25-control-economico-obra/validacion_vistas.sql
```

Con Podman, sustituir `docker compose` por `podman compose`. El [script de conciliación](validacion_vistas.sql) solo consulta datos y lanza `THROW` ante diferencias de filas, integridad o casos especiales. No crea objetos ni modifica NAV.

## Conciliación obra a obra

Cada importe de esta tabla es idéntico en NAV y en la vista. La última columna es la máxima diferencia absoluta entre ambas fuentes para los cuatro importes. La salida íntegra más abajo conserva ambos valores y cada diferencia por separado.

| Obra | Presupuesto coste, € | Presupuesto venta, € | Coste real, € | Certificado, € | Máx. diferencia, € |
|---|---:|---:|---:|---:|---:|
| OB-25001 | 6.052.000,00 | 6.800.000,00 | 6.081.367,59 | 6.800.000,00 | 0,00 |
| OB-25002 | 2.208.000,00 | 2.400.000,00 | 2.525.524,14 | 2.400.000,00 | 0,00 |
| OB-25003 | 4.488.000,00 | 5.100.000,00 | 4.298.541,71 | 4.263.100,20 | 0,00 |
| OB-25004 | 1.420.000,00 | 1.635.000,00 | 1.389.765,92 | 1.600.000,00 | 0,00 |
| OB-25005 | 3.510.000,00 | 3.900.000,00 | 2.846.534,34 | 2.400.076,23 | 0,00 |
| OB-25006 | 3.780.000,00 | 4.200.000,00 | 3.072.276,21 | 3.132.486,00 | 0,00 |
| OB-25007 | 1.092.000,00 | 1.200.000,00 | 1.096.906,65 | 1.200.000,00 | 0,00 |
| OB-26001 | 2.880.000,00 | 3.200.000,00 | 2.137.186,58 | 2.067.296,00 | 0,00 |
| OB-26002 | 3.872.000,00 | 4.400.000,00 | 1.606.312,87 | 1.502.292,00 | 0,00 |
| OB-26003 | 801.000,00 | 900.000,00 | 0,00 | 0,00 | 0,00 |
| **Total** | **30.103.000,00** | **33.735.000,00** | **25.054.416,01** | **25.365.250,43** | **0,00** |

## Cobertura de las cinco vistas

| Vista | Resultado | Contraste con origen |
|---|---|---|
| `DimObra` | 10 obras | Todas las columnas, claves, estados, etiquetas y fechas; empresa de pruebas excluida |
| `DimPartida` | 100 partidas: 10 por obra | Todas las columnas; sin cabeceras ni totales; 10 indirectas |
| `FactPresupuesto` | 100 filas | Todos los importes y fechas por partida, además de totales por obra |
| `FactMovimiento` | 2.112 filas agregadas de 2.777 movimientos NAV | Todas las columnas por obra, partida y fecha, con signos de negocio |
| `FactAvance` | 1.980 instantáneas: 198 por obra | 9 partidas medibles × 22 cierres, enero de 2025 a octubre de 2026 |

Se comparan las filas completas en ambos sentidos con `EXCEPT`, y se verifican por separado duplicados y claves huérfanas. En avance, el resultado esperado se obtiene directamente de NAV con calendario recursivo y `ROW_NUMBER`, sin usar la selección `TOP/OUTER APPLY` de la vista. Los 22 cierres, incluido un corte anterior al actual, dan cero diferencias. La suma de avances que aparece en la salida es solo un control de datos por partida; no representa el avance global ponderado de la obra.

Comprobaciones adicionales superadas:

- No hay opciones nuevas sin interpretar, códigos con el separador de clave ni mediciones duplicadas por obra, partida y fecha.
- `OB-26003` conserva su presupuesto y tiene avance cero en todos los cierres; no se han creado movimientos artificiales.
- `OB-25001|01` conserva al 31/10/2026 el avance del 100 % de su última medición del 31/03/2025.
- `OB-25004|06` conserva 28.000 € de coste y 35.000 € de venta sin fecha; la base de venta productiva total es 33.700.000 €, frente a los 33.735.000 € de presupuesto de venta.
- Las fechas vacías se convierten a NULL en las columnas expuestas. Los mensajes informativos por agregación de NULL corresponden a esa conversión; la ejecución termina con código 0.

## Límites y trabajo pendiente

`FactAvance` expone una fracción (0–1) y no contiene indirectos, que carecen de medición propia. El coste previsto indirecto según avance global y las alertas del 5 % están implementados y validados con DAX; véase la sección del modelo.

El corte inicial de `FactAvance` está declarado en su fichero como `20261031`. Las instantáneas son mensuales y no se deben sumar entre meses. Las tablas, relaciones y medidas ya se han creado mediante MCP. El proyecto guardado por la persona se encuentra en `ObrasModel/Obras.pbip`; los visuales, la reapertura y la actualización se han comprobado; la publicación sigue pendiente.

## Salida íntegra de la conciliación

```text
Prueba|Fallos
------|------
DimObra: comparación completa|0
Prueba|Fallos
------|------
DimPartida: comparación completa|0
Warning: Null value is eliminated by an aggregate or other SET operation.
Prueba|Fallos
------|------
FactPresupuesto: comparación completa|0
Prueba|Fallos
------|------
FactMovimiento: comparación completa|0
Prueba|Fallos
------|------
FactAvance: todos los cierres contra NAV|0
Warning: Null value is eliminated by an aggregate or other SET operation.
Prueba|Fallos
------|------
Claves, integridad, opciones y fechas|0
Obra|PptoCosteNAV|PptoCosteVista|DifPptoCoste|PptoVentaNAV|PptoVentaVista|DifPptoVenta|CosteRealNAV|CosteRealVista|DifCosteReal|CertificadoNAV|CertificadoVista|DifCertificado
----|------------|--------------|------------|------------|--------------|------------|------------|--------------|------------|--------------|----------------|--------------
OB-25001|6052000.00|6052000.00|.00|6800000.00|6800000.00|.00|6081367.59|6081367.59|.00|6800000.00|6800000.00|.00
OB-25002|2208000.00|2208000.00|.00|2400000.00|2400000.00|.00|2525524.14|2525524.14|.00|2400000.00|2400000.00|.00
OB-25003|4488000.00|4488000.00|.00|5100000.00|5100000.00|.00|4298541.71|4298541.71|.00|4263100.20|4263100.20|.00
OB-25004|1420000.00|1420000.00|.00|1635000.00|1635000.00|.00|1389765.92|1389765.92|.00|1600000.00|1600000.00|.00
OB-25005|3510000.00|3510000.00|.00|3900000.00|3900000.00|.00|2846534.34|2846534.34|.00|2400076.23|2400076.23|.00
OB-25006|3780000.00|3780000.00|.00|4200000.00|4200000.00|.00|3072276.21|3072276.21|.00|3132486.00|3132486.00|.00
OB-25007|1092000.00|1092000.00|.00|1200000.00|1200000.00|.00|1096906.65|1096906.65|.00|1200000.00|1200000.00|.00
OB-26001|2880000.00|2880000.00|.00|3200000.00|3200000.00|.00|2137186.58|2137186.58|.00|2067296.00|2067296.00|.00
OB-26002|3872000.00|3872000.00|.00|4400000.00|4400000.00|.00|1606312.87|1606312.87|.00|1502292.00|1502292.00|.00
OB-26003|801000.00|801000.00|.00|900000.00|900000.00|.00|.00|.00|.00|.00|.00|.00
Obra|Partidas|Instantaneas|Cierres|SumaAvancePartidasAlCorte
----|--------|------------|-------|-------------------------
OB-25001|10|198|22|9.000000
OB-25002|10|198|22|9.000000
OB-25003|10|198|22|7.566600
OB-25004|10|198|22|9.000000
OB-25005|10|198|22|7.912300
OB-25006|10|198|22|6.814400
OB-25007|10|198|22|9.000000
OB-26001|10|198|22|6.393500
OB-26002|10|198|22|3.886000
OB-26003|10|198|22|.000000
PresupuestoCoste|PresupuestoVenta|BaseVentaProduccion|CosteSinFecha|VentaSinFecha
----------------|----------------|-------------------|-------------|-------------
30103000.00|33735000.00|33700000.00|28000.00|35000.00
FilasMovimiento|MovimientosNAV|CosteReal|Certificado
---------------|--------------|---------|-----------
2112|2777|25054416.01|25365250.43
Prueba|Fallos
------|------
Casos sin actividad, arrastre y revisión sin fecha|0
```



## Modelo semántico y DAX — 25/11/2026

**Proyecto activo:** [ObrasModel/Obras.pbip](../../../../ObrasModel/Obras.pbip). La persona lo guardó desde Desktop como PBIX y después como PBIP, ambos bajo `ObrasModel/`. Sus referencias enlazan correctamente `Obras.Report` con `Obras.SemanticModel`. La exportación previa mediante MCP en la carpeta raíz `Obras.SemanticModel/definition/` es una copia del modelo, no el proyecto activo que debe abrirse.

Se crearon mediante el MCP oficial seis tablas, ocho relaciones activas 1:N unidireccionales y 24 medidas. El calendario continuo, marcado como tabla de fechas, abarca 01/01/2024–31/12/2027 (1.461 días). Se cargaron 10 obras, 100 partidas, 100 presupuestos, 2.112 filas de movimientos y 1.980 instantáneas. Las particiones usan `Sql.Database(Servidor, BaseDatos)` y las dos expresiones son parámetros de texto. No se han guardado credenciales. Posteriormente la persona autorizó expresamente editar PBIR para completar los visuales.

Las medidas pertenecen a sus hechos y describen el criterio de la spec, con cultura `es-ES` y formatos `#,0.00 €` / `0.0 %`. La medida de certificación se llama **Importe certificado** porque `Certificado` ya es una columna de `FactMovimiento`. Se han aplicado los porcentajes de margen sobre venta presupuestada y certificado, respectivamente, y el coste directo previsto incluye la revisión de 28.000 € conforme al presupuesto completo de la spec.

La conexión se volvió a establecer después de cada Guardar como de Desktop para renovar el estado del modelo. La última actualización completa mediante MCP terminó sin error después de guardar PBIP. Se comprobó que las 24 medidas están presentes también en los TMDL que guardó Desktop. La reapertura, actualización y revisión de las tres páginas se completaron después; véase la sección del informe PBIR.

### Conciliación SQL frente a DAX

- [Referencia SQL](validacion_modelo.sql): consultas exclusivamente sobre tablas NAV `dbo`, sin utilizar vistas `rpt`.
- [Consulta DAX](validacion_modelo.dax): ejecutada mediante MCP, salida Inline sin truncamiento.
- [Comparador](comparar_modelo.py) y [resumen de resultados](evidencias/comparacion_modelo.json).
- Salidas completas: [SQL](evidencias/modelo_sql.txt) y [DAX](evidencias/modelo_dax.json).

Se compararon **2.442 filas = 22 cierres × (100 partidas + 10 obras + 1 total)** de enero de 2025 a octubre de 2026, con 21 valores por fila: 16 importes, 4 ratios y 1 indicador de alerta. Los filtros de fecha incluyen el mes completo: acumulados al cierre y flujos mensuales no se confunden con movimientos del último día.

Resultado: **cero claves diferentes y cero diferencias**; máxima diferencia monetaria y de alertas **0**. Para ratios, tolerancia de comparación 0,00000001 en fracción; diferencia máxima observada 9.9946628E-11. Se comprobaron también los valores vacíos cuando el denominador es cero. No se redondearon importes discrepantes para hacerlos coincidir.

En la tabla siguiente, cada cifra es idéntica en NAV y DAX al 31/10/2026; la última columna es la máxima diferencia absoluta en importes de la obra.

| Obra | Producción, € | Coste previsto, € | Previsto indirecto, € | Pendiente certificar, € | Margen real, € | Máx. diferencia, € |
|---|---:|---:|---:|---:|---:|---:|
| OB-25001 | 6.800.000,00 | 6.052.000,00 | 408.000,00 | 0,00 | 718.632,41 | 0,00 |
| OB-25002 | 2.400.000,00 | 2.208.000,00 | 144.000,00 | 0,00 | -125.524,14 | 0,00 |
| OB-25003 | 4.540.672,80 | 3.995.792,06 | 272.440,37 | 277.572,60 | -35.441,51 | 0,00 |
| OB-25004 | 1.600.000,00 | 1.420.000,00 | 96.000,00 | 0,00 | 210.234,08 | 0,00 |
| OB-25005 | 3.151.629,00 | 2.836.466,11 | 189.097,74 | 751.552,77 | -446.458,11 | 0,00 |
| OB-25006 | 3.351.902,40 | 3.016.712,16 | 201.114,14 | 219.416,40 | 60.209,79 | 0,00 |
| OB-25007 | 1.200.000,00 | 1.092.000,00 | 72.000,00 | 0,00 | 103.093,35 | 0,00 |
| OB-26001 | 2.371.411,20 | 2.134.270,08 | 142.284,67 | 304.115,20 | -69.890,58 | 0,00 |
| OB-26002 | 1.801.879,20 | 1.585.653,69 | 108.112,75 | 299.587,20 | -104.020,87 | 0,00 |
| OB-26003 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| **Total** | **27.217.494,60** | **24.340.894,10** | **1.633.049,67** | **1.852.244,17** | **310.834,42** | **0,00** |

Reproducción:

```bash
docker compose exec -T db bash db/query.sh -f openspec/changes/archive/2026-11-25-control-economico-obra/validacion_modelo.sql > /tmp/reportingbi_modelo_sql.txt
python3 openspec/changes/archive/2026-11-25-control-economico-obra/comparar_modelo.py /tmp/reportingbi_modelo_sql.txt openspec/changes/archive/2026-11-25-control-economico-obra/evidencias/modelo_dax.json
```

Para validar una nueva ejecución, ejecutar también `validacion_modelo.dax` mediante MCP, con `resultMode: Inline` y `maxRows: 5000`, y sustituir el segundo argumento del comparador por el JSON `data` de esa respuesta. Las claves de partida contienen `|`; el comparador lo contempla al leer la salida de sqlcmd.

### Indirectos, filtros y casos frontera

Consultas y respuestas DAX: [validacion_casos.json](validacion_casos.json). Referencia adicional SQL: [validacion_casos.sql](validacion_casos.sql) y [su salida](evidencias/casos_sql.txt).

- **Ponderación:** venta 100.000 € al 100 % y 300.000 € al 0 % dan avance global **25 %**; sobre 20.000 € de presupuesto indirecto, previsto **5.000 €** en SQL y DAX.
- **Solo indirectos:** las 10 obras conservan el avance global y el coste indirecto de la conciliación completa al filtrar únicamente la partida `10`: **cero diferencias**.
- **Filtros de negocio:** 3 delegaciones, 5 tipos, 4 responsables y 3 estados (**15 grupos**). Los importes, ratios y alertas coinciden con la agrupación independiente del origen: **0 diferencias**. Sin filtro temporal, las medidas de flujos suman los periodos disponibles hasta el corte.
- **Frontera:** previsto 1.000 €, real 1.050 € → 5 % y sin alerta; real 1.050,01 € → 5,001 % y alerta.
- **Sin avance:** previsto cero y real 100 € → aviso «Coste sin avance», indicador 1 y porcentaje vacío. Ambos costes cero → sin aviso, indicador 0 y porcentaje vacío.
- **Excepciones reales:** la revisión de `OB-25004/06`, la ausencia de actividad de `OB-26003`, la pérdida de `OB-25002` y el arrastre de mediciones terminadas se contrastaron en todos los cierres disponibles.

Los casos sintéticos usan definiciones DAX limitadas a la consulta, conservando las expresiones de las medidas dependientes y sustituyendo sus entradas. No cambian medidas persistentes ni datos de NAV. Los filtros de negocio y de indirectos utilizan las medidas persistentes sin sustituciones.

### Estado de entrega

Modelo y medidas implementados y conciliados. El PBIP y sus TMDL guardados por Desktop están en `ObrasModel/`. Las tareas 5.1–5.3 están completadas. Solo permanece abierta 5.4: confirmar área de trabajo y publicar la versión validada. El cambio completo no se considera terminado ni archivado.


## Informe PBIR y revisión en Desktop — 25/11/2026

La persona autorizó completar el informe editando PBIR y confirmó que había guardado y cerrado Desktop. Se generaron las tres páginas en `ObrasModel/Obras.Report/definition/`; no se modificó el modelo semántico ni el origen de datos durante esta fase.

| Página | Contenido | Evidencia de Desktop |
|---|---|---|
| Resumen | Ocho indicadores, tabla por obra ordenada por pendiente, márgenes negativos en rojo y desviaciones destacadas | [Captura](evidencias/resumen_desktop.png) |
| Detalle por partida | Coste real y previsto, desviación, producción, certificación, márgenes y aviso de sobrecoste; partidas ordenadas por desviación | [Captura](evidencias/detalle_desktop.png) |
| Evolución mensual | Gráfico de coste, producción y certificado mensuales; tabla con flujos y acumulados al cierre | [Captura](evidencias/evolucion_desktop.png) |

Hay 45 objetos: 16 tarjetas de indicadores, 18 segmentadores, tres tablas, un gráfico de líneas y siete cuadros de texto. Las tres páginas comparten seis grupos sincronizados: delegación, tipo, responsable, estado, obra y fecha límite. El rango de datos inicial es enero de 2025 a octubre de 2026; el presupuesto completo conserva fechas futuras y revisión sin fecha por definición de sus medidas.

Los selectores de tabla y las reglas de color usan las medidas existentes. Las desviaciones superiores al 5 % se resaltan en rojo; el aviso «Coste sin avance» usa ámbar y mantiene el porcentaje vacío. El 5 % exacto no activa la alerta. La tabla de detalle conserva las partidas con presupuesto o coste dentro del filtro de obra, incluida la obra sin actividad, y evita mostrar combinaciones ajenas a dicho filtro.

### Validación estática

- Generador reproducible: `tools/build_report.py`, que requiere Desktop cerrado. Conserva los identificadores de sus páginas y no elimina páginas adicionales de la persona.
- Validador: `tools/validar_informe.py`, con dependencia `jsonschema`.
- Se validaron 50 archivos contra los esquemas oficiales publicados por Microsoft, incluidos los esquemas de expresiones y filtros: **cero errores**.
- Se comprobó la existencia de cada columna y medida en el TMDL del modelo asociado, las referencias de formato condicional, las seis agrupaciones sincronizadas y la ausencia de objetos fuera del lienzo o solapados.
- Se usó el esquema público `report/3.3.0`, conservando el tema Fluent que guardó Desktop; la instalación había emitido inicialmente una referencia `report/3.4.0` aún no publicada. Desktop cargó la definición resultante correctamente.
- Resultado estructurado: [validacion_pbir.json](evidencias/validacion_pbir.json).

Fuentes oficiales de formato: [PBIR](https://learn.microsoft.com/en-us/power-bi/developer/projects/projects-report#pbir-format), [esquemas JSON de Microsoft](https://github.com/microsoft/json-schemas/tree/main/fabric/item/report/definition) y [referencias de visuales de Microsoft](https://github.com/microsoft/skills-for-fabric/tree/main/skills/powerbi-report-cli/references/authoring).

### Comprobación real

Se abrió `ObrasModel/Obras.pbip` con Power BI Desktop y se revisaron las tres páginas renderizadas. No aparecieron avisos de campos inexistentes ni errores de visuales. Se reconectó el MCP al modelo reabierto y la actualización completa terminó correctamente.

Se seleccionó Delegación Norte en el resumen y se comprobó que el detalle y la evolución conservaban esa selección. Sus importes coinciden con la validación anterior de filtros:

| Indicador, Delegación Norte | Valor |
|---|---:|
| Presupuesto de coste | 11.455.000,00 € |
| Coste real | 10.024.808,58 € |
| Coste previsto | 9.980.466,11 € |
| Desviación | 44.342,47 € |
| Producción | 11.151.629,00 € |
| Certificado | 10.400.076,23 € |
| Pendiente de certificar | 751.552,77 € |

Evidencias de sincronización: [detalle filtrado](evidencias/filtro_norte_detalle.png) y [evolución filtrada](evidencias/filtro_norte_evolucion.png). La definición guardada conserva los filtros iniciales sin selecciones de prueba; el informe permanece abierto en Desktop. Se descartaron las capturas auxiliares no verificadas. Las capturas muestran la ordenación por pendiente y por desviación y el resaltado de pérdidas y sobrecostes. Los casos sintéticos de umbral y denominador cero mantienen su validación DAX documentada anteriormente.

Se comprobó la ausencia de credenciales en las definiciones de entrega. El informe local está completado; la publicación no se ha realizado y la tarea 5.4 permanece pendiente.


## Aceptación final de la persona — 25/11/2026

La persona confirmó su validación en Power BI y solicitó dar el trabajo por hecho, archivar el cambio y crear el commit. Se acepta la entrega local con sus pruebas y evidencias. La publicación en el servicio no se ha realizado ni se da por realizada: la tarea 5.4 queda abierta como excepción aceptada para este archivo.
