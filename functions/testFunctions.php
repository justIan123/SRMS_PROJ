<?php
// Helpers for the test maker: teachers create tests for their own subjects
// in the current school year. Put this file in FUNCTIONS_PATH.
//
// Plain PDO: every function runs its own prepare() / execute().
// The write functions use a transaction and re-throw after the rollback,
// so the page or handler that calls them decides what to show.

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

/** Delete a test together with its questions and choices. */
function DeleteTest(PDO $pdo, int $testId): void
{
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM choices WHERE question_id IN (SELECT id FROM questions WHERE test_id = :test)");
        $stmt->execute([':test' => $testId]);

        $stmt = $pdo->prepare("DELETE FROM questions WHERE test_id = :test");
        $stmt->execute([':test' => $testId]);

        $stmt = $pdo->prepare("DELETE FROM tests WHERE id = :test");
        $stmt->execute([':test' => $testId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Delete one question together with its choices. */
function DeleteQuestion(PDO $pdo, int $questionId): void
{
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM choices WHERE question_id = :question");
        $stmt->execute([':question' => $questionId]);

        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = :question");
        $stmt->execute([':question' => $questionId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
    try {
        $pdo->beginTransaction();

        if ($questionId === null) {
            // Postgres: lastInsertId() needs a sequence name, so the INSERT returns the id itself
            $stmt = $pdo->prepare("INSERT INTO questions (test_id, question_text, points)
                                   VALUES (:test, :text, :points)
                                   RETURNING id");
            $stmt->execute([':test' => $testId, ':text' => $text, ':points' => $points]);
            $questionId = (int)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("UPDATE questions SET question_text = :text, points = :points WHERE id = :question");
            $stmt->execute([':text' => $text, ':points' => $points, ':question' => $questionId]);

            // the choices are re-added below
            $stmt = $pdo->prepare("DELETE FROM choices WHERE question_id = :question");
            $stmt->execute([':question' => $questionId]);
        }

        $stmt = $pdo->prepare("INSERT INTO choices (question_id, choice_text, is_correct)
                               VALUES (:question, :choice, :correct)");
        foreach ($choices as $i => $choice) {
            $stmt->execute([
                ':question' => $questionId,
                ':choice'   => $choice,
                ':correct'  => $i === $correct ? 1 : 0,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
