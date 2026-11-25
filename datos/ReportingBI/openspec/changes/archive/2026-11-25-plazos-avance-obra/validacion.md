# Validación SQL de plazos y avance

Fecha: 25/11/2026. Base: `NAVDEMO`. Empresa operativa confirmada en `[dbo].[Company]`: `CONSTRUCTORA DEMO`; empresa de pruebas excluida. Corte global obtenido de NAV: **31/10/2026**. Contenedor `reportingbi-db` saludable. Toda la ejecución utilizó `reporting` mediante la ruta relativa `db/query.sh`, sin guardar credenciales.

## Alcance realizado

Cuatro vistas nuevas aplicadas, una por fichero:

| Fichero | Vista | Filas |
| --- | --- | ---: |
| `sql/rpt/06_fact_planificacion.sql` | `rpt.FactPlanificacion` | 642 |
| `sql/rpt/07_fact_medicion_avance.sql` | `rpt.FactMedicionAvance` | 514 |
| `sql/rpt/08_fact_hito.sql` | `rpt.FactHito` | 50 |
| `sql/rpt/09_dim_corte_datos.sql` | `rpt.DimCorteDatos` | 1 |

Se conservaron sin cambios las cinco vistas SQL del issue 01. No se editó el modelo, DAX ni el informe. Los estados y avances de las comprobaciones son consultas de validación; las vistas publican las fechas y los hechos necesarios para calcularlos posteriormente según filtros.

## Fuentes y diagnóstico

`preparacion.sql` consulta campos y tipos reales. Opciones utilizadas, ya documentadas: `Job Task Type = 0` indica detalle, `Line Type` 1/2 aporta venta y la partida `10` identifica indirectos. Los valores presentes son tipos de tarea 0/3/4 y tipos de línea 0/2. No aparecieron opciones desconocidas.

Las tablas a medida son `Job Progress Entry` y `Job Milestone`. No hay mediciones fuera del rango 0–100 ni sin fecha; ninguna medición apunta a una tarea ausente, no detallada o indirecta. No se encontraron líneas de planificación ni hitos huérfanos. Los 50 hitos tienen planificación válida; 13 carecen de fecha real, convertida a NULL.

## Conciliación por obra

`validacion_vistas.sql` construye el esperado desde `dbo`, sin depender de las dimensiones `rpt`, y compara todas las columnas en ambos sentidos. Comprueba además claves duplicadas, cardinalidad del corte y resúmenes por las diez obras. Los doce controles devolvieron **cero diferencias**.

| Obra | Venta NAV = vista (€) | Base elegible NAV = vista (€) | Mediciones NAV = vista | Hitos NAV = vista |
| --- | ---: | ---: | ---: | ---: |
| OB-25001 | 6.800.000,00 | 6.800.000,00 | 85 | 5 |
| OB-25002 | 2.400.000,00 | 2.400.000,00 | 53 | 5 |
| OB-25003 | 5.100.000,00 | 5.100.000,00 | 69 | 5 |
| OB-25004 | 1.635.000,00 | 1.600.000,00 | 62 | 5 |
| OB-25005 | 3.900.000,00 | 3.900.000,00 | 60 | 5 |
| OB-25006 | 4.200.000,00 | 4.200.000,00 | 69 | 5 |
| OB-25007 | 1.200.000,00 | 1.200.000,00 | 43 | 5 |
| OB-26001 | 3.200.000,00 | 3.200.000,00 | 43 | 5 |
| OB-26002 | 4.400.000,00 | 4.400.000,00 | 30 | 5 |
| OB-26003 | 900.000,00 | 900.000,00 | 0 | 5 |
| **Total** | **33.735.000,00** | **33.700.000,00** | **514** | **50** |

En todas las obras, diferencia monetaria **0,00 €**; cada medición y cada hito coincide también en claves, fechas y valores. La revisión de `OB-25004|06` conserva 35.000,00 € con fecha NULL y base elegible 0,00 €. No participa en ninguno de los dos términos del avance planificado. La explicación visible queda pendiente para la fase del informe.

## Avances mensuales

