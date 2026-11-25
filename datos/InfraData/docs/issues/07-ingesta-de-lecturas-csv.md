---
title: Ingesta de lecturas CSV
labels: [feature, datos]
depends_on: []
---

## Contexto
InfraData ya dispone de sensores y lecturas cargados, pero necesita incorporar las nuevas lecturas exportadas del sistema de adquisición. El equipo de datos necesita validar y normalizar esos ficheros antes de cargarlos, evitando duplicados y pudiendo revisar los rechazos.

## Historia de usuario
Como analista de datos, quiero cargar las lecturas de un CSV tras validarlas y normalizarlas, para incorporarlas a la base de datos sin duplicados y con las fechas en UTC.

Como responsable de mantenimiento, quiero consultar un informe de las filas rechazadas y sus motivos, para revisar los problemas de los datos exportados.

## Alcance
- Recibir un CSV exportado del sistema de adquisición, con una fila por lectura que contiene el código del sensor, la fecha y hora local en `Europe/Madrid` y el valor.
- Validar y normalizar las filas antes de cargarlas en la base de datos, convirtiendo los instantes a UTC.
- Rechazar sensores desconocidos, valores vacíos o no numéricos y filas duplicadas, incluidas lecturas ya cargadas.
- Generar un informe que permita identificar cada fila rechazada y el motivo del rechazo.
- Evitar que cargar dos veces el mismo fichero duplique las lecturas.
- Resolver la ingesta sin depender de otros issues.

## Fuera de alcance
- Dar de alta sensores desconocidos o corregir automáticamente los datos rechazados.
- Detectar huecos o anomalías en valores numéricos válidos.
- Incorporar otros formatos de entrada o automatizar la recepción de ficheros.

## Criterios de aceptación
1. Dado un CSV válido del formador con sensores del conjunto del curso, cuando se carga, entonces las lecturas se incorporan con el sensor y valor originales y con el instante UTC equivalente a su fecha y hora en `Europe/Madrid`.
2. Dado un CSV con sensores desconocidos, valores vacíos y valores no numéricos, cuando se valida, entonces esas filas no se incorporan y el informe identifica cada una con su motivo de rechazo.
3. Dado un CSV con filas duplicadas, cuando se procesa, entonces ninguna lectura se incorpora más de una vez y las filas rechazadas por duplicidad quedan identificadas en el informe.
4. Dado un fichero ya cargado, cuando se vuelve a cargar sin cambios, entonces no se añaden lecturas duplicadas y las filas rechazadas por estar ya cargadas aparecen en el informe.
5. Dado un fichero que mezcla filas válidas y erróneas, cuando se procesa, entonces las lecturas incorporadas y los rechazos coinciden con la política de carga parcial o rechazo completo fijada en la especificación, sin incorporar ninguna fila inválida.

## Notas
- El formato del fichero lo aporta el formador; fijar en la especificación su interpretación antes de implementar.
- Evitar duplicados puede implicar un cambio de esquema. El `AGENTS.md` pide preguntar antes de cambiar el esquema de la base de datos; solicitar aprobación y esperar la respuesta antes de modificarlo.
- Decidir si un fichero con errores se carga en parte o no se carga, y cómo refleja el informe las filas válidas que no se incorporen por esa decisión.
- Definir qué identifica una lectura duplicada y qué ocurre si coinciden sensor e instante, pero difiere el valor.
- Definir cómo tratar fechas inválidas y horas locales ambiguas o inexistentes por los cambios de hora de `Europe/Madrid`, sin asumir un desplazamiento fijo respecto a UTC.
