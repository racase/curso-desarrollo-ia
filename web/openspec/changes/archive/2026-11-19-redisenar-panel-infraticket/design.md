# Design

## Context

Véase `proposal.md` para la motivación y el alcance confirmado. La raíz redirige a `/admin`. `AdminPanelProvider` registra login, dashboard y AccountWidget estándar, marca InfraTicket y color Amber, sin tema propio. `IncidentResource` y sus páginas y relation managers usan componentes de Filament para listado, alta, ficha, acciones, historial y comentarios. Las tres especificaciones de negocio permanecen vigentes.

`resources/css/app.css` declara Instrument Sans y Vite configura esa fuente, pero no existe una entrada específica del panel. Docker instala Composer en un volumen y sirve PHP; ni el Dockerfile ni `docker/start.sh` compilan frontend. Los tests existentes cubren autenticación, permisos, formularios, filtros, seguimiento y comentarios con SQLite.

`DESIGN.md` es una referencia de marketing, no un diseño de pantallas InfraTicket. Incluye fuentes propietarias, componentes de precios y discrepancias entre front matter y prosa en colores de foco, colores atenuados y tamaños. Requiere adaptación explícita.

## Goals / Non-Goals

**Goals:**

- Centralizar tokens y estilos en un tema del panel, con cambios PHP limitados a integración y presentación.
- Conservar componentes de Filament y Livewire, nombres de acciones, policies y semántica accesible.
- Hacer verificable la adaptación con escenarios de la nueva spec, regresión funcional y capturas de navegador.

**Non-Goals:**

- Sustituir Filament, publicar vistas de vendor completas o modificar acciones de dominio, modelos y esquemas.
- Reproducir cada token o ejemplo comercial de DESIGN.md, modificar ese documento o crear una biblioteca de componentes independiente.

## Decisions

### 1. Tema específico de Filament

Crear `resources/css/filament/admin/theme.css` a partir de la estructura soportada por la versión instalada de Filament 5; registrar su entrada en Vite y en el panel mediante el mecanismo de tema de Filament. Comprobar en la implementación la documentación actual y el paquete instalado antes de usar sus puntos de extensión. Mantener estilos limitados al panel, usando variables y selectores públicos estables. Evitar selectores dependientes de posiciones del DOM o clases compiladas.

Alternativas: modificar solo `app.css` no garantiza que el panel lo cargue; sustituir layouts completos aumenta el coste de mantenimiento. Usar hooks o vistas pequeñas únicamente para el detalle de marca si el tema no basta. Mantener la página Login estándar para conservar sus interacciones y tests.

### 2. Polaridad de superficies y modo de presentación

Login con canvas #1f1633 y formulario blanco; CTA oscuro dentro del formulario. Panel con contenido blanco, texto #1f1633, navegación violeta, bordes #e5e7eb y campos con borde #cfcfdb. La navegación activa se diferencia por fondo y peso además de color. Dashboard conserva su contenido actual y el widget de cuenta.

Fijar el contenido operativo en modo claro y retirar el selector de modo del panel. El login oscuro es una superficie de marca independiente, no un segundo tema. Implementar dos temas operativos completos ampliaría el trabajo sin una referencia funcional suficiente. El acento lima #c2ef4e se reserva a un detalle decorativo de marca por vista; no comunica prioridad ni éxito. No añadir texturas, ilustraciones ni animaciones nuevas.

### 3. Tipografía y escala funcional

Usar Rubik local con licencia, pesos 400, 500, 600 y 700, carga intercambiable con fallback de sistema y sin petición a Google Fonts al navegar. Registrar la misma familia en Filament y en el tema; quitar la configuración Instrument Sans si queda sin consumidores. No añadir Sentri Display. Mantener cuerpo funcional de 16px, títulos 24–30px, auxiliares 14px, interlineado 1.5 y radios 6/8/12px. No trasladar titulares de 88px ni badges de 10px al panel. Acciones en mayúsculas visuales con espaciado 0.2px sin reescribir sus etiquetas accesibles.

Alternativa: cargar Rubik desde un proveedor externo simplifica instalación pero hace depender la apariencia de otra conexión en cada sesión.

