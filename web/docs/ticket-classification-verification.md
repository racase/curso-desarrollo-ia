# Verificación de clasificación de incidencias

Cambio OpenSpec: add-automatic-ticket-classification. Issue local: 7.

## Alcance y entorno

Se implementan categoría y urgencia propuestas por OpenRouter, contrato JSON Schema validado localmente con Opis, dos reintentos tras el primer fallo, alta «Sin clasificar» y corrección por personal técnico en Filament.

Las pruebas usan PHP 8.3, SQLite en memoria y el PostgreSQL desechable infraticket_test. El contenedor infraticket-classification-app tiene un volumen de dependencias propio y una red exclusiva con infraticket-classification-db. No se migró ni se reinicializó la base de desarrollo.

Tests/TestCase omite los archivos de entorno locales, elimina cualquier clave de OpenRouter heredada y prohíbe solicitudes HTTP inesperadas. Las pruebas del adaptador usan una clave ficticia y esperas simuladas. La revisión visual usa una base SQLite independiente en /tmp/infraticket-review.sqlite.

## Correspondencia de requisitos y pruebas

| Comportamiento | Evidencia |
| --- | --- |
| Catálogos y JSON cerrado; nulos, arrays y valores extra rechazados | TicketClassificationSchemaTest |
| Contrato enviado, modelo, credencial en cabecera y solo descripción | OpenRouterTicketClassifierTest |
| Éxito en intentos 1, 2 y 3; agotamiento ante HTTP, conexión y contenido inválido | OpenRouterTicketClassifierTest |
| Configuración ausente o inválida sin llamadas; logs sin contenido sensible | OpenRouterTicketClassifierTest |
| Una sola incidencia, valores iniciales y campos del cliente ignorados | TicketClassificationTest y TicketManagementTest |
| Llamadas y esperas fuera de la transacción de guardado | TicketClassificationTest, mediante entrada directa y Livewire |
| Activo eliminado durante la llamada y error de persistencia sin reinferencia | TicketClassificationTest |
| Conservación histórica, valores nulos y reversión protegida | TicketClassificationMigrationTest en SQLite y PostgreSQL |
| Corrección técnica y administrativa, rechazo de peticiones manipuladas y cierre | TicketClassificationTest |
| Vista, filtros y edición de registros completos, históricos y sin clasificar | TicketClassificationTest, TicketResourceTest y TicketAssetsTest |
| Propuesta, fallo y corrección en navegador | Capturas y recorrido descritos en ticket-classification.md |

## Preparación y diagnóstico

La primera ejecución completa detectó que faltaba public/build/manifest.json. Se instalaron las dependencias Node desde el lockfile y se compilaron los assets, siguiendo docs/panel-theme.md: al vivir PHP en un volumen, se copiaron los recursos de Filament al vendor local ignorado por Git. La compilación mostró un aviso sobre la dependencia opcional fontaine; terminó correctamente sin añadirla.

También se corrigió la preparación de la nueva prueba de migración: no debe mantener una transacción RefreshDatabase de SQLite al cambiar de conexión a PostgreSQL. Ambas migraciones y las pruebas de clasificación pasaron juntas: 13 pruebas y 129 comprobaciones.

La siguiente suite completa ejecutó 121 pruebas, con dos fallos de espera en procesos auxiliares de pruebas de concurrencia existentes. Los dos casos pasaron al repetirse por separado: 2 pruebas y 12 comprobaciones. No se relajaron sus plazos ni se modificaron esas pruebas.

Las salidas completas de los intentos se conservaron en /tmp/issue7-phpunit.log, /tmp/issue7-phpunit-final.log y /tmp/issue7-concurrency-recheck.log dentro del contenedor aislado. La repetición completa (/tmp/issue7-phpunit-clean.log) tuvo otro timeout en una prueba de concurrencia distinta. Para la comprobación definitiva se copió el mismo código y las mismas dependencias a /tmp/issue7-linux-check dentro del contenedor, se regeneró el autoload sin scripts y se ejecutó la suite sin modificar sus esperas. No se copiaron archivos de entorno. El resultado completo está en /tmp/issue7-phpunit-linux.log.

## Comprobaciones finales

- PHPUnit: **121 pruebas y 903 comprobaciones correctas**, sin errores, fallos ni avisos, en 13,385 segundos desde el sistema de archivos Linux del contenedor.

- Laravel Pint: comprobación correcta sobre 38 archivos PHP.
- OpenSpec: validación estricta correcta.
- Chrome local: tres capturas verificadas, propuesta y fallo al crear, corrección manual persistida y cero errores de JavaScript.
- Base de revisión: exactamente dos incidencias, Hardware / Alta y Software / Media, ambas en estado Propuesta. No se duplicaron registros por reintentos.

## Incorporación al entorno de desarrollo

Instalar las dependencias Composer del lockfile, aplicar la nueva migración y desplegar los assets generados. Configurar OPENROUTER_API_KEY exclusivamente mediante el mecanismo de secretos del backend; las variables disponibles están documentadas en el archivo de ejemplo y en ticket-classification.md. Sin credencial, el alta funciona como «Sin clasificar».

No se ha validado la precisión semántica con inferencias reales: se verificó el identificador y soporte anunciados en los metadatos públicos del proveedor, y se probó la integración con respuestas simuladas.
