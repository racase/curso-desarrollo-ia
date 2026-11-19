---
name: commit-push-pr
description: Crea un commit siguiendo Conventional Commits a partir de los diffs reales, hace push de la rama al remoto y después pregunta si se quiere abrir una Pull Request hacia una rama destino (main, develop u otra). Úsala siempre que el usuario pida "commitear", "hacer commit y push", "subir los cambios", "guardar y subir", "sube esto al repo", "commit + PR", "abre una PR", o cualquier combinación de commit/push/PR, aunque no mencione Conventional Commits ni nombre la skill.
argument-hint: "[rama-destino-PR]"
---

# Commit → Push → PR

Flujo en tres pasos: un commit con mensaje Conventional Commits deducido de los cambios, un push de la rama actual y, al final, la pregunta de si abrir una PR. El argumento opcional (`$ARGUMENTS`) es la rama destino de la PR; si no viene, se pregunta.

Trabaja siempre desde la raíz del repositorio git (`git rev-parse --show-toplevel`), porque el proyecto vive en `web/` pero el repo empieza un nivel por encima y los paths del diff son relativos a la raíz.

## 1. Comprobaciones previas

Ejecuta en paralelo:

```bash
git status --short --branch
git diff --staged --stat
git diff --stat
git log --oneline -10
git remote -v
```

Detente y avisa al usuario si:

- **No hay cambios** (ni staged, ni unstaged, ni untracked): no hay nada que commitear. Si la rama tiene commits sin subir, ofrece saltar directamente al push (paso 3).
- **Estás en `main`/`master`** (o la rama por defecto del remoto): commitear directamente ahí suele ser un error. Propón crear una rama (`feat/...`, `fix/...`, según el tipo de cambio) y continúa en ella si el usuario acepta.
- **HEAD desacoplado, merge o rebase en curso**: el usuario tiene que resolverlo antes.

## 2. Commit con Conventional Commits

### Qué se incluye

- Si **ya hay cambios en staging**, commitea solo eso: el usuario ha elegido deliberadamente qué incluir. Menciona los cambios unstaged que quedan fuera.
- Si **no hay nada en staging**, añade los ficheros modificados y nuevos por nombre (`git add <rutas>`), no con `git add -A` a ciegas.
- **Nunca** incluyas ficheros que probablemente contengan secretos (`.env`, `*.pem`, `credentials*.json`, `.claude/settings.local.json`, ficheros con tokens o contraseñas) ni artefactos que deberían estar ignorados (`vendor/`, `node_modules/`, `.phpunit.result.cache`, builds). Si aparecen sin ignorar, déjalos fuera y avisa.

### Analizar el diff

Lee el diff real (`git diff --staged` tras hacer el add) y decide el mensaje a partir de lo que cambia en el código, no de los nombres de fichero. Si el diff es muy grande, apóyate en `--stat` y lee en detalle las partes con lógica.

Si los cambios mezclan cosas claramente independientes (p. ej. una feature y un arreglo de docs sin relación), propón dividirlos en varios commits y pregunta. Si están relacionados, un único commit está bien.

### Formato del mensaje

```
<tipo>(<ámbito opcional>): <descripción>

<cuerpo opcional: qué y por qué, no cómo>

<footer opcional: BREAKING CHANGE: ..., Refs: #123>
```

Tipos:

| Tipo | Cuándo |
|------|--------|
| `feat` | Funcionalidad nueva visible para el usuario |
| `fix` | Corrección de un bug |
| `docs` | Solo documentación |
| `style` | Formato sin cambio de lógica (Pint, espacios) |
| `refactor` | Reestructura sin cambiar comportamiento |
| `perf` | Mejora de rendimiento |
| `test` | Añadir o corregir tests |
| `build` | Dependencias, Docker, Composer, npm, Vite |
| `ci` | Pipelines de CI |
| `chore` | Mantenimiento que no encaja en lo anterior |
| `revert` | Revertir un commit previo |

Si el commit mezcla tipos, elige el de mayor impacto (feat > fix > refactor > ...): una feature con sus tests es `feat`, no `test`.

Reglas:

