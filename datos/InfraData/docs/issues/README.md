# Backlog inicial de InfraData

Estos ficheros son borradores para subir después a GitHub. Los números identifican los issues de este backlog; las dependencias deberán enlazarse con los números reales cuando se publiquen.

El proyecto ya dispone de sensores y lecturas cargados, conexión y el comando `check`. Todavía no dispone de analítica en el paquete, informes ni API; el notebook de exploración es el punto de partida del issue 6.

| Número | Título | Dependencias |
| --- | --- | --- |
| [1](01-resumen-diario-por-sensor.md) | Resumen diario por sensor | Ninguna |
| [2](02-deteccion-de-huecos.md) | Detección de huecos | Ninguna |
| [3](03-deteccion-de-valores-anomalos.md) | Detección de valores anómalos | Ninguna |
| [4](04-informe-semanal-csv-por-sede.md) | Informe semanal CSV por sede | 1 |
| [5](05-resumen-diario-precalculado.md) | Resumen diario precalculado | 1 |
| [6](06-del-notebook-al-paquete.md) | Del notebook al paquete | Ninguna |

## Orden recomendado

1. Abordar el issue 1 y después el 2 en la sesión de datos. Son independientes; antes de implementar el 2, el equipo debe completar sus criterios de aceptación.
2. Mantener los issues 3 a 6 como reserva. Se propone abordarlos en orden 3, 4, 5 y 6; el 4 y el 5 requieren completar el 1, mientras que el 3 y el 6 pueden adelantarse de forma independiente.

Cada issue se plantea para un único cambio. Las decisiones pendientes se recogen en sus notas y deben resolverse en la especificación antes de implementar.
