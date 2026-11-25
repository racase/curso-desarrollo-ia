## Purpose

Recoger una valoración definitiva de la atención tras el cierre de una incidencia y ofrecer al administrador medias por técnico con atribución histórica estable.

## ADDED Requirements

### Requirement: Valoración exclusiva del solicitante tras el cierre
El sistema SHALL permitir valorar únicamente a un usuario autenticado con rol solicitante que sea el solicitante de la incidencia. La incidencia SHALL estar Cerrada, tener un técnico del cierre conservado y carecer de puntuación previa. La valoración SHALL ser opcional y no SHALL condicionar el cierre ni cambiar el estado. Estas reglas SHALL comprobarse también ante envíos directos al servidor.

#### Scenario: Envío permitido
- **WHEN** el solicitante envía una puntuación válida para su incidencia cerrada con técnico del cierre y sin valoración
- **THEN** se registra la puntuación y su fecha sin cambiar estado, asignación ni historial de estados

#### Scenario: Incidencia pendiente de cierre
- **WHEN** el propietario intenta valorar una incidencia Propuesta, Abierta, En curso o Resuelta
- **THEN** no se ofrece la acción y el envío directo se rechaza sin guardar puntuación

#### Scenario: Actor no autorizado
- **WHEN** otro solicitante, un técnico o un administrador intenta valorar la incidencia, incluso si uno de estos dos últimos figura como solicitante
- **THEN** el sistema deniega el envío sin modificar datos

#### Scenario: Acceso sin sesión
- **WHEN** una persona sin autenticar intenta enviar una valoración
- **THEN** se exige autenticación y no se registra puntuación

#### Scenario: Cierre sin valoración
- **WHEN** se cierra una incidencia y el solicitante no la valora
- **THEN** permanece cerrada sin puntuación y no aporta datos a ninguna media

### Requirement: Escala válida y envío definitivo
El sistema SHALL admitir únicamente puntuaciones enteras de 1 a 5 y conservar conjuntamente puntuación y fecha. Cada incidencia SHALL admitir un único envío aceptado, sin edición ni eliminación posterior. Ante envíos repetidos o simultáneos SHALL conservar la primera puntuación confirmada y comunicar el conflicto a los demás intentos, sin generar aportaciones adicionales.

#### Scenario: Escala completa
- **WHEN** el solicitante elegible elige cualquiera de los valores enteros entre 1 y 5
- **THEN** se acepta exactamente el valor elegido

#### Scenario: Puntuación inválida
- **WHEN** se envía un valor vacío, cero, negativo, mayor que 5, fraccionario o no numérico
- **THEN** se muestra un error y no se guarda puntuación ni fecha

#### Scenario: Doble envío
- **WHEN** se repite un envío o dos pestañas envían puntuaciones distintas a la misma incidencia simultáneamente
- **THEN** solo se acepta una puntuación, los demás intentos reciben un aviso de que ya está valorada y la media cuenta la incidencia una sola vez

### Requirement: Atribución histórica estable
El sistema SHALL conservar al técnico asignado al confirmar el cierre, de forma atómica con el estado y su historial. Las reasignaciones posteriores y los cambios de rol del técnico no SHALL alterar esta atribución ni sus puntuaciones. Para las incidencias cerradas existentes al incorporar la funcionalidad SHALL conservar una sola vez el técnico asignado en ese momento; sin asignación, SHALL mantenerlas sin técnico del cierre y sin posibilidad de valoración, aunque se asignen después. No SHALL aceptar un técnico proporcionado por el formulario de valoración.

#### Scenario: Reasignación posterior al cierre
- **WHEN** una incidencia cerrada por la atención del técnico A se reasigna al técnico B antes o después de valorarse
- **THEN** la valoración se atribuye siempre a A y no contribuye a la media de B

#### Scenario: Fallo durante el cierre
- **WHEN** falla el registro del historial al cerrar una incidencia
- **THEN** se revierte tanto el estado como la captura del técnico del cierre

#### Scenario: Cierre concurrente con reasignación
- **WHEN** se confirman concurrentemente un cierre y una reasignación
- **THEN** se conserva el técnico que tenía la incidencia en el cierre según el orden serializado de las operaciones, sin mezclar resultados parciales

#### Scenario: Datos anteriores a la funcionalidad
- **WHEN** se incorpora la funcionalidad con incidencias cerradas con técnico, cerradas sin técnico y no cerradas
- **THEN** únicamente las cerradas con técnico reciben atribución histórica inicial y ninguna recibe puntuación inventada; las no cerradas capturan el técnico cuando se cierren

#### Scenario: Incidencia histórica sin técnico
- **WHEN** se asigna un técnico a una incidencia que ya estaba cerrada sin técnico al incorporar la funcionalidad
- **THEN** sigue sin habilitarse su valoración y no se inventa atribución de cierre

