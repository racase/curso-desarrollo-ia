# Design

## Context

Véase `proposal.md` para la motivación. El panel se configura en `AdminPanelProvider` con ámbar, acceso y dashboard estándar, cuatro recursos y `TicketStatusOverview`. La raíz redirige a `/admin`; no existe una web comercial. Vite 8 y Tailwind 4 ya están instalados, pero `app.css` no es un tema del panel. Las pruebas existentes cubren acceso, políticas, recursos y recuentos con SQLite aislado.

## Goals / Non-Goals

**Goals:** centralizar la apariencia en un tema del panel, reutilizar los componentes y las interacciones accesibles de Filament y compilar fuente y estilos como assets locales.

**Non-Goals:** sustituir plantillas del proveedor, reconstruir navegación o formularios en JavaScript, introducir nuevos flujos o trasladar proporciones de marketing a tablas operativas.

## Decisions

### Tema propio sobre Filament

Crear `resources/css/filament/admin/theme.css` importando el tema del proveedor, con fuentes de escaneo para las clases de la aplicación. Registrarlo mediante la API del panel y Vite. Usar selectores de componentes de Filament y variables CSS propias; no editar `vendor/` ni duplicar plantillas. Cambiar solo el color primario no cubre superficies y estados; reemplazar vistas completas aumentaría el coste de actualización.

### Tokens y polaridad

Usar los hexadecimales del front matter como valores canónicos cuando discrepan de la prosa: secundarios #bdb8c0 y foco #9dc1f5. Cada modo define variables de fondo, superficie, texto, secundarios, borde y acción. En claro, derivar un gris violeta más oscuro para secundarios y límites de campos que permita cumplir el contraste; en oscuro, reforzar los límites necesarios de controles. Los bordes puramente decorativos pueden conservar los tonos hairline. Documentar estas adaptaciones en `docs/` sin alterar el documento de referencia.

Conservar los colores semánticos de Filament para peligro, errores y notificaciones: la restricción de acentos de marketing no debe ocultar significado de acciones. Reemplazar ámbar como identidad primaria, evitando imponer lima a las escalas automáticas de Filament. Usar lima únicamente como pequeño marcador en la marca. Mantener etiquetas de estados y prioridades.

### Tipografía y densidad

Servir Rubik localmente con licencia, pesos 400–700 y `font-display: swap`, usando la configuración de fuente local del panel. Utilizar títulos de página de 30px, cuerpo funcional de 16px/1.5, secundarios de 14px y botones de 14px/700 con tracking 0.2px. Las mayúsculas se aplican con CSS para conservar las etiquetas originales. Radios: campos 6px, botones 8px, tarjetas 12px, etiquetas 4px. Espaciado compacto de 8–32px, sin titulares de 88px ni secciones de 96px.

### Interacciones y adaptación

Mantener el gestor de temas, navegación móvil, foco modal y estados de carga de Filament. Personalizar colores sin eliminar outlines; incorporar foco contrastado por polaridad y compatibilidad con colores forzados. Respetar movimiento reducido si se añaden transiciones. Evitar medidas fijas en formularios y tablas; preservar el desplazamiento local de tablas y controles de 44px. No incorporar dependencias JavaScript para el tema.

## Risks / Trade-offs

- Cambios futuros en selectores Filament → concentrar overrides, inspeccionar CSS instalado y verificar capturas y flujos tras actualizaciones.
- Contradicciones y contrastes insuficientes de DESIGN.md → aplicar la precedencia anterior y comprobar pares activos y secundarios en ambos modos.
- Fuente externa en tiempo de ejecución → empaquetar Rubik y licencia localmente con fallback del sistema.
- Pruebas HTTP insuficientes para CSS → complementar regresión automatizada con revisión en navegador de acceso, dashboard, recursos, modales y estados en escritorio y móvil.
- Cambio previo pendiente de archivo → trabajar sobre el código implementado sin modificar sus artefactos ni sus contratos funcionales.

## Migration Plan

Compilar con `npm run build` y desplegar código y assets juntos. No hay cambios de datos. Para revertir, restaurar la configuración previa del panel y Vite, retirar los assets propios y recompilar. Ejecutar pruebas con el entorno de tests aislado, sin leer configuración privada ni modificar PostgreSQL.
