<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RPP (Rencana Pelaksanaan Pembelajaran) — cuma guru pengampu yang boleh
 * menulis, dan cuma guru pengampu + siswa terdaftar mapel itu yang boleh
 * membaca. Guru/siswa di luar mapel harus ditolak 403.
 */
class LessonPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;
    private User $teacher;
    private User $outsiderTeacher;
    private User $student;
    private User $outsiderStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'guru', 'status' => 'active']);
        $this->outsiderTeacher = User::factory()->create(['role' => 'guru', 'status' => 'active']);
        $this->student = User::factory()->create(['role' => 'siswa']);
        $this->outsiderStudent = User::factory()->create(['role' => 'siswa']);

        $this->subject = Subject::create([
            'name'       => 'Matematika',
            'join_code'  => 'MTK001',
            'created_by' => $this->teacher->id,
        ]);
        $this->subject->teachers()->attach($this->teacher->id);
        $this->subject->students()->attach($this->student->id, [
            'enrollment_type' => 'assigned',
            'enrolled_at'     => now(),
        ]);
    }

    public function test_guru_pengampu_bisa_membuat_rpp(): void
    {
        $response = $this->actingAs($this->teacher)->postJson(
            "/api/guru/subjects/{$this->subject->id}/lesson-plans",
            [
                'meeting_number'     => 1,
                'title'              => 'Pengenalan Aljabar',
                'learning_objective' => 'Siswa paham konsep variabel',
                'scheduled_date'     => '2026-08-20',
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('lesson_plans', [
            'subject_id'     => $this->subject->id,
            'meeting_number' => 1,
            'title'          => 'Pengenalan Aljabar',
            'created_by'     => $this->teacher->id,
        ]);
    }

    public function test_nomor_pertemuan_tidak_boleh_duplikat_dalam_satu_mapel(): void
    {
        $this->actingAs($this->teacher)->postJson(
            "/api/guru/subjects/{$this->subject->id}/lesson-plans",
            ['meeting_number' => 1, 'title' => 'Pertemuan pertama']
        )->assertStatus(201);

        $this->actingAs($this->teacher)->postJson(
            "/api/guru/subjects/{$this->subject->id}/lesson-plans",
            ['meeting_number' => 1, 'title' => 'Duplikat']
        )->assertStatus(422);
    }

    public function test_guru_di_luar_mapel_tidak_bisa_membuat_rpp(): void
    {
        $this->actingAs($this->outsiderTeacher)->postJson(
            "/api/guru/subjects/{$this->subject->id}/lesson-plans",
            ['meeting_number' => 1, 'title' => 'Sabotase']
        )->assertStatus(403);

        $this->assertDatabaseMissing('lesson_plans', ['title' => 'Sabotase']);
    }

    public function test_siswa_terdaftar_bisa_melihat_rpp_mapelnya(): void
    {
        $this->actingAs($this->teacher)->postJson(
            "/api/guru/subjects/{$this->subject->id}/lesson-plans",
            ['meeting_number' => 1, 'title' => 'Pengenalan Aljabar']
        )->assertStatus(201);

        $response = $this->actingAs($this->student)->getJson(
            "/api/subjects/{$this->subject->id}/lesson-plans"
        );

        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function test_siswa_di_luar_mapel_tidak_bisa_melihat_rpp(): void
    {
        $this->actingAs($this->outsiderStudent)->getJson(
            "/api/subjects/{$this->subject->id}/lesson-plans"
        )->assertStatus(403);
    }

    public function test_guru_bisa_toggle_status_selesai(): void
    {
        $create = $this->actingAs($this->teacher)->postJson(
            "/api/guru/subjects/{$this->subject->id}/lesson-plans",
            ['meeting_number' => 1, 'title' => 'Pengenalan Aljabar']
        );
        $planId = $create->json('lesson_plan.id');

        $response = $this->actingAs($this->teacher)->postJson(
            "/api/guru/lesson-plans/{$planId}/toggle-complete"
        );

        $response->assertStatus(200)
            ->assertJsonPath('lesson_plan.is_completed', true);

        $this->assertDatabaseHas('lesson_plans', [
            'id'           => $planId,
            'is_completed' => true,
        ]);
    }

    public function test_guru_bisa_menghapus_rpp(): void
    {
        $create = $this->actingAs($this->teacher)->postJson(
            "/api/guru/subjects/{$this->subject->id}/lesson-plans",
            ['meeting_number' => 1, 'title' => 'Pengenalan Aljabar']
        );
        $planId = $create->json('lesson_plan.id');

        $this->actingAs($this->teacher)->deleteJson("/api/guru/lesson-plans/{$planId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('lesson_plans', ['id' => $planId]);
    }
}
