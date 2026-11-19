# Tasks

## 1. Catálogo, esquema y persistencia

- [x] 1.1 Incorporar `TicketCategory` con las cinco categorías confirmadas y crear `TicketClassificationSchema` a partir de los enums; verificar con pruebas que admite las combinaciones válidas y rechaza claves extra, ausentes, nulos, arrays, tipos y valores incorrectos.
- [x] 1.2 Añadir `opis/json-schema` en una versión compatible con PHP 8.3 y JSON Schema 2020-12; verificar resolución de Composer y pruebas del esquema con el validador real, conservando las diferencias entre objeto y array.
- [x] 1.3 Añadir migración para `category` nullable y `priority` nullable sin valor predeterminado; verificar en bases SQLite y PostgreSQL desechables la conservación de prioridades históricas, la admisión de nulos y la reversión segura que rechaza prioridades nulas pendientes.
- [x] 1.4 Adaptar `Ticket`, casts, factories y seeders a clasificación completa, ausencia de clasificación y registros históricos; verificar que los tres casos se construyen y consultan sin error y documentar su representación en `docs/ticket-classification.md`.

## 2. Integración OpenRouter y tolerancia a fallos

- [x] 2.1 Consultar los metadatos públicos de OpenRouter para confirmar `openai/gpt-oss-120b` y su soporte de salida estructurada; documentar el alias lógico `gpt-oss-120b`, la correspondencia y la fuente en `docs/ticket-classification.md`, sin inferencias reales ni sustituciones silenciosas de modelo.
- [x] 2.2 Añadir configuración del servidor y variables de ejemplo para credencial, modelo, tiempo total de 10 s, conexión de 3 s y espera de 250 ms; verificar mediante pruebas que configuración ausente o inválida devuelve ausencia de clasificación sin solicitudes. No leer ni modificar `.env`.
- [x] 2.3 Implementar `TicketClassifier` y su adaptador HTTP con descripción como único dato del usuario, salida `json_schema` estricta y proveedores compatibles; verificar el cuerpo de la petición simulada, la validación local del mismo esquema y la conversión de `urgency` a prioridad.
- [x] 2.4 Implementar un único bucle de tres intentos para transporte, HTTP, contenido vacío, truncado, JSON inválido y esquema inválido, con esperas sustituibles; verificar éxito en intentos 1, 2 y 3, exactamente tres solicitudes al agotarse y ausencia de intentos o esperas posteriores al éxito.
- [x] 2.5 Añadir diagnósticos por intento y fallo definitivo con correlación y causas controladas; verificar que logs y excepciones expuestas no incluyen la credencial ficticia, cabeceras, descripción ni respuestas completas.
- [x] 2.6 Documentar presupuesto remoto de 30,5 segundos, límites del servidor, transmisión de la descripción, configuración y operación sin credencial en `docs/ticket-classification.md`; verificar concordancia con configuración y pruebas del adaptador.

## 3. Alta y edición en el backend

- [x] 3.1 Separar validación básica, comprobación de activo bajo bloqueo y validación de clasificación en `TicketData`; actualizar `TicketManagementTest` y `TicketAssetsTest` para verificar alta sin prioridad, límites de texto, referencia inválida y ausencia de llamadas con datos inválidos.
- [x] 3.2 Integrar el clasificador en `CreateTicket` tras autorización y validación y antes de la transacción breve de inserción; verificar una sola incidencia por operación, éxito y agotamiento, preservación de identidad y estado propuesta y ausencia de transacción durante llamadas y esperas, incluida la entrada de Filament.
- [x] 3.3 Revalidar autorización y activo antes de insertar, sin reintentar errores de persistencia como fallos del proveedor; verificar con una prueba de cambio durante la llamada que la desaparición del activo impide el guardado sin crear registros parciales.
- [x] 3.4 Añadir autorización de clasificación y aplicarla dentro del bloqueo de `UpdateTicket`; verificar cambios por técnicos y administradores, rechazo íntegro de cambios manipulados del solicitante y protección de incidencias cerradas.
- [x] 3.5 Admitir edición básica sin reclasificación y exigir pareja completa ante cambios de clasificación; verificar conservación de prioridad histórica, rechazo de cambios parciales o vaciado, clasificación manual tras fallo y cero llamadas al editar descripción o clasificación.
- [x] 3.6 Actualizar los ejemplos y permisos documentados para el alta y la edición; verificar que `docs/ticket-classification.md` explica la retirada de prioridad manual y los casos históricos sin contradecir las especificaciones delta.

## 4. Filament y revisión humana

- [x] 4.1 Retirar los campos de clasificación del alta y mostrar la pareja editable solo al personal autorizado en edición; adaptar `TicketResourceTest` y verificar alta sin selección manual, corrección técnica válida, rechazo de clasificación parcial y edición básica de registros sin clasificar.
- [x] 4.2 Mostrar categoría, urgencia y «Sin clasificar» en ficha y listado, conservando prioridad histórica y filtros actuales; verificar con Livewire registros completos, nulos e históricos, aislamiento por solicitante y filtros combinados.
- [x] 4.3 Verificar en pruebas Livewire que la credencial ficticia no aparece en recursos o errores renderizados y que peticiones manipuladas no permiten al solicitante modificar clasificación; conservar la protección de incidencias ajenas y cerradas.
- [x] 4.4 Documentar el flujo de revisión con capturas de propuesta, ausencia de clasificación y corrección técnica usando proveedor simulado; verificar visualmente las tres situaciones y adjuntar evidencia en `docs/ticket-classification.md`.

## 5. Comprobaciones integradas

- [x] 5.1 Ejecutar la suite de regresión en el entorno aislado documentado, con solicitudes externas inesperadas prohibidas y configuración de prueba explícita; verificar incidencias, activos, seguimiento, comentarios y resumen, conservando la salida completa de cualquier fallo.
- [x] 5.2 Ejecutar Laravel Pint sobre el PHP modificado y `openspec validate add-automatic-ticket-classification --strict --no-interactive`; verificar que ambos terminan correctamente y registrar los resultados en la documentación de verificación.
- [x] 5.3 Recorrer en Filament alta con éxito, agotamiento de tres intentos y clasificación manual con los roles pertinentes usando respuestas simuladas; verificar persistencia, ausencia de duplicados, estado de atención independiente y ausencia de nuevas llamadas tras la corrección.
