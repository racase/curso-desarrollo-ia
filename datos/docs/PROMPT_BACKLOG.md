# Prompt para generar el backlog de InfraData

Prompt para ejecutar con Codex abierto dentro de `datos/`. Genera los issues del backlog
en `docs/issues/`, uno por fichero. Si la carpeta ya existe, bórrala o renómbrala antes
para comparar el resultado.

```text
Vas a preparar el backlog inicial de InfraData como ficheros Markdown, uno por
issue, para subirlos después a GitHub. No implementes nada: no toques código,
SQL, notebooks ni tests, y no crees la carpeta openspec/.

Antes de escribir, lee AGENTS.md y README.md para conocer el estado real del
proyecto: qué existe ya (sensores y lecturas cargados, conexión, comando check)
y qué no existe todavía (ninguna analítica, informe, notebook ni API).

Contexto de los datos, que ya está en el README:
- 12 sensores en 3 sedes (Planta Valencia, Planta Sagunto, Almacén Paterna), de
  cuatro tipos: temperatura, presión, caudal y nivel.
- Una lectura cada 5 minutos por sensor, del 1 de agosto al 30 de octubre de
  2026, en UTC.
- Los datos contienen a propósito periodos sin lecturas y valores anómalos.

Crea la carpeta docs/issues/ y dentro estos ocho ficheros. Conserva la
numeración de los issues 1 a 6 del backlog original:

1. 01-resumen-diario-por-sensor.md
   Por cada sensor y día: valor mínimo, máximo, medio y número de lecturas, con
   la unidad del sensor. Filtros por sede, por sensor y por rango de fechas. Los
   días se cuentan en UTC. Se consulta desde la línea de comandos del proyecto,
   igual que check.

2. 02-deteccion-de-huecos.md
   Listar los periodos en que un sensor estuvo más de N minutos sin enviar
   lecturas, con su inicio, su fin y su duración. Umbral configurable y filtros
   por sede y por sensor. Debe encontrar todos los periodos sin lecturas que hay
   en el conjunto de datos. No depende del cuaderno.

3. 03-deteccion-de-valores-anomalos.md
   Señalar las lecturas anómalas de cada sensor, cubriendo tres tipos: valores
   fuera del rango posible para su tipo de sensor, valores que se quedan fijos
   durante demasiado tiempo y saltos bruscos respecto a las lecturas vecinas.
   Los límites y umbrales de cada tipo se tienen que definir en la especificación
   antes de programar. No depende del cuaderno.

4. 04-informe-semanal-csv-por-sede.md
   Exportar a CSV, para una sede y una semana, el resumen de cada uno de sus
   sensores. El formato del fichero (columnas, separador, decimales, codificación)
   tiene que quedar fijado y ser verificable, y el fichero debe abrirse bien en
   Excel en español. Depende del issue 1.

5. 05-resumen-diario-precalculado.md
   Que el resumen del issue 1 no se recalcule sobre todas las lecturas en cada
   consulta, sino que esté precalculado y se mantenga al día cuando lleguen
   lecturas nuevas, devolviendo exactamente los mismos resultados. Implica un
   cambio en la base de datos. Depende del issue 1.

6. 06-del-notebook-al-paquete.md
   El equipo de datos tiene un notebook propio, notebooks/tendencia-temperatura.ipynb,
   que calcula la media móvil y la tendencia semanal de la temperatura por sede
   cargando todas las lecturas en memoria. Hay que llevar ese cálculo al paquete
   como funcionalidad reutilizable y con tests, haciendo la agregación en la base
   de datos, y dejar el notebook usando esa funcionalidad en lugar de su propio
   código. El resultado debe coincidir con el del notebook original. No depende
   de otros issues.

7. 07-parte-de-incidencias.md
   Ejercicio principal de integración del curso. Para una sede o un sensor y un
   rango UTC, generar desde la línea de comandos un parte en español que reúna
   los resúmenes diarios, los periodos sin lecturas y los valores anómalos ya
   definidos. Cada hallazgo debe incluir sensor, momento o intervalo, motivo y
   datos concretos para comprobarlo. El parte se puede guardar como Markdown y
   debe indicar cuando no hay hallazgos. La generación es determinista: la IA
   ayuda a desarrollar y probar el programa, pero no hace falta usar un modelo
   de IA al ejecutar el parte. Depende de los issues 1, 2 y 3.

8. 08-cuaderno-de-exploracion-visual.md
   Ampliación opcional, fuera del recorrido principal. Un cuaderno de exploración
   que se abre en VS Code y se ejecuta celda a celda, con los gráficos dentro
   del editor. Muestra un mapa de calor de completitud (un sensor por fila, un
   día por columna y, en cada celda, el porcentaje de lecturas recibidas) y la
   serie temporal de cualquier sensor en un gráfico interactivo con zoom. Se
   guarda como fichero de Python con celdas, no como .ipynb, para revisarlo en
   Git y editarlo con el agente. Usa el resumen del issue 1. Depende solo del 1;
   ningún otro ejercicio depende de este cuaderno.

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
  especificación. La única excepción es el formato del cuaderno del issue 8.
- No consultes la base de datos ni analices las lecturas para localizar los
  huecos o las anomalías. Los issues no deben decir dónde están ni cuántos hay:
  encontrarlos es parte del ejercicio.
- No inventes cifras. Si un criterio necesita un valor concreto del conjunto de
  datos, escribe «<valor a obtener del conjunto de datos>» en su lugar.
- Cada issue debe poder hacerse en un solo cambio. Si uno queda demasiado grande,
  dilo en «Notas» en lugar de partirlo.
- En el issue 1, recoge en «Notas» que cada sensor debería tener 288 lecturas al
  día y que los días incompletos afectan a la media.
- En el issue 8, los criterios de aceptación tienen que poder comprobarse
  mirando el cuaderno: que se abre y se ejecuta entero en VS Code sin errores,
  que el mapa de calor tiene una fila por sensor y una columna por día, y que la
  serie temporal permite elegir el sensor y hacer zoom. Recoge en «Notas» que el
  AGENTS.md pide justificar cualquier dependencia nueva, que conviene que las del
  cuaderno vayan en un grupo aparte para no cargar al resto, y que hacen falta
  las extensiones de Python y Jupyter de VS Code.
- En los issues 2 y 3, deja «Criterios de aceptación» con un único punto de
  ejemplo y la nota «A completar por el equipo antes de implementar».
- En el issue 2, recoge en «Notas» cómo se mide la duración de un hueco (por
  ejemplo, desde la última lectura recibida hasta la siguiente) y qué pasa con
  los huecos al principio o al final del rango consultado.
- En el issue 3, recoge en «Notas» que un mismo valor puede ser normal en un
  sensor y anómalo en otro (por ejemplo, una presión de 0 bar con la instalación
  parada), y que la especificación debe decidir cómo se distinguen.
- En los issues 2 y 3, pide comparar los resultados con lecturas concretas
  para detectar lo que el código no encuentra y lo que marca de más.
- En el issue 4, recoge en «Notas» que Excel en español espera punto y coma como
  separador y coma decimal.
- En el issue 5, indica en «Notas» que el AGENTS.md pide preguntar antes de
  cambiar el esquema de la base de datos, y que hay que decidir con qué retraso
  se admite que el resumen refleje las lecturas nuevas.
- En el issue 6, indica en «Notas» que el notebook lo aporta el formador y que
  el AGENTS.md pide justificar cualquier dependencia nueva (Jupyter, pandas).
- En el issue 7, los criterios deben comprobar filtros, coincidencia de los
  hallazgos con los issues 1 a 3, ausencia de incidencias inventadas y lectura
  del Markdown sin ejecutar el proyecto. Deja para la especificación el formato
  exacto, la precisión y el tratamiento de huecos que cruzan el rango.
- No inventes funcionalidad que no esté en la lista anterior.

Al terminar, crea docs/issues/README.md con una tabla (número, título,
dependencias y uso en el curso) y el orden recomendado. Indica que los issues
1, 2 y 3 preparan la base, que el 7 es el ejercicio principal de integración,
que el 8 es opcional y que 4, 5 y 6 son extensiones. Después enséñame un
resumen de lo creado.
```
