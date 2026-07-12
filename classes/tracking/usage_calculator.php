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

namespace local_sepe\tracking;

/**
 * Computes the USO and SEGUIMIENTO_EVALUACION blocks from course activity.
 *
 * Connection data comes from the standard log store. Log entries of each
 * participant are grouped into sessions using a one-hour inactivity gap
 * (the same criterion as block_dedication); each session is assigned in
 * full to the SEPE time band where it started — mañana 7:00-15:00,
 * tarde 15:00-23:00, noche 23:00-7:00 — and band totals are reported in
 * whole hours with the rounding rule of the data model (fractions of
 * up to 0.5 round down, above 0.5 round up).
 *
 * @package    local_sepe
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class usage_calculator {
    /** @var int Inactivity gap that splits two sessions, in seconds. */
    public const SESSION_GAP = HOURSECS;

    /** @var string[] Band keys in report order. */
    public const BANDS = ['manana', 'tarde', 'noche'];

    /**
     * Constructor.
     *
     * @param int $courseid course being tracked
     * @param int[] $userids participant user ids
     */
    public function __construct(
        /** @var int Course id. */
        private readonly int $courseid,
        /** @var int[] Participant user ids. */
        private readonly array $userids,
    ) {
    }

    /**
     * Usage figures per SEPE time band.
     *
     * @return array<string, array{participantes: int, accesos: int, duracion: int}>
     *     keyed by band ('manana', 'tarde', 'noche')
     */
    public function uso(): array {
        global $DB;

        $result = [];
        foreach (self::BANDS as $band) {
            $result[$band] = ['participantes' => [], 'accesos' => 0, 'seconds' => 0];
        }

        if ($this->userids !== []) {
            [$insql, $params] = $DB->get_in_or_equal($this->userids, SQL_PARAMS_NAMED);
            $params['courseid'] = $this->courseid;
            $rs = $DB->get_recordset_select(
                'logstore_standard_log',
                "courseid = :courseid AND userid $insql",
                $params,
                'userid ASC, timecreated ASC',
                'id, userid, timecreated'
            );

            $user = null;
            $start = null;
            $last = null;
            $close = function () use (&$user, &$start, &$last, &$result) {
                if ($start === null) {
                    return;
                }
                $band = $this->band($start);
                $result[$band]['participantes'][$user] = true;
                $result[$band]['accesos']++;
                $result[$band]['seconds'] += $last - $start;
            };

            foreach ($rs as $entry) {
                if ($user !== $entry->userid || $entry->timecreated - $last > self::SESSION_GAP) {
                    $close();
                    $user = $entry->userid;
                    $start = $entry->timecreated;
                }
                $last = $entry->timecreated;
            }
            $close();
            $rs->close();
        }

        foreach (self::BANDS as $band) {
            $result[$band] = [
                'participantes' => count($result[$band]['participantes']),
                'accesos' => $result[$band]['accesos'],
                'duracion' => self::round_hours($result[$band]['seconds']),
            ];
        }
        return $result;
    }

    /**
     * SEGUIMIENTO_EVALUACION figures for the course.
     *
     * Learning activities are course modules completed by at least one
     * participant; evaluation activities are the gradable activities in
     * the gradebook; attempts add quiz attempts and assignment
     * submissions of the participants.
     *
     * @return array{participantes: int, aprendizaje: int, intentos: int, evaluacion: int}
     */
    public function seguimiento_evaluacion(): array {
        global $DB;

        if ($this->userids === []) {
            return ['participantes' => 0, 'aprendizaje' => 0, 'intentos' => 0, 'evaluacion' => 0];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($this->userids, SQL_PARAMS_NAMED);
        $params = array_merge(['courseid' => $this->courseid], $inparams);

        $completionusers = $DB->get_fieldset_sql(
            "SELECT DISTINCT cmc.userid
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cm.course = :courseid AND cmc.completionstate > 0 AND cmc.userid $insql",
            $params
        );
        $gradeusers = $DB->get_fieldset_sql(
            "SELECT DISTINCT gg.userid
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gi.courseid = :courseid AND gi.itemtype = 'mod'
                    AND gg.finalgrade IS NOT NULL AND gg.userid $insql",
            $params
        );
        $participantes = count(array_unique(array_merge($completionusers, $gradeusers)));

        $aprendizaje = (int)$DB->get_field_sql(
            "SELECT COUNT(DISTINCT cm.id)
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cm.course = :courseid AND cmc.completionstate > 0 AND cmc.userid $insql",
            $params
        );

        $intentos = (int)$DB->get_field_sql(
            "SELECT COUNT(qa.id)
               FROM {quiz_attempts} qa
               JOIN {quiz} q ON q.id = qa.quiz
              WHERE q.course = :courseid AND qa.preview = 0 AND qa.userid $insql",
            $params
        );
        $intentos += (int)$DB->get_field_sql(
            "SELECT COUNT(s.id)
               FROM {assign_submission} s
               JOIN {assign} a ON a.id = s.assignment
              WHERE a.course = :courseid AND s.status = 'submitted' AND s.userid $insql",
            $params
        );

        $evaluacion = (int)$DB->count_records_select(
            'grade_items',
            "courseid = ? AND itemtype = 'mod'",
            [$this->courseid]
        );

        return [
            'participantes' => $participantes,
            'aprendizaje' => $aprendizaje,
            'intentos' => $intentos,
            'evaluacion' => $evaluacion,
        ];
    }

    /**
     * Rounds seconds to whole hours with the SEPE rule.
     *
     * Fractions of up to 0.5 hours round down, above 0.5 round up.
     *
     * @param int $seconds duration in seconds
     * @return int duration in whole hours
     */
    public static function round_hours(int $seconds): int {
        $hours = intdiv($seconds, HOURSECS);
        $fraction = ($seconds % HOURSECS) / HOURSECS;
        return $fraction > 0.5 ? $hours + 1 : $hours;
    }

    /**
     * SEPE time band of a timestamp, using the server timezone.
     *
     * @param int $timestamp epoch seconds
     * @return string 'manana' (7-15h), 'tarde' (15-23h) or 'noche' (23-7h)
     */
    private function band(int $timestamp): string {
        $date = new \DateTime('@' . $timestamp);
        $date->setTimezone(\core_date::get_server_timezone_object());
        $hour = (int)$date->format('G');
        if ($hour >= 7 && $hour < 15) {
            return 'manana';
        }
        if ($hour >= 15 && $hour < 23) {
            return 'tarde';
        }
        return 'noche';
    }
}
