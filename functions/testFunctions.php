<?php
// Helpers for the test maker: teachers create tests for their own subjects
// in the current school year. Put this file in FUNCTIONS_PATH.
//
// Plain PDO: every function runs its own prepare() / execute().
// The writes (create / update / delete a test or a question) are no longer here:
// they live in testProcess.php and questionsProcess.php, inside their transactions.

/**
 * The logged-in user's id, or 0 when nobody is logged in.
 * Assumes the login stores it as $_SESSION['user_id'] - change it here if yours differs.
 */
function CurrentUserId(): int
{
    // $_SESSION is empty until the session is started, so start it if nobody did yet
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return (int)($_SESSION['user_id'] ?? 0);
}

/** true if this user has the 'teacher' role. */
function IsTeacher(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT 1
                           FROM user_roles ur
                           JOIN roles r ON r.role_id = ur.role
                           WHERE ur.user_id = :user AND LOWER(r.role) = 'teacher'
                           LIMIT 1");
    $stmt->execute([':user' => $userId]);
    return (bool)$stmt->fetchColumn();
}

/** The subjects (id, subject_name) a teacher teaches in one school year. */
function TeacherSubjects(PDO $pdo, int $teacherId, int $yearId): array
{
    // is_active is passed as a parameter (not typed as "= 1") so it works whether
    // the Postgres column is boolean or integer.
    $stmt = $pdo->prepare("SELECT s.id, s.subject_name
                           FROM teacher_subjects ts
                           JOIN subjects s ON s.id = ts.subject_id
                           WHERE ts.teacher_id = :teacher
                             AND s.school_year_id = :year
                             AND s.is_active = :active
                           ORDER BY s.subject_name");
    $stmt->execute([':teacher' => $teacherId, ':year' => $yearId, ':active' => 1]);
    return $stmt->fetchAll();
}

/** The test row, but only if it belongs to this teacher and this school year. Otherwise null. */
function FindMyTest(PDO $pdo, int $testId, int $teacherId, int $yearId): ?array
{
    $stmt = $pdo->prepare("SELECT *
                           FROM tests
                           WHERE id = :test AND teacher_id = :teacher AND school_year_id = :year
                           LIMIT 1");
    $stmt->execute([':test' => $testId, ':teacher' => $teacherId, ':year' => $yearId]);

    return $stmt->fetch() ?: null;
}

/**
 * Read and check the question form (no database here).
 * Returns [text, points, choices, correct, error]
 *  - choices : only the choices that were filled in
 *  - correct : position of the correct one inside $choices
 *  - error   : message to show, or null when everything is fine
 */
function ReadQuestionForm(array $post): array
{
    $text         = trim($post['question_text'] ?? '');
    $points       = max(1, min(100, (int)($post['points'] ?? 1)));
    $correctIndex = (int)($post['correct'] ?? -1);

    $choices = [];
    $correct = null;
    foreach ((array)($post['choice'] ?? []) as $i => $choice) {
        $choice = trim((string)$choice);
        if ($choice === '') {
            continue;
        }
        if ((int)$i === $correctIndex) {
            $correct = count($choices);
        }
        $choices[] = $choice;
    }

    $error = null;
    if ($text === '') {
        $error = 'Write the question first.';
    } elseif (count($choices) < 2) {
        $error = 'Add at least two choices.';
    } elseif ($correct === null) {
        $error = 'Mark which choice is correct (it cannot be an empty one).';
    }

    return [$text, $points, $choices, $correct, $error];
}
