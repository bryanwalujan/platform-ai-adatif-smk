<?php

namespace Tests\Feature;

use App\Models\BktParameter;
use App\Models\Discussion;
use App\Models\Material;
use App\Models\PblProject;
use App\Models\Quiz;
use App\Models\School;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\BayesianKnowledgeTracingService;
use App\Support\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SchoolIsolationTest extends TestCase
{
    use RefreshDatabase;

    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        Mail::fake();
        $this->a = $this->school('Sekolah A', 'SEKOLAHA');
        $this->b = $this->school('Sekolah B', 'SEKOLAHB');
    }

    private function school(string $name, string $code): array
    {
        $school = School::create(['name' => $name, 'code' => $code]);

        return app(SchoolContext::class)->run($school->id, function () use ($school, $code) {
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
            $teacher = User::factory()->create(['role' => 'guru', 'status' => 'active']);
            $student = User::factory()->create(['role' => 'siswa', 'status' => 'active', 'name' => 'Siswa '.$code]);
            $subject = Subject::create(['name' => 'Matematika '.$code, 'join_code' => $code, 'created_by' => $teacher->id]);
            $subject->teachers()->attach($teacher);
            $subject->students()->attach($student, ['enrollment_type' => 'assigned', 'enrolled_at' => now()]);
            $topic = Topic::create(['subject_id' => $subject->id, 'title' => 'Aljabar '.$code, 'order' => 1]);
            $material = Material::create(['topic_id' => $topic->id, 'title' => 'Materi '.$code, 'content' => 'Isi', 'file_path' => 'materials/'.$code.'.txt', 'file_name' => 'bahan.txt']);
            Storage::disk('public')->put($material->file_path, 'Berkas '.$code);
            $quiz = Quiz::create(['topic_id' => $topic->id, 'title' => 'Kuis '.$code]);
            $project = PblProject::create(['user_id' => $student->id, 'subject_id' => $subject->id, 'topic_id' => $topic->id, 'title' => 'Proyek '.$code, 'description' => 'Jawaban', 'level' => 'Dasar', 'status' => 'submitted']);
            $discussion = Discussion::create(['user_id' => $student->id, 'topic_id' => $topic->id, 'title' => 'Diskusi '.$code, 'body' => 'Pertanyaan']);

            return compact('school', 'admin', 'teacher', 'student', 'subject', 'topic', 'material', 'quiz', 'project', 'discussion');
        });
    }

    public function test_admin_lists_counts_and_mutations_are_school_scoped(): void
    {
        $this->actingAs($this->a['admin']);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('total_siswa', 1)->assertJsonPath('total_subjects', 1);
        $this->getJson('/api/admin/users')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/admin/subjects')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $this->a['subject']->id);
        $this->getJson('/api/admin/users/'.$this->b['student']->id)->assertNotFound();
        $this->putJson('/api/admin/users/'.$this->b['student']->id.'/status', ['status' => 'rejected'])->assertNotFound();
        $this->postJson('/api/admin/teachers/'.$this->b['teacher']->id.'/approve')->assertNotFound();
        $this->get('/admin/subjects/'.$this->b['subject']->id)->assertNotFound();
        $this->get('/admin')->assertOk()->assertSee('Sekolah A')->assertDontSee('Sekolah B');
    }

    public function test_teacher_search_enrollment_content_and_grading_cannot_cross_school(): void
    {
        $this->actingAs($this->a['teacher']);
        $this->getJson('/api/guru/students/search?q=Siswa')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $this->a['student']->id);
        $this->postJson('/api/guru/subjects/'.$this->a['subject']->id.'/students', ['user_id' => $this->b['student']->id])->assertUnprocessable();
        $this->postJson('/api/guru/content/materials', ['topic_id' => $this->b['topic']->id, 'title' => 'Salah sekolah', 'content' => 'Isi'])->assertUnprocessable();
        $this->getJson('/api/guru/pending-projects')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $this->a['project']->id);
        $this->postJson('/api/guru/projects/'.$this->b['project']->id.'/grade', [
            'feedback' => 'Nilai', 'rubric_scores' => ['kreativitas' => 80, 'teknis' => 80, 'konsep' => 80, 'presentasi' => 80],
        ])->assertNotFound();
        $this->get('/guru/projects/'.$this->b['project']->id.'/grade')->assertNotFound();
        $this->getJson('/api/guru/export/students')->assertOk()->assertDontSee($this->b['student']->email);
    }

    public function test_student_cannot_read_or_submit_foreign_resources(): void
    {
        $this->actingAs($this->a['student']);
        foreach ([
            '/api/topics/'.$this->b['topic']->id,
            '/api/materials/'.$this->b['material']->id,
            '/api/quizzes/'.$this->b['quiz']->id.'/questions',
            '/api/discussions/'.$this->b['discussion']->id,
            '/api/pbl-projects/'.$this->b['project']->id,
        ] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        $this->postJson('/api/subjects/join', ['join_code' => $this->b['subject']->join_code])->assertNotFound();
        $this->postJson('/api/pbl-projects', ['title' => 'Salah sekolah', 'description' => 'Isi', 'level' => 'Dasar', 'topic_id' => $this->b['topic']->id])->assertUnprocessable();
        $this->postJson('/api/interaction-logs', ['topic_id' => $this->a['topic']->id, 'material_id' => $this->b['material']->id, 'action' => 'open_material'])->assertUnprocessable();
        $this->getJson('/api/recommendations?subject_id='.$this->b['subject']->id)->assertForbidden();
        $this->getJson('/api/topics')->assertOk()->assertJsonCount(1);
    }

    public function test_school_in_body_and_headers_cannot_override_identity(): void
    {
        $this->actingAs($this->a['teacher']);
        $this->withHeader('X-School-ID', (string) $this->b['school']->id)->postJson('/api/guru/subjects', [
            'name' => 'IPA', 'school_id' => $this->b['school']->id,
        ])->assertCreated()->assertJsonPath('subject.school_id', $this->a['school']->id);
        $this->getJson('/api/me')->assertJsonPath('school.id', $this->a['school']->id);
        $this->putJson('/api/profile', ['name' => 'Guru A', 'school_id' => $this->b['school']->id])->assertOk()->assertJsonPath('user.school_id', $this->a['school']->id);
    }

    public function test_registration_requires_valid_school_and_keeps_email_unique_globally(): void
    {
        $data = ['name' => 'Baru', 'email' => 'baru@example.test', 'password' => 'password123', 'role' => 'siswa'];
        $this->postJson('/api/register', $data)->assertUnprocessable()->assertJsonValidationErrors('school_code');
        $this->postJson('/api/register', $data + ['school_code' => 'SALAH'])->assertUnprocessable();
        $this->postJson('/api/register', $data + ['school_code' => 'sekolaha', 'school_id' => $this->b['school']->id])->assertCreated();
        $this->assertDatabaseHas('users', ['email' => $data['email'], 'school_id' => $this->a['school']->id]);
        $this->postJson('/api/register', $data + ['school_code' => 'SEKOLAHB'])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_inactive_school_blocks_login_and_existing_tokens(): void
    {
        $this->a['school']->update(['is_active' => false]);
        $this->postJson('/api/login', ['email' => $this->a['student']->email, 'password' => 'password'])->assertForbidden();
        $this->actingAs($this->a['student'])->getJson('/api/me')->assertForbidden();
        $this->postJson('/api/register', ['name' => 'Baru', 'email' => 'baru@example.test', 'password' => 'password123', 'school_code' => 'SEKOLAHA'])->assertUnprocessable();
    }

    public function test_files_require_authentication_and_matching_school_even_when_url_is_known(): void
    {
        $url = '/api/files/'.$this->a['material']->file_path;
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->b['admin'])->getJson($url)->assertNotFound();
        $this->actingAs($this->a['student'])->get($url)->assertOk();
        $this->actingAs($this->a['teacher'])->get('/school-files/'.$this->a['material']->file_path)->assertOk();
        $this->actingAs($this->b['teacher'])->get('/school-files/'.$this->a['material']->file_path)->assertNotFound();
    }

    public function test_multimedia_attachment_isolation_uses_route_binding_inside_school_context(): void
    {
        $response = $this->actingAs($this->a['student'])->postJson('/api/pbl-projects', [
            'title' => 'Bukti', 'level' => 'Dasar', 'topic_id' => $this->a['topic']->id,
            'files' => [UploadedFile::fake()->image('grafik.jpg')],
        ])->assertCreated();
        $url = $response->json('project.attachments.0.url');
        $this->get($url)->assertOk();
        $this->actingAs($this->b['admin'])->getJson($url)->assertNotFound();
        $this->actingAs($this->a['teacher'])->get($url)->assertOk();
    }

    public function test_school_settings_cannot_edit_another_school_and_codes_can_rotate(): void
    {
        $this->actingAs($this->a['admin']);
        $this->putJson('/api/admin/school', ['name' => 'Nama Baru A', 'id' => $this->b['school']->id])->assertOk()->assertJsonPath('id', $this->a['school']->id);
        $this->assertDatabaseHas('schools', ['id' => $this->b['school']->id, 'name' => 'Sekolah B']);
        $newCode = $this->postJson('/api/admin/school/regenerate-code')->assertOk()->json('code');
        $this->assertNotSame('SEKOLAHA', $newCode);
        $this->actingAs($this->a['teacher'])->putJson('/api/admin/school', ['name' => 'Tidak boleh'])->assertForbidden();
    }

    public function test_model_refuses_cross_school_relations_even_outside_http(): void
    {
        $this->expectException(ValidationException::class);
        PblProject::create(['user_id' => $this->a['student']->id, 'subject_id' => $this->b['subject']->id, 'title' => 'Invalid', 'level' => 'Dasar']);
    }

    public function test_school_context_is_restored_between_requests_and_exceptions(): void
    {
        $this->actingAs($this->a['admin'])->getJson('/api/admin/subjects')->assertJsonPath('0.id', $this->a['subject']->id);
        $this->assertNull(app(SchoolContext::class)->id);
        $this->actingAs($this->b['admin'])->getJson('/api/admin/subjects')->assertJsonPath('0.id', $this->b['subject']->id);
        $this->getJson('/api/admin/users/'.$this->a['student']->id)->assertNotFound();
        $this->assertNull(app(SchoolContext::class)->id);
    }

    public function test_bkt_fallback_is_per_school(): void
    {
        app(SchoolContext::class)->run($this->b['school']->id, fn () => BktParameter::create(['p_l0' => 0.9, 'p_t' => 0.1, 'p_s' => 0.1, 'p_g' => 0.1]));
        $parameters = app(SchoolContext::class)->run($this->a['school']->id, fn () => app(BayesianKnowledgeTracingService::class)->getParameters(null));
        $this->assertEquals(0.3, $parameters['p_l0']);
    }
}
