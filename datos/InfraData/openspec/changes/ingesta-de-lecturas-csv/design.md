## Context

El issue 07 incorpora CSV del sistema de adquisición sin depender de otros issues.
El ejemplo del formador está en `data/incoming/lecturas_2026-10-31_2026-11-06.csv`.
`readings` es una hypertable con `time`, `sensor_id` y `value` (`double precision`);
su índice actual por sensor y tiempo no impone unicidad. Python 3.11 y psycopg
ya proporcionan lo necesario para la ingesta.

Las cinco decisiones de «Notas» se consultaron y aceptaron antes de generar artefactos:
formato del ejemplo, carga parcial, clave sensor/UTC con primera fila válida,
rechazo de fechas problemáticas y propuesta de restricción única.

## Goals / Non-Goals

**Goals:** incorporar filas válidas, preservar sus valores, normalizar a UTC,
impedir duplicados y sobrescrituras y explicar cada rechazo de forma reproducible.

**Non-Goals:** crear sensores, corregir entradas, detectar anomalías o huecos,
automatizar recepción, admitir otros formatos o implementar ahora la funcionalidad.

## Decisions

### 1. Formato y validación

Usar `csv` de la biblioteca estándar con UTF-8, `;`, comillas dobles estándar y cabecera
exacta `codigo_sensor;fecha_hora_local;valor`. Admitir finales LF y CRLF. Cada registro
debe tener tres campos; quitar espacios exteriores de sus valores para validarlos,
conservando los originales en el informe. El código distingue mayúsculas y minúsculas.
Las fechas tienen exactamente `DD/MM/AAAA HH:MM`, sin segundos ni desplazamiento.
El valor admite signo, dígitos y parte decimal opcional separada por coma, sin miles,
punto decimal, notación científica ni valores no finitos. Convertir al tipo numérico
de almacenamiento y rechazar resultados no finitos; no aplicar umbrales de anomalías.

Rechazar individualmente campos ausentes o adicionales, sensores desconocidos, fechas
inválidas y valores vacíos o no numéricos. Si hay varios fallos, informar un motivo
principal según este orden: número de campos, sensor, fecha, valor, duplicidad/conflicto.
Una fila inválida no reserva una clave para duplicidad.

UTF-8 inválido, cabecera incorrecta o sintaxis CSV que impida separar los registros son
errores de fichero: abortar sin incorporar lecturas. Un fichero solo con cabecera
es válido y tiene cero filas. Una línea vacía se informa como registro sin campos.
La alternativa de inferir formatos o usar pandas añade ambigüedad o dependencias
innecesarias para un contrato fijo.

### 2. Conversión local a UTC

Usar `zoneinfo.ZoneInfo("Europe/Madrid")`, sin desplazamiento fijo. Para cada hora
local, probar `fold=0` y `fold=1`, convertir a UTC y volver a la zona de origen.
Eliminar candidatos que no recuperen la hora local original y deduplicar los instantes
UTC restantes: cero candidatos significa hora inexistente, dos significa ambigua,
uno significa hora válida. No basta con asignar `tzinfo`, pues no valida saltos horarios.
Una fecha que no pueda convertirse dentro del rango representable se rechaza.
Si el entorno no tiene datos IANA, dar error operativo antes de insertar; documentar
el requisito del sistema sin añadir por defecto una dependencia de Python.

Alternativa descartada: elegir automáticamente una repetición de otoño o desplazar
una hora inexistente. Cambiaría el instante sin evidencia en el CSV.

### 3. Identidad, carga parcial y concurrencia

Clave: `(sensor_id, instante UTC)`. La primera fila que supera validación de campos
reserva la clave dentro del fichero. Las posteriores se comparan con esa primera fila:
mismo valor numérico almacenado es `duplicado_en_csv`; distinto es `conflicto_en_csv`.
Si la primera encuentra una lectura existente, se rechaza como `ya_cargada` o
`conflicto_con_existente`, según igualdad del valor. Nunca actualizar lecturas existentes.
La igualdad usa el valor convertido a `double precision`, sin tolerancias; `23,1` y
`23,10` representan el mismo valor. No comparar el texto decimal.

Separar la lógica reutilizable en un módulo de ingesta con resultados tipados.
Leer el CSV de forma incremental; mantener solo las claves necesarias para la
deduplicación y escribir los rechazos progresivamente a un temporal. No cargar el
histórico de lecturas en Python. Usar `connect()` como gestor de contexto y valores
SQL parametrizados. Insertar con `ON CONFLICT (sensor_id, time) DO NOTHING RETURNING`;
si no se inserta, consultar el valor existente para clasificar el rechazo. Bajo
READ COMMITTED, esa consulta posterior observa la inserción concurrente confirmada.
La restricción única es necesaria: consultar antes de insertar por sí solo deja carreras.