`validacion_avances.sql` compara NAV y las vistas en **1.980 combinaciones partida × cierre**: 90 partidas directas, diez obras y 22 cierres entre enero de 2025 y octubre de 2026. Conserva el denominador completo, elige la última medición y redondea producción al céntimo por partida, como el issue 01.

- Máxima diferencia en base y venta planificada acumulada: **0,00 €**.
- Máxima diferencia en última medición: **0,000000**.
- Máxima diferencia en producción: **0,00 €**.
- Otras 1.980 comparaciones con `FactAvance` y `FactPresupuesto` del issue 01: diferencia cero en avance y producción dentro del horizonte común.
- Evidencia de 242 filas agregadas: diez obras y total para cada uno de los 22 cierres.

Al corte, el conjunto tiene planificado **83,54927944 %** y real **80,76407893 %**. `OB-25006` tiene planificado 100 % y real 79,8072 %. `OB-26003` conserva avance planificado y real cero. El corte se obtiene de los movimientos globales, aunque una obra terminada tenga un último movimiento anterior o una obra futura no tenga ninguno.

## Hitos y cortes históricos

`validacion_hitos.sql` compara estados y días de **1.100 combinaciones hito × cierre** contra NAV: diferencia cero en ambos sentidos. Incluye 413 combinaciones cuya fecha real es posterior al corte consultado y que, correctamente, aún se consideran pendientes o retrasadas.

| Estado al 31/10/2026 | Hitos |
| --- | ---: |
| Cumplido a tiempo | 24 |
| Cumplido con retraso | 13 |
| Pendiente | 12 |
| Retrasado | 1 |

El único pendiente vencido es `OB-25006/HIT05`, con **31 días**. Sus cuatro hitos cumplidos tarde no incrementan ese contador; el máximo retraso histórico de los cumplidos es 113 días y se muestra separado. No se suman demoras solapadas. La obra futura solo tiene hitos pendientes.

Siete casos de frontera con filas de sesión, sin insertar datos en NAV, pasan: cumplimiento a tiempo, cumplimiento tardío, vencimiento igual al corte, vencimiento anterior, fecha real posterior al corte, planificación futura y cumplimiento exactamente en el corte.

## Preservación y reproducción

`evidencias/sql01_antes.sha256` y `sql01_despues.sha256` son idénticos; `sha256sum -c` devuelve OK para los cinco ficheros. `preparacion_antes.txt` y `preparacion_despues.txt` también son idénticos byte a byte: incluyen hashes SHA-256 y definiciones completas fragmentadas de las cinco vistas existentes, además del diagnóstico de origen.

Desde la carpeta del proyecto, se aplicó cada fichero 06–09 con este patrón:

```bash
docker compose exec -T db bash db/query.sh -f sql/rpt/06_fact_planificacion.sql
```

Las comprobaciones son reproducibles con:

```bash
docker compose exec -T db bash db/query.sh -f openspec/changes/archive/2026-11-25-plazos-avance-obra/validacion_vistas.sql
docker compose exec -T db bash db/query.sh -f openspec/changes/archive/2026-11-25-plazos-avance-obra/validacion_avances.sql
docker compose exec -T db bash db/query.sh -f openspec/changes/archive/2026-11-25-plazos-avance-obra/validacion_hitos.sql
```

Las salidas completas están en `evidencias/validacion_vistas.txt`, `validacion_avances.txt` y `validacion_hitos.txt`. Los scripts fallan con `THROW` ante diferencias. Las cifras de referencia sin fecha y obra futura corresponden a la semilla actual; si cambia NAV, deberán revisarse antes de atribuir un fallo a las vistas.

## Continuación del modelo y pendientes

Tras la petición posterior «continua», se incorporaron las tablas, relaciones y medidas mediante MCP y se completó la conciliación SQL/DAX. La evidencia de esa fase está en `validacion_modelo.md`.

La continuación del informe y la validación local aceptada por el usuario se documentan en `validacion_informe.md`. Se autoriza archivar y hacer commit con la publicación pendiente, sin declararla realizada.
