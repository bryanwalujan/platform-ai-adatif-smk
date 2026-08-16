<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * RPP (Rencana Pelaksanaan Pembelajaran) — dibuat & dikelola guru pengampu
 * mata pelajaran, dilihat guru pengampu & siswa yang terdaftar di mapel itu
 * saja (pola scoping sama seperti ContentController).
 */
class LessonPlanController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    /**
     * GET /subjects/{subjectId}/lesson-plans
     * Guru pengampu & siswa terdaftar mapel ini bisa lihat.
     */
    public function index(Request $request, $subjectId)
    {
        $this->access->assertEnrolled($request->user(), $subjectId);

        $plans = LessonPlan::where('subject_id', $subjectId)
            ->with('topic:id,title')
            ->orderBy('meeting_number')
            ->get();

        return response()->json($plans);
    }

    /**
     * GET /guru/lesson-plans/{id}
     */
    public function show(Request $request, $id)
    {
        $plan = LessonPlan::with('topic:id,title')->findOrFail($id);
        $this->access->assertEnrolled($request->user(), $plan->subject_id);

        return response()->json($plan);
    }

    /**
     * POST /guru/subjects/{subjectId}/lesson-plans
     * Isi manual (learning_objective/description) DAN/ATAU lampiran file
     * bebas dipilih guru — dua-duanya opsional, tidak saling mewajibkan.
     */
    public function store(Request $request, $subjectId)
    {
        $this->access->assertTeaches($request->user(), $subjectId);

        $validated = $request->validate([
            'meeting_number'      => 'required|integer|min:1|unique:lesson_plans,meeting_number,NULL,id,subject_id,' . $subjectId,
            'title'               => 'required|string|max:255',
            'learning_objective'  => 'nullable|string',
            'description'         => 'nullable|string',
            'scheduled_date'      => 'nullable|date',
            'topic_id'            => 'nullable|exists:topics,id',
            'file'                => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:15360',
        ]);

        $data = [
            'subject_id'          => $subjectId,
            'created_by'          => $request->user()->id,
            'topic_id'            => $validated['topic_id'] ?? null,
            'meeting_number'      => $validated['meeting_number'],
            'title'               => $validated['title'],
            'learning_objective'  => $validated['learning_objective'] ?? null,
            'description'         => $validated['description'] ?? null,
            'scheduled_date'      => $validated['scheduled_date'] ?? null,
        ];

        if ($request->hasFile('file')) {
            $data = array_merge($data, $this->storeFile($request));
        }

        $plan = LessonPlan::create($data);

        return response()->json(['message' => 'RPP berhasil dibuat', 'lesson_plan' => $plan], 201);
    }

    /**
     * PUT /guru/lesson-plans/{id}
     * Unggah file baru menggantikan file lama (kalau ada). Field manual
     * tetap bisa diedit terpisah tanpa harus ikut unggah ulang file.
     */
    public function update(Request $request, $id)
    {
        $plan = LessonPlan::findOrFail($id);
        $this->access->assertTeaches($request->user(), $plan->subject_id);

        $validated = $request->validate([
            'meeting_number'      => 'sometimes|integer|min:1|unique:lesson_plans,meeting_number,' . $plan->id . ',id,subject_id,' . $plan->subject_id,
            'title'               => 'sometimes|string|max:255',
            'learning_objective'  => 'nullable|string',
            'description'         => 'nullable|string',
            'scheduled_date'      => 'nullable|date',
            'topic_id'            => 'nullable|exists:topics,id',
            'file'                => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:15360',
        ]);

        if ($request->hasFile('file')) {
            if ($plan->file_path) {
                Storage::disk('public')->delete($plan->file_path);
            }
            $validated = array_merge($validated, $this->storeFile($request));
        }

        $plan->update($validated);

        return response()->json(['message' => 'RPP berhasil diperbarui', 'lesson_plan' => $plan->fresh()]);
    }

    /**
     * Simpan file lampiran RPP yang baru diunggah, kembalikan kolom-kolom
     * file_* siap dipakai create()/update() — dipakai store() & update().
     */
    private function storeFile(Request $request): array
    {
        $file = $request->file('file');
        $path = $file->store('lesson_plans', 'public');

        return [
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
        ];
    }

    /**
     * DELETE /guru/lesson-plans/{id}
     */
    public function destroy(Request $request, $id)
    {
        $plan = LessonPlan::findOrFail($id);
        $this->access->assertTeaches($request->user(), $plan->subject_id);

        if ($plan->file_path) {
            Storage::disk('public')->delete($plan->file_path);
        }
        $plan->delete();

        return response()->json(['message' => 'RPP berhasil dihapus']);
    }

    /**
     * POST /guru/lesson-plans/{id}/toggle-complete
     * Buat guru tracking pertemuan mana yang sudah benar-benar dijalankan.
     */
    public function toggleComplete(Request $request, $id)
    {
        $plan = LessonPlan::findOrFail($id);
        $this->access->assertTeaches($request->user(), $plan->subject_id);

        $plan->update(['is_completed' => ! $plan->is_completed]);

        return response()->json(['message' => 'Status RPP diperbarui', 'lesson_plan' => $plan->fresh()]);
    }
}