La carga parcial se refiere a errores de datos: confirmar juntas todas las filas aceptadas
en una transacción. Un fallo operativo antes de confirmar revierte la ingesta completa;
no convertir errores de conexión o SQL en rechazos de datos. No crear el esquema
automáticamente desde el comando; si falta la restricción, fallar con instrucción de migración.

### 4. CLI e informe

Interfaz: `python -m infradata ingestar-csv RUTA --informe RUTA_INFORME`.
El informe es obligatorio, debe tener destino distinto del origen y no sobrescribir
archivos existentes. Verificar rutas resueltas y equivalencia de archivos para impedir
que enlaces permitan sobrescribir el origen; la publicación también debe impedir colisiones.
Usar UTF-8, separador `;` y cabecera:
`fila;codigo_sensor;fecha_hora_local;valor;registro_original;motivo`.
`fila` es el número de línea física donde comienza el registro, contando la cabecera
como línea 1. `registro_original` conserva el texto completo del registro sin su
terminador final, escapado como campo CSV, incluso cuando sobran o faltan campos.
Los tres campos originales van vacíos cuando no existen. Ordenar rechazos como el origen.
Motivos estables y documentados en castellano:
`numero_de_campos_invalido`, `sensor_desconocido`, `fecha_invalida`, `hora_ambigua`,
`hora_inexistente`, `valor_vacio`, `valor_no_numerico`, `valor_no_finito`,
`duplicado_en_csv`, `conflicto_en_csv`, `ya_cargada`, `conflicto_con_existente`.
Un informe sin rechazos contiene solo la cabecera.

Al completar, mostrar `Filas: N | Incorporadas: I | Rechazadas: R` y ruta del informe;
se cumple `N = I + R`. Código 0 si se completó, también con rechazos o cero filas;
código 2 para argumentos, rutas o formato de fichero incorrectos; código 1 para
fallos operativos de base de datos, sistema de archivos o datos IANA ausentes.
Mensajes en castellano, errores por stderr y sin traceback ni credenciales.

Preparar y cerrar el temporal del informe antes del commit; publicarlo después de
confirmar. Ante error previo al commit, revertir y no publicar un informe de éxito.
El sistema de archivos y PostgreSQL no comparten transacción: si falla la publicación
tras confirmar, dar código 1, indicar que la carga sí se confirmó y conservar e identificar
el temporal para recuperar el informe. No afirmar que hubo rollback tras un commit.
Un fallo de conexión durante commit puede dejar resultado desconocido: explicitarlo
y permitir verificar o reintentar gracias a la unicidad, sin afirmar cero inserciones.

### 5. Esquema y compatibilidad

Proponer `UNIQUE (sensor_id, time)` con nombre `readings_sensor_time_unique`,
incluyendo la dimensión temporal de la hypertable. Mantener el índice actual y
las consultas existentes. Actualizar la definición inicial y preparar un script
explícito en `db/migrations/` para volúmenes ya creados: los scripts de `db/init/`
no se ejecutan de nuevo sobre ellos.

La migración verificará duplicados mediante `GROUP BY sensor_id, time HAVING count(*) > 1`
en SQL y abortará si existen, tanto con igual como distinto valor. No borrar ni fusionar
filas automáticamente. Proteger la comprobación y creación con transacción y bloqueo
adecuado para evitar escrituras entre ambas; documentar el bloqueo de escrituras.
La inclusión del cambio de esquema en la propuesta está aprobada. La ejecución real
de la migración requiere aprobación previa del usuario, según la consulta realizada.

## Risks / Trade-offs

- La memoria para claves crece con el fichero; la lectura incremental evita mantener
  todas las filas y el histórico. Procesar volúmenes masivos queda fuera de este issue.
- La tabla actual puede contener duplicados: abortar la migración y pedir una decisión
  específica de saneamiento, conservando datos y anomalías intencionados.
- Una transacción larga y la creación de la restricción pueden bloquear escrituras:
  documentar la aplicación en una ventana adecuada para el conjunto didáctico.
- La publicación del informe puede fallar después del commit: conservar el temporal
  e indicar expresamente el estado confirmado o desconocido de la transacción.
- El CSV local no distingue las dos repeticiones de otoño: rechazarlas es una pérdida
  de datos deliberada y revisable, acordada con el usuario.

## Migration Plan

1. Implementar y probar con una base aislada, incluidos duplicados previos y concurrencia.
2. Preparar scripts de esquema inicial y migración, sin ejecutar cambios sobre la base del usuario.
3. Obtener aprobación antes de aplicar la migración y comprobar duplicados sin eliminarlos.
4. Aplicar la restricción y habilitar la ingesta; conservar las verificaciones de hypertables.
5. Si se revierte el código, conservar las lecturas importadas y la restricción. Retirar
   la restricción sería otro cambio de esquema sujeto a aprobación; no borrar datos.

## Open Questions

No quedan decisiones funcionales abiertas de «Notas». La aplicación de la migración
es un paso futuro sujeto a aprobación; cualquier duplicado preexistente necesitaría
una decisión adicional antes de sanear datos.
