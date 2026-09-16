<?php

namespace Tests\Feature;

use App\Models\PblProject;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PblMultimediaTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $teacher;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->teacher = User::factory()->create(['role' => 'guru', 'status' => 'active']);
        $this->student = User::factory()->create(['role' => 'siswa']);
        $this->subject = Subject::create(['name' => 'Animasi multimedia', 'join_code' => 'MEDIA1', 'created_by' => $this->teacher->id]);
        $this->subject->teachers()->attach($this->teacher);
        $this->subject->students()->attach($this->student, ['enrollment_type' => 'assigned', 'enrolled_at' => now()]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge(['title' => 'Karya animasi', 'level' => 'Dasar', 'subject_id' => $this->subject->id], $extra);
    }

    public function test_mixed_attachments_are_available_to_student_and_teacher_and_can_be_streamed(): void
    {
        $response = $this->actingAs($this->student)->postJson('/api/pbl-projects', $this->payload(['files' => [
            UploadedFile::fake()->image('storyboard.jpg'),
            UploadedFile::fake()->create('narasi.mp3', 10, 'audio/mpeg'),
            UploadedFile::fake()->create('animasi.mp4', 10, 'video/mp4'),
        ]]))->assertCreated()->assertJsonCount(3, 'project.attachments');
        $project = PblProject::findOrFail($response->json('project.id'));
        foreach ($project->attachments as $file) {
            Storage::disk('local')->assertExists($file['path']);
        }
        $url = $response->json('project.attachments.0.url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        // File URLs now require the logged-in owner or teacher, not a public signature.
        $this->actingAs($this->teacher)->getJson('/api/guru/pending-projects')->assertOk()->assertJsonCount(3, '0.attachments');
        $this->actingAs($this->teacher)->get('/guru/projects/'.$project->id.'/grade')->assertOk()->assertSee('storyboard.jpg')->assertSee('<audio', false)->assertSee('<video', false);
        $this->travel(2)->days();
        $this->get($url)->assertOk();
    }

    public function test_text_only_and_legacy_single_upload_are_supported(): void
    {
        $this->actingAs($this->student)->postJson('/api/pbl-projects', $this->payload(['description' => 'Penjelasan prinsip animasi']))
            ->assertCreated()->assertJsonCount(0, 'project.attachments');
        $this->postJson('/api/pbl-projects', $this->payload(['file' => UploadedFile::fake()->create('laporan.pdf', 10, 'application/pdf')]))
            ->assertCreated()->assertJsonPath('project.file_name', 'laporan.pdf');
    }

    public function test_empty_unsafe_and_oversize_submissions_are_rejected(): void
    {
        $this->actingAs($this->student)->postJson('/api/pbl-projects', $this->payload())->assertUnprocessable();
        $this->postJson('/api/pbl-projects', $this->payload(['files' => [UploadedFile::fake()->create('script.php', 1, 'text/x-php')]]))->assertUnprocessable();
        $this->postJson('/api/pbl-projects', $this->payload(['files' => [UploadedFile::fake()->create('large.mp4', 51201, 'video/mp4')]]))->assertUnprocessable();
        $this->postJson('/api/pbl-projects', $this->payload(['files' => array_map(fn () => UploadedFile::fake()->create('clip.mp4', 40000, 'video/mp4'), range(1, 3))]))->assertUnprocessable();
        $this->postJson('/api/pbl-projects', $this->payload(['files' => array_map(fn () => UploadedFile::fake()->image('frame.jpg'), range(1, 6))]))->assertUnprocessable();
        $this->assertDatabaseCount('pbl_projects', 0);
    }

    public function test_outsiders_cannot_submit_read_grade_or_delete(): void
    {
        $id = $this->actingAs($this->student)->postJson('/api/pbl-projects', $this->payload(['description' => 'Jawaban']))->json('project.id');
        $outsider = User::factory()->create(['role' => 'siswa']);
        $this->actingAs($outsider)->postJson('/api/pbl-projects', $this->payload(['description' => 'Jawaban']))->assertForbidden();
        $this->getJson('/api/pbl-projects/'.$id)->assertNotFound();
        $this->deleteJson('/api/pbl-projects/'.$id)->assertNotFound();
        $teacher = User::factory()->create(['role' => 'guru', 'status' => 'active']);
        $this->actingAs($teacher)->postJson('/api/guru/projects/'.$id.'/grade', $this->grade())->assertForbidden();
    }

    private function grade(): array
    {
        return ['feedback' => 'Narasi jernih dan visual sesuai konsep.', 'rubric_scores' => ['kreativitas' => 85, 'teknis' => 90, 'konsep' => 70, 'presentasi' => 100], 'rubric_feedback' => ['teknis' => 'Mixing audio baik']];
    }

    public function test_grading_uses_weighted_score_and_preserves_attachments(): void
    {
        $id = $this->actingAs($this->student)->postJson('/api/pbl-projects', $this->payload(['files' => [UploadedFile::fake()->create('narasi.wav', 10, 'audio/x-wav')]]))->assertCreated()->json('project.id');
        $this->actingAs($this->teacher)->postJson('/api/guru/projects/'.$id.'/grade', $this->grade())->assertOk()->assertJsonPath('score', 85.25);
        $this->postJson('/api/guru/projects/'.$id.'/grade', $this->grade())->assertUnprocessable();
        $this->actingAs($this->student)->getJson('/api/pbl-projects/'.$id)->assertJsonPath('score', 85.25)->assertJsonPath('rubric_feedback.teknis', 'Mixing audio baik')->assertJsonCount(1, 'attachments');
        $this->deleteJson('/api/pbl-projects/'.$id)->assertUnprocessable();
    }

    public function test_deleting_ungraded_project_removes_all_files(): void
    {
        $id = $this->actingAs($this->student)->postJson('/api/pbl-projects', $this->payload(['files' => [UploadedFile::fake()->image('frame.jpg')]]))->assertCreated()->json('project.id');
        $path = PblProject::findOrFail($id)->attachments[0]['path'];
        $this->deleteJson('/api/pbl-projects/'.$id)->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('pbl_projects', ['id' => $id]);
    }

    public function test_legacy_stored_projects_keep_their_attachment(): void
    {
        $project = PblProject::create($this->payload(['user_id' => $this->student->id, 'file_path' => 'pbl_projects/old.pdf', 'file_name' => 'old.pdf', 'file_type' => 'application/pdf']));
        $this->actingAs($this->student)->getJson('/api/pbl-projects/'.$project->id)->assertOk()->assertJsonPath('attachments.0.name', 'old.pdf');
    }
}
