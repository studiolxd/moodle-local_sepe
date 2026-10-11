# Changelog

## 1.1.0 (2026-10-11)

Compatibilidad con Moodle 5.3 y correcciones de una auditoría de seguridad,
errores y rendimiento.

**Cambian los datos de seguimiento que recibe el SEPE**:

- `USO` solo cuenta la actividad entre `FECHA_INICIO` y `FECHA_FIN` de la
  acción.
- `PARTICIPANTES` son los usuarios matriculados con un *rol calificado*
  (`$CFG->gradebookroles`), en lugar de cualquier usuario con un rol de
  arquetipo estudiante: entran los roles de alumno personalizados y salen los
  usuarios con rol pero sin matrícula.
- `obtenerAccion` sirve el XML desde una caché de hasta 24 horas, que la
  tarea programada regenera a diario.
- Las peticiones de más de 5 MB se rechazan con un SOAP Fault.

Seguridad y errores:

- El log de peticiones ya no guarda la contraseña WS-Security, y de las
  peticiones no autenticadas solo guarda los primeros 4 KB.
- La página de gestión es accesible con `local/sepe:manage` sin ser
  administrador del sitio (antes solo se registraba para quien tenía
  `moodle/site:config`).
- `require_sesskey()` en la página de gestión: un sesskey inválido daba la
  acción por no enviada sin avisar.
- El reinicio de datos (página y CLI) se ejecuta en una transacción y
  dispara el evento `data_reset`; vincular una acción dispara
  `accion_mapped`.
- Al borrar un curso, sus acciones quedan desvinculadas.
- Los eventos validan su `other` y declaran su mapping de restauración.

Rendimiento:

- La generación del XML hace un número fijo de consultas, sin consultas por
  alumno (notas, finalización y NIF, incluido el de campos de perfil), y
  pasa los participantes como subconsulta en lugar de listas `IN`.
- La tarea *Actualizar datos de seguimiento* guarda el resultado; antes lo
  generaba y lo descartaba.
- La página de gestión ya no carga todos los cursos del sitio: el selector
  busca cursos bajo demanda (autocompletado AJAX).
- La tabla de acciones no carga las columnas XML.

Otros:

- Moodle 5.3: `$plugin->supported = [502, 503]`.
- Se eliminan las columnas `userid` de `local_sepe_tutor` y
  `local_sepe_participante`, que nunca se rellenaban; el proveedor de
  privacidad pasa a cubrir la caché del XML y declara el envío de datos al
  SEPE como ubicación externa.
- `$ADMIN->fulltree` en `settings.php`, `http_response_code()` en el
  endpoint y cabecera de caché en el WSDL.
- Workflow de GitHub Actions con moodle-plugin-ci para Moodle 5.2 y 5.3.
- Verificado con PHP 8.4 y PostgreSQL 17: phpunit (49 tests) en verde
  sobre Moodle 5.2.4 y 5.3.0; phplint, phpcs, phpdoc, validate, savepoints y
  mustache en verde; actualización desde la 1.0.1 y prueba del endpoint por
  HTTP sobre 5.3.0.

## 1.0.1 (2026-08-10)

Correcciones de calidad de código, sin cambios funcionales.

- Corrige dos `@param` documentados con genéricos (`array<int, string>` en
  `acciones_page::__construct` y `array<int, \stdClass>` en
  `generator::build_participantes`), que el comprobador de phpdoc de Moodle
  contaba como lista de parámetros incompleta porque solo acepta la
  notación `tipo[]` cuando el argumento se declara `array`.
- Elimina un `global $DB;` sin uso en `generator::students()`.
- Verificado con moodle-plugin-ci 4.5.11 sobre Moodle 5.2.2 y PHP 8.4:
  phplint, phpcs, phpdoc, validate, savepoints y phpunit (28 tests,
  107 aserciones) en verde.

## 1.0.0 (2026-07-12)

- Primera versión. Implementa el Servicio Web de Seguimiento de acciones
  formativas de teleformación del SEPE (WSDL ProveedorCentroTFWS 2014-06-19,
  modelo de datos 2019-11-29) para Moodle 5.2:
  - Endpoint SOAP 1.1 document/literal con autenticación WS-Security
    UsernameToken (PasswordText) y las 6 operaciones del contrato.
  - Persistencia de centro y acciones formativas (literal + normalizada),
    con eventos de Moodle y log de peticiones con retención configurable.
  - Generación de datos reales de seguimiento desde cursos Moodle
    (participantes, uso por franjas horarias, seguimiento y evaluación,
    resultados) para acciones vinculadas a curso.
  - Página de administración: datos del centro, acciones recibidas,
    vinculación acción-curso, log y reinicio de datos de validación.
  - Proveedor de privacidad (GDPR), capacidades, tareas programadas y
    tests PHPUnit que reproducen la secuencia completa del kit de
    autoevaluación oficial.
- Verificado: 22/22 pruebas OK con el kit oficial ProvCentTFVal_20161118
  sobre HTTPS.
