## Context

La CLI actual solo admite `check`. La conexión utiliza psycopg y `connect()` como gestor de
contexto. `sensors` contiene código único, sede y unidad; `readings` contiene instante
`timestamptz`, sensor y valor `double precision`, y es una hypertable con índice por sensor
y tiempo. El conjunto inicial contiene 12 sensores, lecturas cada cinco minutos y huecos
y anomalías intencionados. No existen capacidades OpenSpec previas.

## Goals / Non-Goals

**Goals:** consulta desde CLI, filtros combinables, resultados reproducibles por día UTC,
agregación en SQL y una interfaz Python reutilizable para el posterior informe del issue 04.

**Non-Goals:** modificar el esquema, añadir dependencias, limpiar lecturas, completar días,
detectar incidencias, exportar datos, crear una API o precalcular estadísticas.

## Decisions

### 1. CLI con argumentos explícitos

Usar `argparse` de la biblioteca estándar para `resumen-diario`. Conservar la ruta y salida
actuales de `check`, el código 2 para errores de uso y el código 1 para fallos de conexión
o consulta. Los mensajes del nuevo comando serán en castellano.

Los filtros `--sede` y `--sensor` comparan exactamente la sede y el código, con distinción de
mayúsculas y acentos. Las fechas usan exclusivamente `YYYY-MM-DD`. Sin filtros se consulta
todo el histórico; cada extremo es opcional. Rechazar fechas inválidas, un rango invertido
y opciones sin valor antes de abrir una conexión. Los identificadores desconocidos o
una combinación sede/sensor incompatible producen una consulta vacía, sin validaciones extra.

Alternativa: incorporar una librería de CLI. No aporta suficiente valor para este comando
y obligaría a justificar una dependencia nueva.

### 2. Función de consulta y resultado tipado

Crear `src/infradata/analytics.py` con una función `daily_summary`, filtros opcionales de
sede, código y fechas, y resultados tipados mediante una dataclass `DailySummary`.
Cada resultado contendrá sede, código, fecha UTC, unidad, mínimo, máximo, media y recuento.
La función valida también las fechas recibidas desde Python y usa `connect()` como gestor
de contexto. La CLI se encarga de presentar los resultados y gestionar los errores.

Alternativa: incluir el SQL en la CLI. Separarlo permite que el issue 04 reutilice el cálculo
sin capturar ni interpretar texto de terminal. Solo se recuperan filas ya agregadas.

### 3. Agregación SQL y límites UTC

Unir `readings` con `sensors` y agrupar por sensor, sede, unidad y
`(readings.time AT TIME ZONE 'UTC')::date`. Calcular `MIN`, `MAX`, `AVG` y `COUNT(*)`.
Ordenar por fecha UTC y código de sensor ascendentes.

Convertir `--desde` a medianoche UTC inclusiva y `--hasta` a medianoche UTC del día
siguiente exclusiva. Aplicar `time >= %s` y `time < %s` antes de agregar: así se incluye
todo el último día sin depender de la zona horaria de sesión y se permite aprovechar
el filtrado temporal de TimescaleDB. La implementación deberá tratar el desbordamiento
de la fecha máxima admitida como error de uso en castellano, sin traceback.
Añadir solo los predicados presentes, combinados con AND; todos los valores serán parámetros
psycopg y solo los fragmentos SQL constantes se compondrán en código.

Alternativas: agrupar según la zona de sesión daría días distintos; pandas cargaría lecturas
en memoria contra las reglas del proyecto; precalcular requiere otro issue y otro diseño.

### 4. Días ausentes y precisión de salida

No generar filas para días sin lecturas ni para sensores sin datos en el rango. No rellenar
huecos ni descartar anomalías. `AVG(value)` calcula sobre el recuento real, sin usar 288
como denominador. La consulta devuelve valores sin redondear; únicamente la CLI presenta
mínimo, máximo y media con dos decimales fijos, punto decimal y el redondeo del formato
numérico de Python `.2f`. El recuento se muestra como entero.
La CLI añade junto a `Lecturas` una columna `Esperadas`, con el entero fijo 288 en cada fila
(24 horas UTC por 12 lecturas por hora). Es una referencia de presentación, no un recuento
calculado a partir de los datos ni un denominador para la media; no requiere cambiar
`DailySummary` ni el esquema. Su valor se mantiene aunque el día tenga lecturas ausentes
o la zona horaria local cambie de horario.

Salida de texto con cabecera
`Sede | Sensor | Día UTC | Unidad | Mínimo | Máximo | Media | Lecturas | Esperadas`,
una fila por resultado y columnas separadas por ` | `. Sin resultados, mostrar únicamente
`No hay lecturas para los filtros indicados.` y terminar con código 0.

Alternativa: devolver ceros para días vacíos sugeriría observaciones inexistentes y medias
engañosas. La salida no es un contrato CSV; la exportación se abordará en el issue 04.

### 5. Verificación antes y después de implementar

Antes del código de cálculo, obtener de una base inicializada con los scripts del curso
un sensor/día completo y otro incompleto, registrar mínimo, máximo, media, recuento y
unidad como referencias fijas, junto con las consultas de referencia. No inventar las
estadísticas ni calcular las expectativas llamando a la función que se está probando.

Complementar esas referencias con datos sintéticos controlados para medianoche UTC y
fechas inclusivas, cambiando la zona de sesión entre UTC y Europe/Madrid. Las inserciones
de pruebas se revertirán; no modificar los scripts ni los datos iniciales permanentes.
Marcar todas las pruebas que usan PostgreSQL como `integration` y conservar las existentes.

## Risks / Trade-offs

- [Media de un día incompleto] → Mostrar siempre recuento y columna `Esperadas` con 288;
  documentar la comparación, sin excluir ni corregir observaciones.
- [Promedios en coma flotante] → Comparar valores internos con tolerancia explícita en
  pruebas y comprobar por separado la salida fija con dos decimales.
- [Consulta de todo el histórico] → Agregar y filtrar en SQL; aprovechar las tablas e índices
  actuales. No prometer un tiempo máximo ni introducir optimizaciones de esquema.
- [Base local alterada] → Obtener referencias en el conjunto inicial del curso y aislar
  datos sintéticos mediante transacciones que se revierten, sin borrar datos del usuario.

## Migration Plan

Añadir el módulo, extender la CLI, probar y documentar. No hay migración de datos ni de
esquema. Se revierte retirando el nuevo módulo y subcomando; `check` y las lecturas permanecen.

## Open Questions

No quedan decisiones funcionales abiertas. Las estadísticas del conjunto fijo se han
consultado y registrado en [referencias.md](referencias.md); véase allí la desviación del
orden previsto de obtención respecto a la escritura del módulo.
