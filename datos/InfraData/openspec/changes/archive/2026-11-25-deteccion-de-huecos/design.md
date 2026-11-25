## Context

InfraData usa Python, psycopg, PostgreSQL y TimescaleDB. Las lecturas del curso tienen
cadencia fija de cinco minutos. Existen `check` y `resumen-diario`; el issue 02 no debe
depender de sus resultados. Las referencias independientes están en
[referencias.md](referencias.md); no se han deducido de la futura implementación.

## Goals / Non-Goals

**Goals:** delimitar interrupciones por sensor, distinguir duración observada y mínima,
resolver los extremos del rango, filtrar y verificar exhaustividad sobre el conjunto fijo.

**Non-Goals:** rellenar lecturas, identificar causas, detectar anomalías, alertar,
modificar esquema o añadir dependencias.

## Decisions

### 1. Duración y umbral

Un hueco completo tiene dos lecturas consecutivas del mismo sensor como extremos.
Su duración es siguiente menos anterior. No se restan los cinco minutos de cadencia.
Por ejemplo, de 10:00 a 10:20 son 20 minutos entre lecturas, aunque falten tres lecturas
esperadas y su tiempo equivalente sea 15 minutos.

`--umbral-minutos` acepta enteros positivos y vale 5 por defecto. La comparación es
estrictamente `duración > umbral`, sin redondeo previo. Un intervalo normal de cinco
minutos queda excluido; uno de diez queda incluido por defecto. Los 15 minutos de la
consulta de referencia son un valor explícito para aceptación, no el valor predeterminado.

Alternativa: restar la cadencia y medir tiempo de lecturas ausentes. Es una magnitud
distinta de la solicitada y exigiría otro contrato para los extremos abiertos.

### 2. Fechas y selección por solapamiento

`--desde` y `--hasta` admiten días `YYYY-MM-DD` inclusivos UTC. Internamente forman
un rango `[A, B)`, con B en la medianoche posterior al último día. Cada fecha puede
omitirse: el extremo ausente usa el inicio o fin del conjunto fijo del curso,
respectivamente `2026-08-01T00:00:00Z` y `2026-10-31T00:00:00Z`. No usar hoy ni la
primera/última lectura de cada sensor como sustitutos: ocultaría ausencias de borde.
Este periodo es una decisión explícita para el conjunto didáctico; una futura cobertura
dinámica requeriría definir el periodo de observación por separado.

Un hueco completo `(anterior, siguiente)` se incluye si `anterior < B` y `siguiente > A`
y supera el umbral. Se muestran sus extremos reales y su duración total, sin recortarlos.
El contacto puntual con un límite no constituye solapamiento. Hay que buscar contexto
fuera del rango: filtrar las lecturas por fechas antes de obtener sus vecinas perdería
huecos, especialmente cuando no hay ninguna lectura dentro del rango.

### 3. Extremos desconocidos

Si no existe una lectura previa en todo el histórico disponible, el extremo inicial es
desconocido; si no existe una posterior, el final es desconocido. No sustituir un extremo
desconocido por una lectura ficticia en A o B.

La duración mínima es la longitud de la parte sin lecturas observable en `[A, B)`:
desde A hasta la primera lectura, desde la última hasta B, o B menos A si no hay
lecturas disponibles para ese sensor en ningún punto del histórico. Los límites conocidos
fuera del rango se recortan solo para este cálculo mínimo. Si hay lecturas a ambos lados
del rango vacío, existe un hueco completo y se aplica la regla anterior.

Mostrar un extremo desconocido como `desconocido`, el estado correspondiente y
`>= HH:MM:SS` como duración mínima. Solo listar un hueco abierto cuando esa duración
mínima supere el umbral. Si no lo supera, omitirlo: no hay evidencia suficiente para
acreditar que supera el umbral, aunque su duración real podría hacerlo.
Incluir sensores conocidos sin lecturas, partiendo del catálogo de sensores filtrado.
Una primera lectura exactamente en A o una ausencia observable de duración cero no
generan un hueco abierto. No inferir el funcionamiento real del sensor fuera del periodo
observado: la salida describe ausencia de datos, no demuestra una avería.

### 4. Consulta SQL e interfaz reutilizable

Extender `analytics.py` con una consulta reutilizable y un resultado tipado que contenga
sede, código, extremos opcionales, duración y estado. La duración es exacta en los huecos
completos y mínima en los abiertos, identificada explícitamente por su estado.

Resolver vecindad por sensor con funciones de ventana SQL y completar los bordes usando
el catálogo y las lecturas de contexto. Calcular diferencias, solapamientos y umbrales en
PostgreSQL; recuperar únicamente los huecos finales. Los filtros por sede y sensor pueden
aplicarse antes de la vecindad; las fechas no deben eliminar el contexto necesario.
Usar `connect()` como gestor de contexto y parametrizar todos los valores.

Alternativa: recorrer lecturas en Python. Cargaría el histórico en memoria y trasladaría
el cálculo fuera de SQL, contra las reglas del proyecto. No se necesita otra dependencia.

### 5. CLI, presentación y errores

Comando `huecos` con `--umbral-minutos`, `--sede`, `--sensor`, `--desde` y `--hasta`.
Sede y código se comparan exactamente, distinguiendo mayúsculas y acentos; los filtros
se combinan con AND. Identificadores desconocidos o incompatibles producen cero filas.

Cabecera: `Sede | Sensor | Última lectura UTC | Siguiente lectura UTC | Duración | Estado`.
Fechas con formato `YYYY-MM-DD HH:MM:SS UTC`; duraciones con horas acumuladas
`HH:MM:SS`, sin reiniciar a las 24 horas. Estados: `completo`, `abierto al inicio`,
`abierto al final` y `abierto en ambos extremos`. Ordenar por inicio observable ascendente
(A si el inicio es desconocido), código de sensor y fin observable.

Sin filas: `No hay huecos que superen el umbral para los filtros indicados.`, sin cabecera,
código 0. Validar argumentos antes de conectar: umbral no entero o no positivo,
fechas inválidas, rango efectivo invertido, opciones incompletas y desbordamiento al
calcular B dan código 2 y mensaje en castellano por stderr. Un fallo de base de datos
da código 1, sin traceback ni resultados parciales. Conservar los comandos existentes.

## Risks / Trade-offs

- Los extremos abiertos solo aportan cotas mínimas; documentar que pueden omitirse
  interrupciones reales cuya parte observable no supera el umbral.
- Los extremos completos pueden quedar fuera de las fechas solicitadas; explicar que
  las fechas seleccionan interrupciones por solapamiento y no recortan su duración.
- La cadencia fija permite usar cinco minutos por defecto; no extrapolar ese valor a
  sensores con otra cadencia sin revisar el contrato.
- La referencia de 15 minutos no prueba por sí sola exhaustividad con umbral de cinco;
  verificar además ese umbral mediante consulta independiente y datos controlados.
- Una base ampliada con CSV posteriores puede tener resultados distintos; ejecutar las
  referencias fijas en una base aislada con los scripts iniciales, sin borrar datos locales.

## Migration Plan

Añadir consulta, CLI, pruebas y documentación tras la aprobación de la propuesta.
No hay migraciones ni permisos pendientes relativos al esquema. Revertir retirando la
funcionalidad nueva, sin tocar lecturas ni los comandos existentes.

## Open Questions

No quedan decisiones abiertas. Antes de implementar se verificó de forma independiente
el conjunto fijo con umbral de cinco minutos: devolvió las mismas tres filas. Véanse
[referencias.md](referencias.md) y [validacion.md](validacion.md).
