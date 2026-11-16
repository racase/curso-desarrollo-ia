# Bloqueo de archivos de entorno en Codex

El proyecto incluye un hook `PreToolUse` en `.codex/hooks.json`. Antes de ejecutar una herramienta, comprueba sus argumentos y deniega referencias explícitas a `.env` y `.env.*`, excepto `.env.example`. La comparación ignora mayúsculas para cubrir rutas Windows.

## Activación

1. Abre Codex desde `web/` o un subdirectorio de este proyecto.
2. Revisa y confía en el hook cuando Codex solicite autorizarlo. Los hooks locales requieren confianza antes de ejecutarse.
3. Inicia una sesión nueva para asegurar que se cargue la configuración. No se presupone que la sesión actual la recargue automáticamente.

Se requiere Node.js en el PATH. El comando de arranque busca el script ascendiendo desde el directorio de la sesión, por lo que no contiene rutas absolutas personales ni depende del directorio concreto desde el que se inició Codex dentro de `web/`.

La instalación comprobada es Codex CLI 0.169.0, con la función `hooks` estable y habilitada. El hook se configura para Codex; no modifica Claude Code ni la configuración global del usuario.

## Comportamiento

- Examina rutas individuales o listas en argumentos como `file_path`, `filePath`, `path`, `paths` o `uri`.
- Examina comandos de shell y código de herramientas de ejecución que contengan referencias explícitas a archivos de entorno.
- Devuelve `permissionDecision: deny` con un mensaje en español cuando detecta una referencia protegida.
- No concede permisos a las llamadas permitidas: deja que continúen las comprobaciones normales de Codex.
- Si el JSON recibido es inválido, devuelve código 2 y un mensaje genérico sin reproducir el contenido recibido.
- No abre los archivos solicitados, no ejecuta los comandos examinados y no guarda argumentos ni contenidos en registros.

Las pruebas pasan cadenas simuladas al hook. No leen ni crean archivos de entorno:

```bash
node --test .codex/hooks/block-env-read.test.cjs
```

## Alcance y límites

El hook protege frente a referencias explícitas en las herramientas que Codex intercepta. No es un aislamiento del sistema de archivos: no detecta necesariamente rutas construidas dinámicamente, enlaces simbólicos, comandos ofuscados ni lecturas que un programa haga internamente sin indicar el archivo en sus argumentos. Una operación como `artisan test` puede abrir archivos internamente y no se convierte en segura solo por este hook.

La inspección de comandos es conservadora: mencionar un archivo protegido en un comando también puede bloquearlo aunque el comando no pretenda leerlo. El contenido de parches y los prompts de subagentes no se tratan como comandos de lectura.

Para una prohibición absoluta de acceso hacen falta permisos o aislamiento del sistema operativo, además de las instrucciones del proyecto. Mantén la regla de `AGENTS.md` de usar `.env.example` como referencia.

Documentación oficial: [Hooks de Codex](https://learn.chatgpt.com/docs/hooks), especialmente `PreToolUse`, cobertura de herramientas y revisión de confianza.