### 4. Componentes, accesibilidad y discrepancias

Traducir tokens de DESIGN.md a variables del tema. Usar los valores hex del front matter como base; las variantes activas, de error, foco y texto secundario deben satisfacer la spec aunque requieran ajustar el tono. El foco conserva la intención azul de #9dc1f5 pero añade un contorno oscuro cuando el fondo claro no da contraste suficiente. Los estados deshabilitados se diferencian sin copiar automáticamente el contraste débil del ejemplo.

No mapear el color principal a lima ni confiar solo en cambiar Amber por violeta: los CTA requieren casi negro sobre blanco y blanco sobre oscuro. Mantener variantes semánticas de éxito, aviso y error de Filament cuando sean necesarias para comprender una operación, siempre con texto o icono. Esta excepción a la paleta de marca evita confundir estado con decoración.

Mantener inputs, selects, tablas, modales, notificaciones y su control de foco nativos. Estilizar su presentación y comprobar los estados hover, pulsado, foco, carga y deshabilitado. En la ficha, priorizar avanzar estado cuando esté permitido y presentar asignación como secundaria; si solo está disponible asignación, puede ser la principal. Las confirmaciones mantienen confirmar y cancelar inequívocos. No crear acciones adicionales para llenar el diseño.

### 5. Responsive y verificación

Preservar navegación lateral de escritorio y hacerla desplegable por debajo de 768px. Formularios y ficha pasan a una columna en móvil. Contener el desplazamiento de tablas en su región sin ocultar columnas ni acciones; ajustar ancho y alto de modales y permitir su desplazamiento vertical. Asegurar campos y acciones de 44px en móvil. Validar anchos 320, 375, 768 y 1440px y zoom 200%.

Reutilizar los tests actuales para comportamiento. Añadir solo comprobaciones significativas de carga del tema y login si la integración introduce un riesgo no cubierto; no escribir tests de cadenas de CSS. El contraste, foco, distribución y ausencia de desbordamientos requieren navegador. Capturar login, listado, alta, ficha y modal con datos de demo y revisar acciones disponibles para cada rol.

### 6. Compilación y ejecución local

Registrar el tema en el manifiesto de Vite y documentar `npm ci` y `npm run build` antes de abrir el panel con el nuevo tema. El contenedor actual no tiene Node: este cambio convierte la compilación local en un requisito de desarrollo explícito. Mantener PHP y Composer dentro del contenedor. El montaje `.:/app` permite que PHP sirva `public/build` generado desde el host. Verificar la web sin servidor Vite activo y sin archivos hot obsoletos.

Alternativa: incorporar Node o un servicio de construcción a Docker automatizaría el paso, pero amplía infraestructura para un cambio visual. No se incluye aquí. No versionar node_modules ni añadir una instalación npm al arranque PHP.

## Risks / Trade-offs

- [Cambios de markup de Filament] → Tema pequeño, selectores estables y revisión visual tras actualizar dependencias.
- [Referencia visual contradictoria o poco accesible] → Aplicar las decisiones de adaptación anteriores y verificar contraste sobre estados reales.
- [Compilación frontend ausente] → Actualizar README y verificar manifiesto y assets con el flujo documentado desde Docker.
- [Preferencia oscura guardada previamente] → Comprobar que el panel queda claro también con una preferencia anterior y con el sistema operativo en oscuro.
- [Tests de PHP no detectan defectos visuales] → Evidencia de navegador para pantallas, roles, tamaños, teclado y zoom.

## Migration Plan

1. Incorporar tema, fuente local con licencia y registro del panel/Vite sin migraciones ni reinicialización de datos.
2. Ejecutar `npm ci`, `npm run build`, Pint y la suite PHP dentro del contenedor donde corresponda.
3. Verificar que login y panel sirven assets compilados sin servidor Vite, completar la revisión visual y adjuntar capturas.
4. Documentar el requisito de build y el alcance del tema en README.
5. Para revertir, restaurar proveedor, configuración Vite y archivos de presentación anteriores y regenerar assets. No hay reversión de base de datos.
