---
name: commit-push-pr
description: Create Conventional Commits from the actual Git diffs, push the requested changes, then ask whether to open a pull request against a chosen target branch. Use when the user asks for commit and push, or invokes this workflow. Creating or editing this skill does not invoke it.
---

# Commit, push y propuesta de PR

Ejecuta en este orden: revisar y commitear los cambios autorizados, confirmar su push y preguntar por una PR hacia la rama de destino. Redacta la conversación en español; respeta el idioma y las convenciones de commits del repositorio (por defecto, inglés).

## 1. Determinar alcance y ramas

- Lee los AGENTS.md aplicables y respeta los límites del proyecto. No abras archivos de entorno ni secretos.
- Identifica repositorio, rama actual, remotos y upstream. Consulta el estado completo, el diff sin stage, el diff staged y los archivos nuevos relevantes. Leer solo un resumen o los nombres de archivo no basta para redactar el commit.
- Usa las rutas o el alcance indicado por el usuario. Si pide el resto de cambios, incluye el trabajo pendiente de ese proyecto. Conserva cambios ajenos y no vacíes el índice; si hay cambios staged fuera del alcance, aclara cómo tratarlos antes de commitear.
- Si HEAD está separado, hay conflictos sin resolver o el remoto resulta ambiguo, pide la información necesaria. No crees ni cambies ramas por iniciativa propia.
- Conserva como destino de PR la rama indicada por el usuario. Si dijo literalmente «X» como marcador, no asumas que existe una rama con ese nombre: busca la rama por defecto del remoto como sugerencia, sin tomar la rama actual como destino.

## 2. Redactar el Conventional Commit desde el diff

Selecciona explícitamente los archivos autorizados y revisa el diff staged completo, incluidos archivos nuevos. Confirma que el conjunto staged corresponde al alcance antes de escribir el commit.

Usa este formato:

```text
type(scope opcional): descripción breve en imperativo
```

Elige el tipo por el efecto real del diff: `feat` para una capacidad nueva, `fix` para corregir comportamiento, `refactor` para reorganización sin cambio observable, `docs` para documentación, `test` para pruebas y `build`, `ci`, `perf`, `style` o `chore` cuando corresponda. `style` significa formato del código, no una funcionalidad visual. No deduzcas el tipo únicamente del nombre de la rama, la incidencia o la última conversación.

El scope es opcional y debe representar el módulo afectado. Usa `!` y explica `BREAKING CHANGE:` en el cuerpo solo si el diff rompe compatibilidad. Para cambios independientes, crea commits pequeños y coherentes sin mezclar trabajo ajeno; deriva cada mensaje de su propio diff staged.

Antes de commitear, ejecuta `git diff --cached --check` y las comprobaciones pertinentes exigidas por el proyecto. Reutiliza resultados recientes si el código no ha cambiado desde que se ejecutaron; no repitas una suite solo por preparar un commit. No desactives hooks ni comprobaciones para conseguir un commit. Si el mensaje explícito del usuario contradice Conventional Commits, señala el conflicto antes de alterarlo.

## 3. Crear el commit

- Invocar este flujo autoriza commit y push dentro del alcance solicitado; no pidas otra confirmación rutinaria para esos pasos.
- Ejecuta el commit con el mensaje derivado del diff. Usa argumentos estructurados o un archivo temporal para cuerpos multilínea, preservando saltos de línea y evitando interpolación del shell.
- Comprueba el resultado antes de seguir. Si falla, detente y explica la causa; no hagas push como si hubiese funcionado.
- Si no hay cambios, no crees un commit vacío. Si ya existen commits locales autorizados pendientes de publicar, puedes subirlos; si tampoco los hay, informa de que no hay nada nuevo y no abras una PR automáticamente.

## 4. Hacer push y confirmarlo

- Usa el remoto y la rama de seguimiento correctos. Si no existe upstream y el remoto es inequívoco, publica la rama actual con `--set-upstream`. No subas otras ramas ni tags.
- Revisa qué commits quedarán publicados: si aparecen commits previos fuera del alcance autorizado, acláralo antes de subirlos.
- Nunca uses force push, amend, reset, rebase ni cambies la configuración global de Git para resolver un rechazo dentro de este flujo.
- Espera a que termine el comando: un identificador de sesión o proceso pendiente no acredita éxito. Confirma su código de salida y la referencia remota publicada. Ante rechazo, error de autenticación o fallo de red, informa del commit local conservado y detén el flujo; no preguntes todavía por la PR ni reintentes indefinidamente.

## 5. Preparar la PR y preguntar después del push

Solo tras confirmar el push, determina la rama de destino real: usa la indicada por el usuario o propone la rama por defecto del remoto. Si no puedes determinarla, pregunta qué rama desea; no inventes `main`, `master` ni una rama llamada `X`.

Para una rama conocida, revisa el diff de la futura PR contra su base y prepara un título y una descripción breves con cambios, incidencias conocidas y validaciones realizadas. No inventes pruebas ni cierres incidencias automáticamente. Comprueba también si ya existe una PR abierta de esa rama a ese destino; si existe, enlázala en vez de crear un duplicado.

Después presenta el commit, la rama publicada y el resumen preparado, y pregunta una vez:

> ¿Quieres crear una PR desde `<rama-actual>` hacia `<rama-destino>`? Puedes indicar otra rama.

Esta pregunta es un paso solicitado del flujo: el commit y push no autorizan por sí solos a abrir una PR. Usa la herramienta de preguntas disponible o deja la pregunta al final de la respuesta. Espera una respuesta explícita; el silencio no es aprobación. Si el usuario ya ha aprobado expresamente esa misma PR y destino, respeta esa autorización sin pedirla de nuevo. Si no quiere PR, termina con el commit y push completados.

Si cambia el destino, revisa de nuevo el diff y actualiza título y descripción antes de crear la PR. Una respuesta que solo nombra una rama resuelve el destino, pero no sustituye una aprobación ausente.

## 6. Crear la PR solo cuando esté autorizada

Utiliza preferentemente el conector del alojamiento del repositorio. Si no dispone de la operación necesaria, usa su CLI autenticada; con GitHub CLI pasa el cuerpo mediante `--body-file`. Si no hay acceso, informa y proporciona el borrador preparado, sin afirmar que se creó.

Establece base y head explícitamente, respeta la plantilla del repositorio y verifica la URL devuelta. No hagas merge, no borres ramas ni envíes comentarios adicionales. Termina con el hash del commit, la rama subida y la URL de la PR, o indica que el usuario optó por no crearla.
