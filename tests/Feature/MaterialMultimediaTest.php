<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\School;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaterialMultimediaTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Subject $subject;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->teacher = User::factory()->create(['role' => 'guru', 'status' => 'active']);
        $this->student = User::factory()->create(['role' => 'siswa']);
        $this->subject = Subject::create(['name' => 'IPA', 'created_by' => $this->teacher->id, 'join_code' => 'IPA123']);
        $this->subject->teachers()->attach($this->teacher);
        $this->subject->students()->attach($this->student, ['enrollment_type' => 'assigned', 'enrolled_at' => now()]);
        $this->topic = Topic::create(['subject_id' => $this->subject->id, 'title' => 'Energi', 'order' => 1]);
    }

    private function endpoint(): string
    {
        return '/api/guru/subjects/'.$this->subject->id.'/content/materials';
    }

    private function data(array $extra = []): array
    {
        return array_merge(['topic_id' => $this->topic->id, 'title' => 'Energi di sekitar kita'], $extra);
    }

    public function test_teacher_uploads_mixed_media_and_students_access_them(): void
    {
        $response = $this->actingAs($this->teacher)->postJson($this->endpoint(), $this->data(['files' => [
            UploadedFile::fake()->image('diagram.png'), UploadedFile::fake()->create('demo.mp4', 10, 'video/mp4'),
            UploadedFile::fake()->create('narasi.mp3', 10, 'audio/mpeg'), UploadedFile::fake()->create('modul.pdf', 10, 'application/pdf'),
        ]]))->assertCreated()->assertJsonCount(4, 'material.attachments')->assertJsonMissingPath('material.media_files');
        $id = $response->json('material.id');
        $url = $response->json('material.attachments.0.url');
        $this->getJson('/api/guru/subjects/'.$this->subject->id.'/content/topics')->assertOk()->assertJsonCount(4, '0.materials.0.attachments');
        $this->actingAs($this->student)->getJson('/api/materials/'.$id)->assertOk()->assertJsonCount(4, 'attachments');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getJson('/api/topics/'.$this->topic->id)->assertOk()->assertJsonCount(4, 'materials.0.attachments');
    }

    public function test_edit_retains_files_until_explicitly_removed_and_cleans_deleted_files(): void
    {
        $response = $this->actingAs($this->teacher)->postJson($this->endpoint(), $this->data(['files' => [UploadedFile::fake()->image('old.png')]]))->assertCreated();
        $id = $response->json('material.id');
        $oldId = $response->json('material.attachments.0.id');
        $oldPath = Material::find($id)->media_files[0]['path'];
        $this->putJson($this->endpoint().'/'.$id, ['title' => 'Judul baru'])->assertOk()->assertJsonCount(1, 'material.attachments');
        $this->post($this->endpoint().'/'.$id, ['_method' => 'PUT', 'remove_attachment_ids' => [$oldId],
            'files' => [UploadedFile::fake()->create('new.mp3', 10, 'audio/mpeg')]])->assertOk()->assertJsonPath('material.attachments.0.name', 'new.mp3');
        Storage::disk('public')->assertMissing($oldPath);
        $path = Material::find($id)->media_files[0]['path'];
        $this->deleteJson($this->endpoint().'/'.$id)->assertOk();
        Storage::disk('public')->assertMissing($path);
    }

    public function test_validation_rejects_empty_unsafe_oversized_and_too_many_files_without_saving(): void
    {
        $this->actingAs($this->teacher)->postJson($this->endpoint(), $this->data())->assertUnprocessable();
        $this->postJson($this->endpoint(), $this->data(['files' => [UploadedFile::fake()->create('bad.php', 1, 'text/x-php')]]))->assertUnprocessable();
        $this->postJson($this->endpoint(), $this->data(['files' => [UploadedFile::fake()->create('large.mp4', 51201, 'video/mp4')]]))->assertUnprocessable();
        $this->postJson($this->endpoint(), $this->data(['files' => array_map(fn () => UploadedFile::fake()->create('clip.mp4', 40000, 'video/mp4'), range(1, 3))]))->assertUnprocessable();
        $this->postJson($this->endpoint(), $this->data(['files' => array_map(fn () => UploadedFile::fake()->image('frame.png'), range(1, 6))]))->assertUnprocessable();
        $this->assertDatabaseCount('materials', 0);
    }

    public function test_text_link_and_legacy_files_remain_supported(): void
    {
        $this->actingAs($this->teacher)->postJson($this->endpoint(), $this->data(['content' => 'Materi berbentuk teks']))->assertCreated();
        $this->postJson($this->endpoint(), $this->data(['video_url' => 'https://example.test/video.mp4']))->assertCreated();
        $this->postJson($this->endpoint(), $this->data(['video_url' => 'javascript:alert(1)']))->assertUnprocessable();
        $legacy = Material::create(['topic_id' => $this->topic->id, 'title' => 'Materi lama', 'content' => '', 'file_path' => 'materials/old.pdf', 'file_name' => 'old.pdf', 'file_type' => 'application/pdf']);
        Storage::disk('public')->put($legacy->file_path, 'legacy');
        $this->getJson('/api/materials/'.$legacy->id)->assertJsonPath('attachments.0.id', 'legacy');
        $this->putJson($this->endpoint().'/'.$legacy->id, ['title' => 'Diperbarui'])->assertOk()->assertJsonPath('material.attachments.0.name', 'old.pdf');
        $this->get(Material::find($legacy->id)->attachments[0]['url'])->assertOk();
    }

    public function test_school_subject_and_role_guards_protect_mutation_and_file_access(): void
    {
        $r = $this->actingAs($this->teacher)->postJson($this->endpoint(), $this->data(['files' => [UploadedFile::fake()->image('diagram.png')]]))->assertCreated();
        $id = $r->json('material.id');
        $url = $r->json('material.attachments.0.url');
        $this->actingAs($this->student)->postJson($this->endpoint(), $this->data(['content' => 'Tidak boleh']))->assertForbidden();
        $outsider = User::factory()->create(['role' => 'siswa']);
        $this->actingAs($outsider)->getJson($url)->assertForbidden();
        $school = School::create(['name' => 'Sekolah Lain', 'code' => 'LAIN123']);
        $other = User::factory()->create(['school_id' => $school->id, 'role' => 'guru', 'status' => 'active']);
        $this->actingAs($other)->getJson($url)->assertNotFound();
        $this->putJson($this->endpoint().'/'.$id, ['title' => 'Sabotase'])->assertNotFound();
        $anotherSubject = Subject::create(['name' => 'Bahasa', 'join_code' => 'BHS123', 'created_by' => $this->teacher->id]);
        $anotherSubject->teachers()->attach($this->teacher);
        $this->actingAs($this->teacher)->putJson('/api/guru/subjects/'.$anotherSubject->id.'/content/materials/'.$id, ['title' => 'Salah konteks'])->assertNotFound();
    }

    public function test_removal_validation_does_not_delete_existing_content(): void
    {
        $r = $this->actingAs($this->teacher)->postJson($this->endpoint(), $this->data(['files' => [UploadedFile::fake()->image('diagram.png')]]))->assertCreated();
        $id = $r->json('material.id');
        $fileId = $r->json('material.attachments.0.id');
        $this->putJson($this->endpoint().'/'.$id, ['remove_attachment_ids' => ['wrong-id']])->assertUnprocessable();
        $this->putJson($this->endpoint().'/'.$id, ['remove_attachment_ids' => [$fileId]])->assertUnprocessable();
        $this->assertCount(1, Material::find($id)->media_files);
        Storage::disk('public')->assertExists(Material::find($id)->media_files[0]['path']);
    }

    public function test_quiz_questions_use_nested_quiz_id_instead_of_subject_id(): void
    {
        $this->actingAs($this->teacher);
        $base = '/api/guru/subjects/'.$this->subject->id.'/content';
        $this->postJson($base.'/quizzes', ['topic_id' => $this->topic->id, 'title' => 'Kuis 1'])->assertCreated();
        $quizId = $this->postJson($base.'/quizzes', ['topic_id' => $this->topic->id, 'title' => 'Kuis 2'])->assertCreated()->json('id');
        $this->postJson($base.'/quizzes/'.$quizId.'/questions', ['question' => 'Energi?', 'options' => ['A' => 'Benar', 'B' => 'Salah'], 'correct_answer' => 'A'])->assertCreated()->assertJsonPath('question.quiz_id', $quizId);
    }
}
