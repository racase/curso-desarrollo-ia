# Cierre del cambio

Archivado por petición del usuario el 2026-11-19. El usuario asumió la verificación visual y solicitó ejecutar el build con Podman.

## Evidencia disponible

- Tema, fuente Rubik local con licencia, marca, proveedor del panel y entrada Vite incorporados.
- Corregido el borde accidental de pantalla: el estilo de notificación se aplica a `.fi-no-notification`, no al contenedor global `.fi-no`.
- Build completado con Podman y Node 22; lockfile regenerado sin node_modules previos para incluir dependencias nativas de Linux.
- AdminLoginTest: 6 tests correctos, 20 aserciones.
- Laravel Pint: correcto, 62 archivos.

## Validación pendiente

Las tareas que combinan implementación y comprobaciones sin evidencia completa permanecen sin marcar. No se ejecutó la suite completa ni se completaron la revisión por roles, contraste, teclado, móvil, zoom o las capturas previstas. La captura del escritorio aportada por el usuario confirma la carga del tema en esa pantalla, no todos los escenarios de aceptación.

El archivo conserva estas limitaciones; no equivale a certificar el cumplimiento de todos los requisitos visuales y de accesibilidad.

## Compilación usada

Con el contenedor de aplicación iniciado en Podman:

```powershell
podman run --rm --volumes-from infraticket-app -w /app -v infraticket-node-modules:/app/node_modules docker.io/library/node:22-bookworm-slim sh -c "npm ci && npm run build"
```

Este flujo utiliza Node dentro del contenedor y el volumen de Composer de la aplicación, sin requerir Node ni una copia de vendor en Windows. Sustituye la decisión inicial de compilar exclusivamente desde el host para este entorno.
