# local_sepe — Servicio Web de Seguimiento de Teleformación del SEPE

Plugin local de Moodle que implementa el **Servicio Web de Seguimiento de
acciones formativas impartidas en modalidad de teleformación** del SEPE
(Servicio Público de Empleo Estatal, España), conforme al WSDL oficial
`ProveedorCentroTFWS` (junio 2014) y al *Modelo de datos para el seguimiento
de acciones formativas en teleformación* (noviembre 2019), en desarrollo del
Anexo V de la Orden TMS/369/2019.

Los centros y entidades de formación acreditados/inscritos para impartir
teleformación deben exponer este servicio para que el SEPE (y los órganos de
seguimiento autonómicos) consulten los datos de seguimiento de sus acciones
formativas. La correcta operativa del servicio es **condición bloqueante** en
el trámite de acreditación/inscripción (autovalidación en la aplicación
TeleformacionWebRED).

## Requisitos

- Moodle 5.2 (probado con 5.2.1). Sin retrocompatibilidad con versiones anteriores.
- PHP 8.3+ con las extensiones dom y libxml (incluidas por defecto).
- Endpoint accesible por HTTPS con certificado válido (exigido por el SEPE en
  producción).
- **El servidor web debe entregar a PHP los cuerpos de peticiones con
  `Transfer-Encoding: chunked`** — el cliente SOAP del SEPE (Axis2) envía las
  peticiones chunked. Nginx+PHP-FPM lo maneja correctamente; Apache con
  mod_proxy_fcgi requiere `SetEnv proxy-sendcl 1` (los cuerpos multi-chunk
  llegan vacíos sin él, PHP bug #60826). Compruébalo con:
  `curl -H 'Transfer-Encoding: chunked' --data-binary @peticion-grande.xml <endpoint>`

## Instalación y configuración

1. Copia el plugin en `local/sepe` (en Moodle 5.x: `public/local/sepe`) y
   ejecuta la actualización (`php admin/cli/upgrade.php`).
2. En *Administración del sitio → Extensiones → Servicio de seguimiento de
   teleformación del SEPE → Ajustes*:
   - **Contraseña del servicio web**: la que introducirás en el formulario del
     SEPE junto a la URL del servicio. Solo se almacena un hash irreversible.
     El usuario WS-Security es siempre `SEPE` (fijado por la especificación).
   - **Datos identificativos del centro**: devueltos por `obtenerDatosCentro`
     mientras el SEPE no haya registrado el centro vía `crearCentro`.
     **Importante**: deben coincidir exactamente con los datos declarados ante
     el SEPE — la autovalidación compara la respuesta con lo declarado.
   - **Campo de usuario con el NIF**: campo del perfil que contiene el
     NIF/NIE del alumnado (`idnumber` o `profile_<nombrecorto>`).
3. La URL del endpoint es `https://<tu-moodle>/local/sepe/endpoint.php`
   (el WSDL se sirve en `...endpoint.php?wsdl`).

## Operaciones implementadas

`crearCentro`, `obtenerDatosCentro`, `crearAccion`, `obtenerAccion`,
`obtenerListaAcciones` y `eliminarAccion`, con autenticación WS-Security
UsernameToken 1.0 (PasswordText), SOAP 1.1 document/literal y los códigos de
retorno del protocolo (0, -1, -2, 1, 2 + `ETIQUETA_ERROR`).

Las acciones formativas recibidas del SEPE se persisten literalmente
(`rawxml`) y normalizadas (cabecera, especialidades, tutores, participantes).
`obtenerAccion` devuelve:

- **Acciones sin vincular a curso**: eco fiel del XML recibido (lo que exige
  el kit de autoevaluación).
- **Acciones vinculadas a un curso Moodle** (página *Acciones formativas
  SEPE*): XML regenerado con datos reales del curso —
  - `PARTICIPANTES` desde las matriculaciones (rol estudiante), con
    `FECHA_ALTA`/`FECHA_BAJA` de la matrícula y el documento de identidad del
    campo configurado (los usuarios sin NIF/NIE válido se omiten y se anotan
    en el log de depuración);
  - `USO` desde el log estándar: sesiones con corte de inactividad de 1 hora
    (criterio de block_dedication), asignadas a la franja del modelo de datos
    en la que comienzan (mañana 7-15h, tarde 15-23h, noche 23-7h), duración en
    horas enteras con el redondeo del modelo (≤0,5 abajo, >0,5 arriba);
  - `SEGUIMIENTO_EVALUACION` desde finalización de actividades, intentos de
    cuestionarios y entregas de tareas, y actividades calificables;
  - `RESULTADOS` desde la finalización del curso y la calificación final
    (escala 5-10 × 100): 3/4 según nota al completar, 5 si la acción terminó
    sin completar, 2 si la matrícula está suspendida o vencida, 0 en curso;
    `PUNTUACION_FINAL` solo con resultado 3.
  - `INDICADOR_COMPETENCIAS_CLAVE` se informa como `01` (cumple requisitos de
    acceso).

La tarea programada *Actualizar datos de seguimiento SEPE* regenera a diario
los datos de las acciones vinculadas (el SEPE exige una antigüedad máxima de
7 días).

## Validación con el kit oficial del SEPE

El kit de autoevaluación (`ProvCentTFVal_20161118.zip`, en sepe.es) reproduce
la batería de pruebas de la autovalidación:

```
java -Djavax.net.ssl.trustStoreType=jks \
     -Djavax.net.ssl.trustStore=/ruta/truststore.jks \
     -Djavax.net.ssl.trustStorePassword=<pass> \
     -jar ProvCentTFVal.jar \
     https://<tu-moodle>/local/sepe/endpoint.php '<password>' \
     pruebaDatosDelCentro_20140619.xml
```

- El truststore JKS debe contener la CA del certificado del servidor
  (`keytool -importcert`). Con certificado público válido no es necesario.
- La prueba «datos identificativos correctos» compara la respuesta de
  `obtenerDatosCentro` con los `DATOS_IDENTIFICATIVOS` del XML de prueba:
  configura esos valores en los ajustes del plugin antes de ejecutar el kit
  (y restaura después los datos reales del centro).
- El kit borra al final las acciones que crea, por lo que puede re-ejecutarse.
  Si una ejecución se interrumpe a medias, limpia los datos con
  `php local/sepe/cli/reset_validation_data.php --run` o el botón «Reiniciar
  datos de validación» de la página de administración.
- Funciona con cualquier JRE ≥ 6 (probado con Temurin 26). Si tu servidor no
  acepta peticiones chunked (ver Requisitos), puedes desactivar
  `Transfer-Encoding: chunked` en `config/repository/axis2.xml` del kit para
  las pruebas locales, pero corrige el servidor antes de la autovalidación
  real.

Notas sobre los artefactos oficiales: el XSD del kit
(`ACCIONES_INICIADAS_DEL_CENTRO_V11.xsd`) contiene un error — el elemento
`FORMACION_MODALIDAD_TELEFORMACION` está declarado con un espacio final en el
nombre — que impide validar incluso el XML de prueba del propio SEPE. La
copia embebida en `xsd/` corrige ese espacio y se usa en los tests para
validar el XML generado.

## Privacidad (GDPR)

El plugin almacena documentos de identidad de tutores y participantes
recibidos del SEPE, y las peticiones/respuestas SOAP en un log con retención
configurable. Estos datos se conservan por obligación legal (seguimiento FPE,
Orden TMS/369/2019; el órgano de seguimiento puede consultarlos hasta 90 días
después de finalizar la acción). El proveedor de privacidad exporta y
desvincula los registros asociados a usuarios de Moodle; los datos de
terceros no vinculados quedan fuera del ciclo GDPR por usuario en virtud de
dicha obligación.

## Licencia

GPL v3 o posterior. © 2026 Studio LXD.
