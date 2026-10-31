<?php
// Shared by manage_grades.php, manage_classrecord.php and their handlers

// The score columns of the grades table, in the order the pages show them.
// These keys are the only thing that can ever be used as a column name in the SQL.
const GRADE_COLUMNS = [
    'pt1'        => 'Performance task 1',
    'pt2'        => 'Performance task 2',
    'pt3'        => 'Performance task 3',
    'recitation' => 'Recitation',
    'st1'        => 'Summative test 1',
    'st2'        => 'Summative test 2',
    'te1'        => 'Term exam 1',
    'te2'        => 'Term exam 2',
    'te3'        => 'Term exam 3',
];

// Highest score that can be typed in (keep the same as the CHECK in db_grades.sql)
const GRADE_MAX = 100;

// How the weighted grade is made. Each group is averaged first, then the group averages
// are combined by weight.
//
// !!! THE WEIGHTS BELOW ARE PLACEHOLDERS. Put your school's real percentages here. !!!
// They do not have to add up to 100; the result is divided by the weights that are in use.
const GRADE_GROUPS = [
    'Performance tasks' => ['weight' => 30, 'columns' => ['pt1', 'pt2', 'pt3']],
    'Recitation'        => ['weight' => 10, 'columns' => ['recitation']],
    'Summative tests'   => ['weight' => 20, 'columns' => ['st1', 'st2']],
    'Term exams'        => ['weight' => 40, 'columns' => ['te1', 'te2', 'te3']],
];

/**
 * The subjects of the current school year that this user may grade.
 * An admin gets every active subject, anyone else only the subjects in teacher_subjects.
 * Returns [subject id => subject name].
 */
