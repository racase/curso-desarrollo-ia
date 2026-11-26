---
title: Detección de huecos
labels: [feature, datos]
depends_on: []
---

## Contexto
Las lecturas del curso contienen periodos sin datos que todavía no se pueden consultar en InfraData. El equipo de mantenimiento necesita identificarlos para revisar la continuidad de cada sensor.

## Historia de usuario
Como responsable de mantenimiento, quiero listar los periodos sin lecturas de un sensor, para investigar las interrupciones de envío.

## Alcance
- Listado de periodos en que un sensor permanece más de N minutos sin enviar lecturas.
- Identificación del sensor, inicio, fin y duración de cada periodo, con tiempos en UTC.
- Umbral configurable y filtros combinables por sede y sensor.
- Detección de todos los periodos del conjunto fijo que superen el umbral acordado.
- Funcionalidad independiente de los demás issues.

## Fuera de alcance
- Rellenar lecturas ausentes o corregir datos.
- Explicar la causa de una interrupción o detectar valores anómalos.
- Alertas e informes.

## Criterios de aceptación
1. `gaps` sin opciones sobre el conjunto fijo devuelve exactamente tres huecos:

   | Sensor | Inicio UTC | Fin UTC | Duración (minutos) |
   | --- | --- | --- | ---: |
   | VAL-F01 | 2026-08-20T22:15:00Z | 2026-08-20T23:05:00Z | 50 |
   | SAG-P02 | 2026-09-03T08:00:00Z | 2026-09-03T14:30:00Z | 390 |
   | PAT-L01 | 2026-10-12T00:00:00Z | 2026-10-14T06:00:00Z | 3240 |

2. El inicio es la última lectura más 5 minutos; el fin es la siguiente lectura.
   No se cuenta el intervalo normal de envío. Los duplicados no generan huecos y
   los valores anómalos cuentan como lecturas.
3. El umbral es entero no negativo, configurable con `--threshold-minutes`, por defecto
   15. La comparación es estricta: un hueco de 50 minutos se excluye con umbral 50
   y se incluye con 49. Cero lista cualquier hueco positivo.
4. Los filtros exactos por sede y sensor distinguen mayúsculas y se combinan con
   fechas inclusivas UTC. Una sede y sensor incompatibles o desconocidos no devuelven filas.
5. El rango se intersecta con la ventana global `[min(time), max(time) + 5 minutos)`,
   calculada antes de los filtros. En el seed va del 1 de agosto al 31 de octubre
   exclusivo. Los extremos omitidos usan esa ventana; fuera de ella o sin lecturas
   globales no se generan huecos.
6. Se usan vecinos fuera del rango y se muestra la intersección del hueco con él.
   Duración y umbral corresponden a esa parte. PAT-L01 del 12 al 13 de octubre
   devuelve un hueco de 2880 minutos recortado al final; el día 14 devuelve 360 minutos
   recortados al inicio. No aparecen con umbrales 2880 y 360 respectivamente.
7. Los indicadores `recortado_inicio` y `recortado_fin` distinguen los cortes de la
   consulta; `abierto_inicio` y `abierto_fin` señalan falta de lectura previa o posterior
   del sensor. Los indicadores abiertos se conservan tras recortar. Un sensor existente
   sin lecturas devuelve todo el rango como un hueco abierto por ambos extremos si
   supera el umbral. La última lectura normal no genera un falso hueco final.
8. La tabla se ordena por inicio y código, con tiempos UTC ISO terminados en `Z`,
   duración con tres decimales e indicadores `si`/`no`. Una consulta vacía imprime
   `Sin huecos para los filtros indicados.` y devuelve 0. Entradas inválidas devuelven
   2 antes de conectar; errores PostgreSQL devuelven 1 sin tabla parcial.

## Notas
- Se supone cadencia fija de 5 minutos y actividad de todos los sensores durante la
  ventana común; no se infiere un calendario desde `installed_at`.
- La ventana inferida no permite reconocer ausencias de todos los sensores anteriores
  o posteriores al historial global; los límites abiertos no demuestran fechas de avería.
- Detección, recorte y duración se calculan en SQL. No hay cambios de esquema, seed
  ni dependencias nuevas. Diseño y escenarios: `openspec/changes/archive/2026-11-26-deteccion-de-huecos/`.
