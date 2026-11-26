## Context

El proyecto usa Python 3.11+, `psycopg` y PostgreSQL/TimescaleDB. `__main__.py` reconoce únicamente `check`; `db.connect` y `config.database_url` centralizan la conexión. Las tablas `sensors` y `readings` ya incluyen los metadatos y un índice `(sensor_id, time DESC)`. No hay especificaciones funcionales anteriores.

El issue 01 requiere cuatro agregados por día y sensor. Las decisiones de interfaz y límites temporales justifican este diseño antes de implementar.

## Goals / Non-Goals

**Goals:** ofrecer una consulta reproducible, filtrable y verificable, con días UTC y agregación en SQL; mantener operativo `check`.

**Non-Goals:** detectar huecos o anomalías, rellenar lecturas, exportar datos, crear API, precalcular resultados o modificar esquema y semillas.

## Decisions

1. **CLI con `argparse` de la biblioteca estándar.** Añadir `daily-summary` y las opciones `--site`, `--sensor`, `--start-date`, `--end-date`. Sede y código se comparan exactamente, con distinción de mayúsculas. Las fechas usan `YYYY-MM-DD`; ambos extremos son opcionales e inclusivos. Sin filtros se consulta todo el historial disponible. Frente al despacho manual actual, `argparse` proporciona validación y ayuda sin añadir dependencias. `main(argv)` seguirá devolviendo enteros, incluso ante errores de análisis; se adaptará `SystemExit` para conservar el contrato de las pruebas existentes.

2. **Fechas de usuario inclusivas, consulta con intervalo semiabierto UTC.** Convertir inicio a medianoche UTC y fin a la medianoche UTC del día siguiente, aplicando `time >= inicio AND time < fin_exclusivo`. Validar fechas y orden antes de abrir conexión; rechazar extremos cuyo límite exclusivo no sea representable. Frente a comparar fechas convertidas en el `WHERE`, los límites sobre `time` facilitan el uso del índice y la poda temporal. Agrupar con `(r.time AT TIME ZONE 'UTC')::date` para no depender de `TimeZone` de sesión. El horario de verano local no afecta a los días.

3. **Nuevo módulo `analytics.py` con resultado tipado.** Una función de consulta recibirá filtros opcionales, usará `db.connect` como context manager y devolverá solo filas agregadas con día, código, nombre, sede, unidad, mínimo, máximo, media y recuento. SQL unirá las tablas, aplicará filtros parametrizados y calculará `MIN`, `MAX`, `AVG`, `COUNT(*)`, agrupando por sensor y día y ordenando por día ascendente y código ascendente. Si se construyen predicados dinámicos serán fragmentos constantes; los valores irán siempre como parámetros `psycopg`. No se cargarán lecturas para agregar en Python. Frente a pandas o agregación en memoria, SQL respeta las reglas del proyecto sin dependencias adicionales.

4. **Salida tabular estable.** Cabecera `dia_utc`, `sensor`, `nombre`, `sede`, `unidad`, `min`, `max`, `media`, `lecturas`; filas separadas por tabuladores, fechas ISO y valores con tres decimales, punto decimal y recuento entero. Formatear únicamente para mostrar, conservando la precisión del agregado retornado. Sin filas, imprimir `Sin lecturas para los filtros indicados.` y devolver 0, también para sede o sensor desconocidos. Errores de uso devuelven 2; errores `psycopg` devuelven 1 y un mensaje por stderr sin tabla parcial. Se omiten días vacíos en vez de inventar filas con cero; las lecturas anómalas participan como cualquier otra.

5. **Referencias observadas y pruebas independientes.** Consulta SQL de solo lectura ejecutada el 2026-11-26 contra el contenedor saludable del curso, agrupando explícitamente en UTC:

   | Sensor | Día UTC | Unidad | Recuento | Mínimo | Máximo | Media sin formatear | Media de salida |
   | --- | --- | --- | ---: | ---: | ---: | ---: | ---: |
   | VAL-T01 | 2026-08-01 | °C | 288 | 18.60 | 27.46 | 23.013472222222216 | 23.013 |
   | SAG-P02 | 2026-09-03 | bar | 210 | 6.11 | 7.27 | 6.666571428571434 | 6.667 |
   | PAT-L01 | 2026-10-14 | % | 216 | 44.27 | 75.77 | 56.85449074074074 | 56.854 |

   `PAT-L01` no devolvió filas para 2026-10-12 ni 2026-10-13. La semilla elimina 78 lecturas de `SAG-P02` el 3 de septiembre; las 210 restantes justifican la referencia de día incompleto. Las pruebas fijarán estos resultados, con tolerancia absoluta de `1e-9` para las medias de tipo flotante, en vez de generar el resultado esperado con la misma consulta implementada. Las pruebas de frontera usarán lecturas controladas en una transacción revertida, sin DDL y sin alterar datos persistentes, bajo una zona de sesión distinta de UTC.

## Risks / Trade-offs

- [Variaciones minúsculas de coma flotante] → Comparar agregados con tolerancia y verificar por separado los tres decimales de salida.
- [Base externa o dataset modificado] → Documentar que las referencias son del dataset fijo del curso; separar casos de contrato con datos controlados de las comprobaciones de semilla.
- [Consultas sin filtros devuelven todo el historial agregado] → Documentar los filtros; el volumen del curso es pequeño. Paginación y precálculo quedan fuera del issue.
- [Pruebas de integración omitidas sin PostgreSQL] → Arrancar `docker compose up -d` antes de validarlas y registrar explícitamente si alguna se omite.

## Migration Plan

No se requiere migración ni cambio de configuración. Añadir código, pruebas y documentación; ejecutar pruebas y lint. La reversión consiste en revertir esos ficheros, sin tocar la base de datos.

## Open Questions

Ninguna bloqueante. Los detalles abiertos del issue quedan fijados en la especificación: fechas inclusivas, tres decimales y omisión de días sin lecturas.
