<?php

namespace App\Support;

use Closure;

/** Request/job-local school identity; never accepted from a request header or body. */
class SchoolContext
{
    public ?int $id = null;

    public const TABLES = [
        'users', 'subjects', 'topics', 'materials', 'quizzes', 'quiz_questions',
        'pbl_projects', 'lesson_plans', 'student_topic_mastery', 'learning_logs',
        'test_results', 'quiz_attempts', 'interaction_logs', 'discussions',
        'discussion_replies', 'app_notifications', 'bkt_parameters',
    ];

    public function run(int $schoolId, Closure $callback): mixed
    {
        $previous = $this->id;
        $this->id = $schoolId;
        try {
            return $callback();
        } finally {
            $this->id = $previous;
        }
    }
}
