# Backlog inicial de InfraData

Estos ficheros preparan los issues para su posterior subida a GitHub; no representan funcionalidad implementada. El punto de partida son los sensores y lecturas cargados, la conexión y el comando `check`; todavía no hay analítica, informes ni API.

| Número | Título | Dependencias |
| --- | --- | --- |
| 1 | [Resumen diario por sensor](01-resumen-diario-por-sensor.md) | Ninguna |
| 2 | [Detección de huecos](02-deteccion-de-huecos.md) | Ninguna |
| 3 | [Detección de valores anómalos](03-deteccion-de-valores-anomalos.md) | Ninguna |
| 4 | [Informe semanal CSV por sede](04-informe-semanal-csv-por-sede.md) | 1 |
| 5 | [Resumen diario precalculado](05-resumen-diario-precalculado.md) | 1 |
| 6 | [Del notebook al paquete](06-del-notebook-al-paquete.md) | Ninguna |
| 7 | [Ingesta de lecturas CSV](07-ingesta-de-lecturas-csv.md) | Ninguna |

## Orden recomendado

Los issues **1 y 2 son los de la sesión de datos**: empezar por el 1 y continuar con el 2, completando sus criterios de aceptación antes de implementarlo. Son independientes y pueden abordarse por separado.

Los issues **3 al 7 son de reserva**. El orden sugerido completo es **1 → 2 → 3 → 4 → 5 → 6 → 7**. Los únicos requisitos de orden son completar el 1 antes del 4 y del 5; el 3, el 6 y el 7 no dependen de otros issues.

Los números son referencias internas de este backlog; al subir los issues a GitHub, vincular las dependencias con los números que se asignen allí. Cada fichero plantea un único cambio y deja las decisiones pendientes para la especificación.
