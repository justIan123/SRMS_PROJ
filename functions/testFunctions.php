<?php
// Helpers for the test maker: teachers create tests for their own subjects
// in the current school year. Put this file in FUNCTIONS_PATH.

/**
 * The logged-in user's id, or 0 when nobody is logged in.
 * Assumes the login stores it as $_SESSION['user_id'] - change it here if yours differs.
 */
function CurrentUserId(): int
{
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
    $stmt->execute(normalizeParams([':teacher' => $teacherId, ':year' => $yearId, ':active' => 1]));
    return $stmt->fetchAll();
}

/** The test row, but only if it belongs to this teacher and this school year. Otherwise null. */
function FindMyTest(PDO $pdo, int $testId, int $teacherId, int $yearId): ?array
{
    $test = FindById($pdo, 'tests', $testId);

    if (!$test || (int)$test['teacher_id'] !== $teacherId || (int)$test['school_year_id'] !== $yearId) {
        return null;
    }
    return $test;
}

/** Delete a test together with its questions and choices. */
function DeleteTest(PDO $pdo, int $testId): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM choices WHERE question_id IN (SELECT id FROM questions WHERE test_id = :test)");
        $stmt->execute([':test' => $testId]);
        DeleteRecord($pdo, 'questions', $testId, 'test_id');
        DeleteRecord($pdo, 'tests', $testId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Delete one question together with its choices. */
function DeleteQuestion(PDO $pdo, int $questionId): void
{
    $pdo->beginTransaction();
    try {
        DeleteRecord($pdo, 'choices', $questionId, 'question_id');
        DeleteRecord($pdo, 'questions', $questionId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Read and check the question form.
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

/** Insert a question (or update it when $questionId is given) together with its choices. */
function SaveQuestion(PDO $pdo, int $testId, ?int $questionId, string $text, int $points, array $choices, int $correct): void
{
    $pdo->beginTransaction();
    try {
        if ($questionId === null) {
            $questionId = (int)InsertRecord($pdo, 'questions', [
                'test_id'       => $testId,
                'question_text' => $text,
                'points'        => $points,
            ]);
        } else {
            UpdateRecord($pdo, 'questions', $questionId, ['question_text' => $text, 'points' => $points]);
            DeleteRecord($pdo, 'choices', $questionId, 'question_id');   // choices are re-added below
        }

        foreach ($choices as $i => $choice) {
            // 4th argument null = we don't need the new choice id back
            InsertRecord($pdo, 'choices', [
                'question_id' => $questionId,
                'choice_text' => $choice,
                'is_correct'  => $i === $correct ? 1 : 0,
            ], null);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}