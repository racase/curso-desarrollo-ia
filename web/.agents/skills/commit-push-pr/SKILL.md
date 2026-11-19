---
name: commit-push-pr
description: Crea un commit Conventional Commits basado en los diffs, hace push de la rama y pregunta después si se quiere abrir una PR hacia la rama destino indicada. Úsala cuando se solicite este flujo de publicación; no para una mera revisión de cambios.
---

# Commit, push y PR opcional

Ejecuta el flujo en este orden: revisar cambios, crear el commit, hacer push y preguntar por la PR. Invocar esta skill para ejecutar el flujo autoriza el commit y el push dentro del alcance solicitado. Crear o editar la propia skill no ejecuta ese flujo.

## Revisar y crear el commit

- Lee las instrucciones del repositorio. Identifica su raíz, rama actual, remoto y upstream mediante Git; no supongas que el directorio actual es la raíz ni que el remoto se llama `origin`.
- Inspecciona `git status --short`, `git diff`, `git diff --cached` y el contenido de archivos nuevos relevantes. Determina qué cambios pertenecen a la petición. Conserva los cambios ajenos, incluidos los ya preparados; si no puedes distinguir el alcance, acláralo antes de modificar el índice.
- Comprueba que no haya conflictos ni operaciones Git pendientes. Si HEAD está separado o el destino del push es ambiguo, resuelve esa información con el usuario antes de publicar. No cambies de rama ni crees una rama nueva por iniciativa propia.
- Ejecuta las comprobaciones exigidas por el repositorio y las apropiadas al cambio. En InfraTicket, los comandos PHP y Composer se ejecutan dentro del servicio `app`; consulta las instrucciones vigentes para las pruebas. Si fallan o no pueden ejecutarse, informa del motivo y detén la publicación, salvo que el usuario ya haya autorizado continuar con esa limitación.
- Prepara únicamente los archivos o fragmentos del alcance; evita `git add .` cuando pueda incluir trabajo ajeno. Revisa el diff preparado que entrará realmente en el commit. No incluyas secretos ni archivos locales de configuración.
- Redacta el mensaje a partir de ese diff, con el formato `type(scope opcional): descripción`. Usa `feat` para funcionalidad nueva, `fix` para correcciones y, según el cambio, `docs`, `refactor`, `test`, `build`, `ci`, `perf`, `style` o `chore`. `style` es formato sin cambio funcional. El scope es opcional y debe representar un área real. Mantén una descripción breve e imperativa, siguiendo el idioma del repositorio.
- Usa `!` y un pie `BREAKING CHANGE:` solo si hay una incompatibilidad real; explica su efecto. Añade un cuerpo si ayuda a entender el motivo. No deduzcas el tipo solo por los nombres de los archivos ni inventes validaciones o referencias.
- Crea un commit con el mensaje resultante y comprueba su hash y contenido. No uses `--amend`, `--no-verify` ni reescribas commits existentes sin autorización expresa. Si no hay cambios que incluir, no crees un commit vacío; informa y continúa solo si hay commits pendientes de publicar dentro del alcance autorizado.

## Hacer push

- Antes de publicar, comprueba qué commits pendientes se enviarán al remoto. Si se publicarían commits ajenos al alcance autorizado, pide aclaración.
- Usa el upstream existente. Si no existe, identifica el remoto correcto y publica la rama actual con `git push --set-upstream <remoto> <rama>`, sustituyendo los parámetros por valores comprobados. Si hay varios destinos plausibles, pregunta cuál utilizar.
- Espera al resultado del push y confirma que la punta remota coincide con el commit publicado. No uses push forzado. Ante un rechazo, error de autenticación o divergencia, informa y detente sin hacer pull, rebase, merge ni reintentos ciegos.
- Si el commit se creó pero el push falló, conserva el commit y comunica ambos resultados por separado. No pases a crear una PR hasta que la publicación esté confirmada.

## Preguntar y crear la PR

- La rama destino es un parámetro del usuario: “X” representa esa rama, no una rama literal que deba asumirse. Conserva la indicada en la petición; si falta, identifícala en la pregunta posterior al push. Puedes ofrecer la rama predeterminada remota como sugerencia, sin darla por elegida.
- Tras el push, comprueba si ya existe una PR abierta desde la rama publicada hacia el destino, usando las herramientas del proveedor disponibles o su CLI autenticada. No dupliques una PR existente; comunica su enlace. Si no puedes comprobarlo, informa de esa limitación antes de crearla.
- Prepara un título y una descripción basados en el diff completo entre la rama destino y la rama publicada, incluyendo contexto, cambios, validación real y migraciones o configuración cuando proceda. Respeta la plantilla del repositorio. El alcance de la PR puede abarcar varios commits.
- Presenta el repositorio, rama origen, rama destino, título y descripción propuestos, y pregunta: «¿Quieres que cree esta PR hacia `<rama-destino>`?». Si falta el destino, pregunta primero hacia qué rama debe ir y prepara la propuesta antes de solicitar la confirmación final. Esta confirmación posterior al push forma parte del flujo pedido.
- Espera una respuesta afirmativa explícita antes de crear la PR. El silencio no es consentimiento. Si el usuario rechaza, termina con el commit y el push completados.
- Tras la confirmación, crea la PR mediante las herramientas del proveedor disponibles o su CLI. Para cuerpos multilínea con `gh`, utiliza un archivo temporal y `--body-file`. No hagas merge ni cambies protecciones de rama.
- Ante un error de creación, comprueba si la PR llegó a crearse antes de reintentar para evitar duplicados; si el resultado sigue siendo incierto, detente e informa.

Al finalizar, comunica el hash y asunto del commit, la rama/remoto publicados, el resultado de las comprobaciones y el enlace de la PR si se creó o ya existía.
