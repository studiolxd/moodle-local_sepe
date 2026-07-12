# Changelog

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