### Requirement: Interacción accesible y consulta propia
El sistema SHALL ofrecer en la ficha elegible la acción «Valorar atención» con cinco opciones de estrellas sin selección inicial, etiquetas textuales de 1 a 5, foco visible y manejo mediante teclado y lector de pantalla. SHALL indicar antes de confirmar que el envío es definitivo. Tras enviarlo, SHALL ocultar la acción y mostrar al propietario con rol solicitante la puntuación y su fecha en lectura, también al recargar. No SHALL exponer puntuaciones individuales a los demás roles mediante esta funcionalidad. La presentación SHALL seguir `DESIGN.md`, permanecer legible a 320px y con ampliación del 200%, y ofrecer controles táctiles de al menos 44px sin depender exclusivamente del color.

#### Scenario: Elección y confirmación
- **WHEN** el solicitante recorre las estrellas con teclado o lector de pantalla
- **THEN** identifica cada valor, la selección y el foco, y puede confirmar una elección explícita conociendo que no podrá editarla

#### Scenario: Consulta después del envío
- **WHEN** el solicitante vuelve a abrir su incidencia ya valorada
- **THEN** ve la puntuación y su fecha sin controles de edición ni nuevo envío

#### Scenario: Ficha consultada por el equipo
- **WHEN** un técnico o administrador abre una incidencia valorada
- **THEN** conserva el acceso habitual a la ficha sin ver su puntuación individual ni poder valorarla

#### Scenario: Pantalla estrecha
- **WHEN** se utiliza la ficha a 320px o con ampliación del 200%
- **THEN** el control y la puntuación siguen siendo legibles y operables sin desbordamiento horizontal de página

### Requirement: Media y recuento por técnico
El sistema SHALL mostrar en el dashboard administrativo un widget «Valoraciones por técnico» con nombre, media aritmética sobre 5 a un decimal y número de puntuaciones recibidas. SHALL incluir a todos los usuarios con rol técnico y a los usuarios conservados como técnico del cierre aunque cambien de rol, identificando estos últimos como «Histórico». SHALL agrupar por el técnico del cierre, excluir puntuaciones nulas y mostrar «Sin valoraciones» con recuento 0 en ausencia de puntuaciones. SHALL ordenar por nombre y, en caso de empate, identificador, y ofrecer paginación. Los filtros de incidencias no SHALL alterar el cálculo.

#### Scenario: Cálculo y ausencia de puntuación
- **WHEN** un técnico tiene puntuaciones 5 y 4 y otra incidencia sin valorar
- **THEN** se muestra «4,5 / 5» con 2 valoraciones

#### Scenario: Técnico sin valoraciones
- **WHEN** un técnico no tiene ninguna puntuación
- **THEN** aparece con «Sin valoraciones» y 0, sin atribuirle una media cero

#### Scenario: Cambio de rol
- **WHEN** se reasignan las incidencias actuales de un técnico y se cambia su rol
- **THEN** sus atribuciones históricas permanecen, sus puntuaciones siguen en su propia media y su fila se identifica como «Histórico»

#### Scenario: Ausencia de técnicos
- **WHEN** no existen técnicos actuales ni usuarios con atribución de cierre
- **THEN** el widget muestra «No hay técnicos»

### Requirement: Acceso administrativo y actualización del resumen
El sistema SHALL restringir el widget y sus datos a administradores autenticados, incluyendo accesos directos y peticiones de actualización. SHALL recalcular al cargar o recargar y cada 30 segundos mientras sea visible, incluyendo las valoraciones confirmadas antes de cada consulta. No SHALL reutilizar resultados entre sesiones con distintos permisos. Las columnas SHALL conservar etiquetas legibles y acceso a todos sus datos en móvil y al ampliar al 200%.

#### Scenario: Acceso por rol
- **WHEN** un técnico, solicitante o persona sin sesión intenta obtener los datos del widget directamente
- **THEN** se deniega el acceso sin revelar medias ni recuentos

#### Scenario: Valoración reciente
- **WHEN** se confirma una valoración y un administrador recarga el dashboard o llega la siguiente actualización visible
- **THEN** el resumen incluye exactamente esa puntuación en la media y recuento del técnico atribuido

#### Scenario: Pérdida del rol administrador
- **WHEN** un administrador cambia de rol con el dashboard abierto y llega una nueva petición del widget
- **THEN** no recibe datos administrativos

#### Scenario: Tabla en móvil
- **WHEN** el administrador consulta el resumen a 320px o con ampliación del 200%
- **THEN** puede identificar cada técnico, su media y su recuento sin desbordamiento horizontal de página
