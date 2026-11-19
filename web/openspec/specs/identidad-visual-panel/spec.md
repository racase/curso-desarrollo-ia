# identidad-visual-panel Specification

## Purpose

Ofrecer una identidad visual coherente, accesible y adaptable para el acceso y las pantallas operativas de InfraTicket a partir del lenguaje visual de DESIGN.md.

## Requirements

### Requirement: Identidad InfraTicket coherente
El sistema SHALL presentar el login, dashboard y pantallas de incidencias con la marca InfraTicket, tipografía Rubik con alternativa de sistema y una identidad violeta basada en DESIGN.md. SHALL reservar el lima para un único detalle de énfasis por vista y nunca usarlo como fondo de botón ni texto de cuerpo. SHALL mantener la tipografía funcional en 16px y títulos de página entre 24 y 30px.

#### Scenario: Continuidad entre pantallas
- **WHEN** una persona recorre login, dashboard, listado, alta y ficha
- **THEN** reconoce la misma marca, tipografía, escala de títulos, bordes y jerarquía visual sin encontrar marcas ni textos comerciales de Sentri

#### Scenario: Fuente no disponible
- **WHEN** el navegador no puede cargar Rubik
- **THEN** el contenido permanece legible y utilizable con la fuente alternativa de sistema

### Requirement: Login con identidad violeta
El sistema SHALL mostrar /admin/login con fondo violeta profundo y un formulario claramente delimitado, etiquetas visibles y una acción principal de acceso. SHALL conservar autenticación, errores de credenciales y redirecciones existentes, incluida la redirección de / a /admin. Los errores SHALL aparecer asociados al formulario sin depender solo del color.

#### Scenario: Acceso correcto e incorrecto
- **WHEN** una persona envía credenciales válidas o inválidas desde el login rediseñado
- **THEN** accede al panel con las válidas y recibe un error legible sin autenticar con las inválidas

#### Scenario: Acceso sin sesión
- **WHEN** una persona sin sesión visita / o /admin
- **THEN** termina en el login y no se muestran datos de incidencias

### Requirement: Panel operativo de superficies claras
El sistema SHALL presentar dashboard, listado, alta y ficha sobre superficies claras con texto violeta oscuro y navegación de identidad violeta. SHALL extender el mismo lenguaje a filtros, estados vacíos, historial, comentarios, modales y avisos. SHALL mantener etiquetas de estado y prioridad legibles y toda la información y acciones autorizadas de los flujos existentes.

#### Scenario: Gestión completa
- **WHEN** un usuario consulta, filtra, crea y abre una incidencia accesible y usa sus acciones autorizadas
- **THEN** tablas, campos, ficha, historial, comentarios y confirmaciones mantienen una apariencia coherente sin ocultar datos, errores ni controles necesarios

#### Scenario: Estados sin contenido y datos extensos
- **WHEN** un listado no tiene resultados o una ficha contiene textos largos e historial o comentarios paginados
- **THEN** se distinguen el estado vacío y las secciones, el texto puede leerse completo y la paginación permanece accesible

### Requirement: Jerarquía de acciones y feedback
El sistema SHALL distinguir una acción principal por formulario, modal o grupo de acciones, usando fondo #150f23 con texto blanco en superficies claras y blanco con texto oscuro en oscuras. SHALL diferenciar acciones secundarias y estados de foco, pulsación, carga y deshabilitado. SHALL identificar errores, avisos y éxito mediante texto o icono además del color.

#### Scenario: Acciones de la ficha
- **WHEN** un usuario abre una ficha con varias acciones permitidas o una confirmación
- **THEN** puede distinguir la acción principal, las alternativas y la cancelación, y reconocer el resultado o conflicto de la operación

#### Scenario: Envío pendiente
- **WHEN** un formulario está enviándose o su acción está deshabilitada
- **THEN** el control comunica ese estado y no aparenta estar disponible para repetir la operación

### Requirement: Navegación y contraste accesibles
El sistema SHALL permitir operar login, navegación, filtros, formularios y modales por teclado con foco visible y orden lógico. SHALL alcanzar contraste WCAG AA de 4.5:1 en texto normal y 3:1 en texto grande y elementos gráficos necesarios. SHALL conservar etiquetas y mensajes asociados a los campos, y devolver el foco al activador al cerrar un modal.

#### Scenario: Operación con teclado
- **WHEN** una persona usa Tab, Shift+Tab, Enter y Escape para acceder, filtrar, crear o abrir y cerrar una confirmación
- **THEN** puede completar la interacción, localizar siempre el foco y volver al activador del modal sin quedar atrapada

#### Scenario: Errores y estados visuales
- **WHEN** se muestran errores de validación, avisos o etiquetas de estado y prioridad
- **THEN** su significado se entiende sin distinguir colores y los textos y controles activos cumplen los contrastes exigidos

### Requirement: Adaptación móvil y ampliación
El sistema SHALL mantener login y panel utilizables desde 320px de ancho y con ampliación del 200%, sin desbordamiento horizontal de página. SHALL colapsar la navegación bajo 768px, apilar formularios y ajustar modales al espacio disponible. Las tablas SHALL permitir desplazamiento horizontal dentro de su contenedor cuando sea necesario. Los botones y campos interactivos SHALL ofrecer un área mínima de 44 por 44px en móvil.

#### Scenario: Pantalla estrecha
- **WHEN** una persona usa login, listado, alta y ficha a 320px o 375px de ancho
- **THEN** accede al menú, acciones y campos sin superposiciones ni pérdida de contenido y puede consultar todas las columnas desplazando solo el contenedor de la tabla

#### Scenario: Ampliación y escritorio
- **WHEN** una persona revisa el panel a 768px y 1440px o amplía el navegador al 200%
- **THEN** el contenido se redistribuye sin cortar controles, mensajes o confirmaciones y conserva una jerarquía legible
