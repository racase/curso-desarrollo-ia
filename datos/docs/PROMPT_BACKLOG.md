# Prompt para generar el backlog de InfraData

Prompt para ejecutar con Codex abierto dentro de `datos/`. Genera los issues del backlog
en `docs/issues/`, uno por fichero. Si la carpeta ya existe, bórrala o renómbrala antes
para comparar el resultado.

```text
Vas a preparar el backlog inicial de InfraData como ficheros Markdown, uno por
issue, para subirlos después a GitHub. No implementes nada: no toques código,
SQL ni tests, y no crees la carpeta openspec/.

Antes de escribir, lee AGENTS.md y README.md para conocer el estado real del
proyecto: qué existe ya (sensores y lecturas cargados, conexión, comando check)
y qué no existe todavía (ninguna analítica, informe ni API).

Contexto de los datos, que ya está en el README:
- 12 sensores en 3 sedes (Planta Valencia, Planta Sagunto, Almacén Paterna), de
  cuatro tipos: temperatura, presión, caudal y nivel.
- Una lectura cada 5 minutos por sensor, del 1 de agosto al 30 de octubre de
  2026, en UTC.
- Los datos contienen a propósito periodos sin lecturas y valores anómalos.

Crea la carpeta docs/issues/ y dentro estos cinco ficheros:

1. 01-resumen-diario-por-sensor.md
   Por cada sensor y día: valor mínimo, máximo, medio y número de lecturas.
   Filtros por sede, por sensor y por rango de fechas. Los días se cuentan en
   UTC. Se consulta desde la línea de comandos del proyecto, igual que check.

2. 02-deteccion-de-huecos.md
   Listar los periodos en que un sensor estuvo más de N minutos sin enviar
   lecturas, con su inicio, su fin y su duración. Umbral configurable y filtros
   por sede y por sensor. Debe encontrar todos los periodos sin lecturas que hay
   en el conjunto de datos. Depende del issue 1.

3. 03-deteccion-de-valores-anomalos.md
   Señalar las lecturas anómalas de cada sensor: valores imposibles para su
   tipo y valores que se quedan fijos durante demasiado tiempo. Qué cuenta como
   «anómalo» se tiene que definir en la especificación antes de programar.
   Depende del issue 1.

4. 04-informe-semanal-csv-por-sede.md
   Exportar a CSV, para una sede y una semana, el resumen de cada uno de sus
   sensores. El formato del fichero (columnas, separador, decimales) tiene que
   quedar fijado y ser verificable. Depende del issue 1.

5. 05-resumen-diario-precalculado.md
   Que el resumen del issue 1 no se recalcule sobre todas las lecturas en cada
   consulta, sino que esté precalculado y se mantenga al día cuando lleguen
   lecturas nuevas. Implica un cambio en la base de datos. Depende del issue 1.

Cada fichero sigue exactamente esta estructura:

---
title: <título corto en español, sin número>
labels: [feature, datos]
depends_on: [<números de los issues de los que depende, o vacío>]
---

## Contexto
Dos o tres frases: qué problema resuelve y para quién.

## Historia de usuario
Como <rol>, quiero <acción>, para <beneficio>. Una por rol afectado. Los roles
son personas del equipo de datos o de mantenimiento: analista de datos,
responsable de mantenimiento, responsable de sede.

## Alcance
Lista de lo que incluye, en términos de comportamiento observable.

## Fuera de alcance
Lista de lo que no incluye, para evitar que el cambio crezca.

## Criterios de aceptación
Lista numerada de comprobaciones verificables, redactadas como
«Dado… cuando… entonces…», sobre el conjunto de datos fijo del curso.

## Notas
Dudas abiertas o decisiones que habrá que tomar en la especificación.

Reglas:
- Escribe en español, con el vocabulario del proyecto.
- Describe el qué, no el cómo: nada de nombres de funciones, tablas, columnas,
  funciones de TimescaleDB ni librerías. Eso se decide después, en la
  especificación.
- No consultes la base de datos ni analices las lecturas para localizar los
  huecos o las anomalías. Los issues no deben decir dónde están ni cuántos hay:
  encontrarlos es parte del ejercicio.
- No inventes cifras. Si un criterio necesita un valor concreto del conjunto de
  datos, escribe «<valor a obtener del conjunto de datos>» en su lugar.
- Cada issue debe poder hacerse en un solo cambio. Si uno queda demasiado grande,
  dilo en «Notas» en lugar de partirlo.
- En el issue 2, deja «Criterios de aceptación» con un único punto de ejemplo y la
  nota «A completar por el equipo antes de implementar».
- En el issue 2, recoge en «Notas» cómo se mide la duración de un hueco (por
  ejemplo, desde la última lectura recibida hasta la siguiente) y qué pasa con
  los huecos al principio o al final del rango consultado.
- En el issue 5, indica en «Notas» que el AGENTS.md pide preguntar antes de
  cambiar el esquema de la base de datos.
- No inventes funcionalidad que no esté en la lista anterior.

Al terminar, crea docs/issues/README.md con una tabla (número, título,
dependencias) y el orden recomendado, y enséñame un resumen de lo creado.
```