function GradableSubjects(PDO $pdo, int $yearId, int $userId): array
{
    $stmt = $pdo->prepare("SELECT 1
                           FROM user_roles ur
                           JOIN roles r ON r.role_id = ur.role
                           WHERE ur.user_id = :uid AND LOWER(r.role) = 'admin'
                           LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $isAdmin = (bool)$stmt->fetchColumn();

    $sql    = "SELECT s.id, s.subject_name
               FROM subjects s
               WHERE s.school_year_id = :year AND s.is_active = 1";
    $params = [':year' => $yearId];

    if (!$isAdmin) {
        $sql .= " AND EXISTS (SELECT 1 FROM teacher_subjects ts
                              WHERE ts.subject_id = s.id AND ts.teacher_id = :uid)";
        $params[':uid'] = $userId;
    }

    $stmt = $pdo->prepare($sql . " ORDER BY s.subject_name");
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * The extra SQL for a student search box, and its parameters. Returns [sql, params].
 *
 * The text is split into words and EVERY word has to match the first name, the last name or the
 * grade level, so "ian curt", "curt ian" and "curt, grade 7" all work. % and _ typed by the
 * user are matched as plain characters. $alias is the students table alias in the query
 * (always a fixed string from the calling code, never user input).
 */
function StudentSearchSql(string $search, string $alias = 's'): array
{
    $sql    = '';
    $params = [];

    $words = preg_split('/[\s,]+/', trim($search), -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($words, 0, 5) as $i => $word) {
        $like = '%' . addcslashes($word, '%_\\') . '%';
        // every placeholder is used once (PDO does not allow the same name twice)
        $sql .= " AND ($alias.fname ILIKE :sw{$i}a OR $alias.lname ILIKE :sw{$i}b OR $alias.grade_level ILIKE :sw{$i}c)";
        $params[":sw{$i}a"] = $like;
        $params[":sw{$i}b"] = $like;
        $params[":sw{$i}c"] = $like;
    }

    return [$sql, $params];
}

/**
 * The students of the current school year who are enrolled in at least one of the given subjects,
 * in the order the dropdown shows them. $search (optional) narrows the list, see StudentSearchSql().
 */
function GradableStudents(PDO $pdo, int $yearId, array $subjectIds, string $search = ''): array
{
    $subjectIds = array_map('intval', $subjectIds);   // integers only, so they are safe to put in the SQL
    if (!$subjectIds) {
        return [];
    }

    [$searchSql, $params] = StudentSearchSql($search);
    $params[':year'] = $yearId;

    $stmt = $pdo->prepare("SELECT s.id, s.fname, s.lname, s.grade_level
                           FROM students s
                           WHERE s.school_year_id = :year
                             AND EXISTS (SELECT 1 FROM enrollments e
                                         WHERE e.student_id = s.id
                                           AND e.subject_id IN (" . implode(', ', $subjectIds) . "))
                             $searchSql
                           ORDER BY s.lname, s.fname, s.id");
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** "85.00" -> "85", "70.50" -> "70.5", null -> "" (only trims after a decimal point) */
function FormatScore($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $s = (string)$value;
    if (strpos($s, '.') !== false) {
        $s = rtrim(rtrim($s, '0'), '.');
    }
    return $s;
}

/**
 * Weighted grade of one grades row (see GRADE_GROUPS), or null when no score is entered yet.
 * A group with no scores is left out, so the result is the standing so far, not a final mark.
 */
function GradeFinal(array $row): ?float
{
    $total   = 0.0;
    $weights = 0.0;

    foreach (GRADE_GROUPS as $group) {
        $sum   = 0.0;
        $count = 0;
        foreach ($group['columns'] as $col) {
            if (isset($row[$col]) && $row[$col] !== '') {
                $sum += (float)$row[$col];
                $count++;
            }
        }
        if ($count) {
            $total   += $group['weight'] * ($sum / $count);
            $weights += $group['weight'];
        }
    }

    return $weights > 0 ? $total / $weights : null;
}

/** "Performance tasks 30%, Recitation 10%, ..." for the note under the tables */
function GradeWeightsText(): string
{
    $parts = [];
    foreach (GRADE_GROUPS as $name => $group) {
        $parts[] = htmlspecialchars($name) . ' ' . $group['weight'] . '%';
    }
    return implode(', ', $parts);
}

/** The tabs on top of the grade pages. $active is 'mine', 'student' or 'subject'. */
function GradeNav(string $active): string
{
    $tabs = [
        'mine'    => ['manage_mystudents.php',  'My students'],
        'student' => ['manage_grades.php',      'By student'],
        'subject' => ['manage_classrecord.php', 'By subject (class record)'],
    ];

    $html = '<ul class="nav nav-pills mb-3">';
    foreach ($tabs as $key => [$file, $label]) {
        $html .= '<li class="nav-item"><a class="nav-link' . ($key === $active ? ' active' : '') . '"'
               . ' href="' . COMPONENTS_URL . $file . '">' . $label . '</a></li>';
    }
    return $html . '</ul>';
}

/**
 * Check and save typed scores. Used by both grade handlers.
 *
 * $items is keyed the way the page keys its inputs (subject id on the student page, student id
 * on the class record). Each item: ['student_id' => int, 'subject_id' => int, 'label' => text for
 * error messages, 'row' => the posted [column => text]].
 *
 * Every score is checked first and nothing is written unless all of them are fine. A student+subject
 * whose boxes are all empty has its row deleted. Call it inside the handler's transaction.
 *
 * Returns ['error' => message or null, 'typed' => [key => [column => what was typed]]].
 */
function SaveGradeItems(PDO $pdo, array $items, int $myId): array
{
    if (!$items) {
        return ['error' => 'There are no scores to save.', 'typed' => []];
    }

    // what was typed, for every box (so the page can show it again if something is wrong)
    $typed = [];
    foreach ($items as $key => $item) {
        foreach (GRADE_COLUMNS as $col => $label) {
            // The real form always sends every box (an empty box is ""). Anything else is a broken
            // request, and it must never be read as "cleared", because that would delete saved grades.
            if (!isset($item['row'][$col]) || !is_string($item['row'][$col])) {
                return ['error' => 'The form was incomplete. Reload the page and try again.', 'typed' => []];
            }
            $typed[$key][$col] = trim($item['row'][$col]);
        }
    }

    // check every box
    $values = [];   // key => [column => "85.00" or null]
    foreach ($typed as $key => $cells) {
        foreach ($cells as $col => $raw) {
            if ($raw === '') {
                $values[$key][$col] = null;   // empty = not graded
            } elseif (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > GRADE_MAX) {
                return [
                    'error' => htmlspecialchars($items[$key]['label'] . ' - ' . GRADE_COLUMNS[$col])
                             . ': enter a number from 0 to ' . GRADE_MAX . ', or leave it empty.',
                    'typed' => $typed,
                ];
            } else {
                $values[$key][$col] = number_format((float)$raw, 2, '.', '');
            }
        }
    }

    // write
    $cols   = array_keys(GRADE_COLUMNS);   // fixed list from this file, never user input
    $upsert = $pdo->prepare(
        "INSERT INTO grades (student_id, subject_id, " . implode(', ', $cols) . ", updated_by, updated_at)
         VALUES (:student_id, :subject_id, " . implode(', ', array_map(fn($c) => ':' . $c, $cols)) . ", :updated_by, now())
         ON CONFLICT (student_id, subject_id)
         DO UPDATE SET " . implode(', ', array_map(fn($c) => "$c = EXCLUDED.$c", $cols)) . ",
                       updated_by = EXCLUDED.updated_by,
                       updated_at = now()"
    );
    $remove = $pdo->prepare("DELETE FROM grades WHERE student_id = :student_id AND subject_id = :subject_id");

    foreach ($values as $key => $row) {
        $ids = [':student_id' => (int)$items[$key]['student_id'], ':subject_id' => (int)$items[$key]['subject_id']];

        if (count(array_filter($row, fn($v) => $v !== null)) === 0) {
            $remove->execute($ids);   // every box empty: no row is kept
        } else {
            $upsert->execute(
                $ids + [':updated_by' => $myId ?: null]
                     + array_combine(array_map(fn($c) => ':' . $c, array_keys($row)), $row)
            );
        }
    }

    return ['error' => null, 'typed' => $typed];
}