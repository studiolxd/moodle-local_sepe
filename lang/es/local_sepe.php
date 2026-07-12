<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish language strings for local_sepe.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accionessection'] = 'Acciones formativas recibidas';
$string['centreheading'] = 'Datos identificativos del centro';
$string['centreheading_desc'] = 'Datos del centro devueltos por obtenerDatosCentro hasta que el SEPE registre el centro mediante crearCentro. Usa los valores de tu acreditación/inscripción en el SEPE.';
$string['centroemail'] = 'Email del centro';
$string['centroemail_desc'] = 'Dirección de correo de contacto (EMAIL).';
$string['centronombre'] = 'Nombre del centro';
$string['centronombre_desc'] = 'Nombre del centro de formación acreditado (NOMBRE_CENTRO).';
$string['centroorigen'] = 'Código de origen del centro';
$string['centroorigen_desc'] = 'Código ORIGEN_CENTRO, dos caracteres que identifican a la administración que lo asigna (por ejemplo 20 para el SEPE).';
$string['centrocodigo'] = 'Código del centro';
$string['centrocodigo_desc'] = 'CODIGO_CENTRO asignado por la autoridad laboral.';
$string['centrosection'] = 'Datos del centro registrados por el SEPE';
$string['centrotelefono'] = 'Teléfono del centro';
$string['centrotelefono_desc'] = 'Teléfono de contacto (TELEFONO).';
$string['centrourlplataforma'] = 'URL de la plataforma';
$string['centrourlplataforma_desc'] = 'URL pública de la plataforma de teleformación (URL_PLATAFORMA).';
$string['centrourlseguimiento'] = 'URL del servicio de seguimiento';
$string['centrourlseguimiento_desc'] = 'URL pública HTTPS de este servicio web SOAP de seguimiento (URL_SEGUIMIENTO).';
$string['colcourse'] = 'Curso de Moodle';
$string['coldenominacion'] = 'Denominación';
$string['colespecialidad'] = 'Especialidad principal';
$string['colfechas'] = 'Fechas';
$string['colip'] = 'Dirección IP';
$string['coloperation'] = 'Operación';
$string['colreturncode'] = 'Código de retorno';
$string['colsituacion'] = 'Situación';
$string['coltime'] = 'Fecha y hora';
$string['errornocourse'] = 'El curso seleccionado no existe.';
$string['eventaccioncreated'] = 'Acción formativa SEPE creada';
$string['eventacciondeleted'] = 'Acción formativa SEPE eliminada';
$string['eventcentroupdated'] = 'Datos del centro SEPE actualizados';
$string['logretentiondays'] = 'Retención de logs (días)';
$string['logretentiondays_desc'] = 'Las entradas del log del servicio web anteriores a este número de días se eliminan con la tarea programada de limpieza. Pon 0 para conservarlas indefinidamente.';
$string['logssection'] = 'Últimas peticiones al servicio web';
$string['managepage'] = 'Acciones formativas SEPE';
$string['mapsaved'] = 'Vinculación con el curso guardada.';
$string['niffield'] = 'Campo de usuario con el NIF';
$string['niffield_desc'] = 'Campo del usuario que contiene el documento de identidad (NIF/NIE) con el que se informa de los participantes al SEPE. Usa "idnumber" para el campo estándar de número de ID, o el nombre corto de un campo de perfil personalizado con el prefijo "profile_", por ejemplo "profile_nif".';
$string['noacciones'] = 'Todavía no se ha recibido ninguna acción formativa.';
$string['nocentro'] = 'El SEPE aún no ha registrado el centro. Se devolverán los datos por defecto configurados en los ajustes del plugin.';
$string['nologs'] = 'Todavía no se ha registrado ninguna petición al servicio web.';
$string['notmapped'] = 'Sin vincular';
$string['pluginname'] = 'Servicio de seguimiento de teleformación del SEPE';
$string['privacy:metadata:local_sepe_accion'] = 'Acciones formativas notificadas por el SEPE. El XML original puede incluir datos personales de tutores y participantes de la acción.';
$string['privacy:metadata:local_sepe_accion:rawxml'] = 'XML literal de la acción formativa tal como se recibió del SEPE.';
$string['privacy:metadata:local_sepe_participante'] = 'Participantes de las acciones formativas del SEPE, identificados por su documento de identidad.';
$string['privacy:metadata:local_sepe_participante:letra_nif'] = 'Letra de control del documento de identidad.';
$string['privacy:metadata:local_sepe_participante:num_documento'] = 'Número del documento de identidad (NIF/NIE).';
$string['privacy:metadata:local_sepe_participante:tipo_documento'] = 'Tipo de documento de identidad.';
$string['privacy:metadata:local_sepe_participante:userid'] = 'Usuario de Moodle vinculado al participante.';
$string['privacy:metadata:local_sepe_tutor'] = 'Tutores de las acciones formativas del SEPE, identificados por su documento de identidad.';
$string['privacy:metadata:local_sepe_tutor:acreditacion'] = 'Acreditación académica del tutor.';
$string['privacy:metadata:local_sepe_tutor:letra_nif'] = 'Letra de control del documento de identidad.';
$string['privacy:metadata:local_sepe_tutor:num_documento'] = 'Número del documento de identidad (NIF/NIE).';
$string['privacy:metadata:local_sepe_tutor:tipo_documento'] = 'Tipo de documento de identidad.';
$string['privacy:metadata:local_sepe_tutor:userid'] = 'Usuario de Moodle vinculado al tutor.';
$string['privacy:metadata:local_sepe_wslog'] = 'Registro de peticiones recibidas del SEPE en el servicio web de seguimiento. Los cuerpos de las peticiones pueden incluir datos personales de tutores y participantes.';
$string['privacy:metadata:local_sepe_wslog:ip'] = 'Dirección IP de origen de la petición.';
$string['privacy:metadata:local_sepe_wslog:requestxml'] = 'Petición SOAP recibida, literal.';
$string['privacy:metadata:local_sepe_wslog:responsexml'] = 'Respuesta SOAP devuelta, literal.';
$string['resetdata'] = 'Reiniciar datos de validación';
$string['resetdataconfirm'] = 'Se eliminarán el registro del centro y todas las acciones formativas recibidas del SEPE, incluidos tutores y participantes. Está pensado para usarse entre ejecuciones del kit de autoevaluación del SEPE. ¿Continuar?';
$string['resetdone'] = 'Datos del centro y de las acciones formativas eliminados.';
$string['sepe:manage'] = 'Gestionar el servicio web de seguimiento del SEPE';
$string['sepe:viewlogs'] = 'Ver los logs del servicio web de seguimiento del SEPE';
$string['servicedisabled'] = 'Modo servicio no disponible';
$string['servicedisabled_desc'] = 'Cuando está activo, todas las operaciones responden con el código de retorno -2 (servicio no disponible). Actívalo solo durante mantenimientos.';
$string['serviceheading'] = 'Servicio web de seguimiento';
$string['serviceheading_desc'] = 'Endpoint SOAP que implementa el servicio de seguimiento de teleformación del SEPE (Orden TMS/369/2019, Anexo V). URL del endpoint: {$a}';
$string['settingspage'] = 'Ajustes del servicio web SEPE';
$string['taskcleanuplogs'] = 'Limpiar logs del servicio web SEPE';
$string['taskrefreshtracking'] = 'Actualizar datos de seguimiento SEPE de los cursos vinculados';
$string['wspassword'] = 'Contraseña del servicio web';
$string['wspassword_desc'] = 'Contraseña que el SEPE usa en la cabecera WS-Security para autenticarse contra este servicio (el usuario es siempre "SEPE"). Solo se guarda un hash irreversible. Déjala vacía para mantener la contraseña actual.';
