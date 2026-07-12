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
 * English language strings for local_sepe.
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accionessection'] = 'Training actions received';
$string['centreheading'] = 'Centre identification data';
$string['centreheading_desc'] = 'Default centre data returned by obtenerDatosCentro until SEPE registers the centre through crearCentro. Use the values of your SEPE accreditation/registration.';
$string['centroemail'] = 'Centre email';
$string['centroemail_desc'] = 'Contact email address (EMAIL).';
$string['centronombre'] = 'Centre name';
$string['centronombre_desc'] = 'Name of the accredited training centre (NOMBRE_CENTRO).';
$string['centroorigen'] = 'Centre origin code';
$string['centroorigen_desc'] = 'ORIGEN_CENTRO code, two characters identifying the assigning authority (for example 20 for SEPE).';
$string['centrocodigo'] = 'Centre code';
$string['centrocodigo_desc'] = 'CODIGO_CENTRO assigned by the labour authority.';
$string['centrosection'] = 'Centre data registered by SEPE';
$string['centrotelefono'] = 'Centre phone';
$string['centrotelefono_desc'] = 'Contact phone number (TELEFONO).';
$string['centrourlplataforma'] = 'Platform URL';
$string['centrourlplataforma_desc'] = 'Public URL of the e-learning platform (URL_PLATAFORMA).';
$string['centrourlseguimiento'] = 'Tracking service URL';
$string['centrourlseguimiento_desc'] = 'Public HTTPS URL of this SOAP tracking web service (URL_SEGUIMIENTO).';
$string['colcourse'] = 'Moodle course';
$string['coldenominacion'] = 'Name';
$string['colespecialidad'] = 'Main speciality';
$string['colfechas'] = 'Dates';
$string['colip'] = 'IP address';
$string['coloperation'] = 'Operation';
$string['colreturncode'] = 'Return code';
$string['colsituacion'] = 'Status';
$string['coltime'] = 'Time';
$string['errornocourse'] = 'The selected course does not exist.';
$string['eventaccioncreated'] = 'SEPE training action created';
$string['eventacciondeleted'] = 'SEPE training action deleted';
$string['eventcentroupdated'] = 'SEPE centre data updated';
$string['logretentiondays'] = 'Log retention (days)';
$string['logretentiondays_desc'] = 'Web service log entries older than this number of days are deleted by the scheduled cleanup task. Set to 0 to keep logs forever.';
$string['logssection'] = 'Latest web service requests';
$string['managepage'] = 'SEPE training actions';
$string['mapsaved'] = 'Course mapping saved.';
$string['niffield'] = 'NIF user field';
$string['niffield_desc'] = 'User field holding the national identity document (NIF/NIE) used to report participants to SEPE. Use "idnumber" for the standard ID number field, or the short name of a custom profile field prefixed with "profile_", for example "profile_nif".';
$string['noacciones'] = 'No training actions have been received yet.';
$string['nocentro'] = 'SEPE has not registered the centre yet. The default centre data from the plugin settings will be returned.';
$string['nologs'] = 'No web service requests have been logged yet.';
$string['notmapped'] = 'Not mapped';
$string['pluginname'] = 'SEPE teletraining tracking service';
$string['privacy:metadata:local_sepe_accion'] = 'Training actions notified by SEPE. The raw XML may embed personal data of tutors and participants of the action.';
$string['privacy:metadata:local_sepe_accion:rawxml'] = 'Verbatim XML of the training action as received from SEPE.';
$string['privacy:metadata:local_sepe_participante'] = 'Participants of SEPE training actions, identified by their national identity document.';
$string['privacy:metadata:local_sepe_participante:letra_nif'] = 'Check letter of the identity document.';
$string['privacy:metadata:local_sepe_participante:num_documento'] = 'Number of the identity document (NIF/NIE).';
$string['privacy:metadata:local_sepe_participante:tipo_documento'] = 'Type of identity document.';
$string['privacy:metadata:local_sepe_participante:userid'] = 'Moodle user mapped to the participant.';
$string['privacy:metadata:local_sepe_tutor'] = 'Tutors of SEPE training actions, identified by their national identity document.';
$string['privacy:metadata:local_sepe_tutor:acreditacion'] = 'Academic accreditation of the tutor.';
$string['privacy:metadata:local_sepe_tutor:letra_nif'] = 'Check letter of the identity document.';
$string['privacy:metadata:local_sepe_tutor:num_documento'] = 'Number of the identity document (NIF/NIE).';
$string['privacy:metadata:local_sepe_tutor:tipo_documento'] = 'Type of identity document.';
$string['privacy:metadata:local_sepe_tutor:userid'] = 'Moodle user mapped to the tutor.';
$string['privacy:metadata:local_sepe_wslog'] = 'Log of requests received from SEPE on the tracking web service. Request bodies may embed personal data of tutors and participants.';
$string['privacy:metadata:local_sepe_wslog:ip'] = 'IP address the request came from.';
$string['privacy:metadata:local_sepe_wslog:requestxml'] = 'Raw SOAP request received.';
$string['privacy:metadata:local_sepe_wslog:responsexml'] = 'Raw SOAP response returned.';
$string['resetdata'] = 'Reset validation data';
$string['resetdataconfirm'] = 'This deletes the centre registration and every training action received from SEPE, including tutors and participants. It is meant to be used between runs of the SEPE self-evaluation kit. Continue?';
$string['resetdone'] = 'Centre and training action data deleted.';
$string['sepe:manage'] = 'Manage the SEPE tracking web service';
$string['sepe:viewlogs'] = 'View SEPE tracking web service logs';
$string['servicedisabled'] = 'Service unavailable mode';
$string['servicedisabled_desc'] = 'When enabled, every operation answers with return code -2 (service unavailable). Enable only during maintenance.';
$string['serviceheading'] = 'Tracking web service';
$string['serviceheading_desc'] = 'SOAP endpoint implementing the SEPE teletraining tracking service (Orden TMS/369/2019, Annex V). Endpoint URL: {$a}';
$string['settingspage'] = 'SEPE web service settings';
$string['taskcleanuplogs'] = 'Clean up SEPE web service logs';
$string['taskrefreshtracking'] = 'Refresh SEPE tracking data of mapped courses';
$string['wspassword'] = 'Web service password';
$string['wspassword_desc'] = 'Password that SEPE uses in the WS-Security header to authenticate against this service (username is always "SEPE"). Only a one-way hash is stored. Leave empty to keep the current password.';