- **Ámbito**: el módulo o área afectada, en minúsculas y corto (`incidents`, `users`, `auth`, `filament`, `docker`, `openspec`). Omítelo si el cambio es transversal.
- **Descripción**: imperativo, sin mayúscula inicial ni punto final, ≤ 72 caracteres en la primera línea. Debe decir qué cambia, no "update files".
- **Idioma**: sigue el idioma predominante en `git log` reciente del repo; si hay mezcla, usa el del último commit del usuario.
- **Cuerpo**: añádelo cuando el cambio no sea trivial; explica el motivo y los efectos relevantes (migraciones, cambios de configuración, comandos nuevos).
- **Breaking changes**: `!` tras el tipo/ámbito (`feat(api)!: ...`) y un footer `BREAKING CHANGE: ...`.
- Añade los trailers de atribución que el entorno indique (p. ej. `Co-Authored-By`), si los hay.

Ejemplos:

```
feat(incidents): permitir adjuntar comentarios a una incidencia
fix(auth): redirigir al panel tras login con rol técnico
build(docker): añadir extensión pdo_pgsql a la imagen de PHP
docs: documentar arranque con Podman en el README
```

### Crear el commit

Usa un heredoc para que el formato multilínea se conserve:

```bash
git commit -F - <<'EOF'
feat(incidents): permitir adjuntar comentarios a una incidencia

Añade el modelo Comment, su relación con Incident y el formulario
en el recurso de Filament.
EOF
```

Si un hook pre-commit falla (Pint, tests, lint), **no** uses `--no-verify`: corrige el problema, vuelve a añadir los ficheros y crea el commit de nuevo (no `--amend`, porque el commit fallido no llegó a existir). Comprueba con `git status` que el commit quedó creado.

## 3. Push

```bash
git rev-parse --abbrev-ref --symbolic-full-name @{u}   # ¿tiene upstream?
```

- Sin upstream: `git push -u origin <rama-actual>`.
- Con upstream: `git push`.
- Si el push es **rechazado** (non-fast-forward), **no** fuerces. Explica que el remoto tiene commits nuevos y propón `git pull --rebase` y repetir el push; ejecútalo solo si el usuario lo aprueba. Si aparecen conflictos, para y muéstralos.
- Si falla por autenticación, indica al usuario cómo autenticarse (p. ej. `! gh auth login`) en lugar de reintentar.

## 4. Preguntar por la Pull Request

Tras un push correcto, pregunta siempre si se quiere abrir una PR; no la crees sin confirmación, porque es visible para otras personas.

1. Comprueba si ya existe una PR para la rama: `gh pr view --json url,baseRefName,state 2>/dev/null`. Si existe y está abierta, informa de su URL (el push ya la ha actualizado) y termina.
2. Determina la rama destino candidata:
   - Si `$ARGUMENTS` trae una rama, esa es la candidata.
   - Si no, lista las ramas remotas (`git branch -r`) y ofrece las habituales (`main`, `develop`) que existan.
3. Pregunta con `AskUserQuestion`, por ejemplo: "¿Quieres abrir una PR de `<rama-actual>` hacia `<destino>`?" con opciones: crear PR hacia la candidata, otra rama (las demás habituales), y "No crear PR". El usuario puede escribir cualquier otra rama con "Other".
4. Si la rama destino no existe en el remoto, avisa y vuelve a preguntar.

### Crear la PR

Revisa todos los commits que entrarán en la PR, no solo el último:

```bash
git log --oneline origin/<destino>..HEAD
git diff --stat origin/<destino>...HEAD
```

Crea la PR con `gh`:

```bash
gh pr create --base <destino> --head <rama-actual> --title "<título>" --body-file - <<'EOF'
## Resumen
- ...

## Validación
- `docker compose exec app php artisan test` → resultado (o "no ejecutado")

## Notas
- Migraciones / cambios de configuración, si los hay
- Enlaces a issues o artefactos de `openspec/`, si aplica
EOF
```

- **Título**: en formato Conventional Commits; si la PR tiene un solo commit, reutiliza su mensaje.
- **Cuerpo**: resume el conjunto de cambios, indica qué validación se hizo realmente (no inventes resultados de tests que no se ejecutaron), destaca migraciones o cambios de configuración y enlaza issues/OpenSpec relevantes. Si hay cambios visibles en el panel, recuerda al usuario adjuntar capturas.
- Añade la línea de atribución que el entorno indique para PRs, si la hay.

Muestra al final la URL de la PR.

## Resumen final

Termina con un resumen breve:

- Hash corto y mensaje del commit
- Rama subida y remoto
- URL de la PR, o "PR no creada"
- Cualquier fichero que se dejó fuera a propósito y por qué
