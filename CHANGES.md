# Changelog

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
