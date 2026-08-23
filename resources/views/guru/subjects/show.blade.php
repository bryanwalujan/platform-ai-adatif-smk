@extends('guru.layout')
@section('title', $subject->name)
@section('content')
    <div class="cards">
        <div class="card"><div class="num">{{ $subject->students_count }}</div><div class="label">Siswa</div></div>
        <div class="card"><div class="num">{{ $subject->topics_count }}</div><div class="label">Topik</div></div>
        <div class="card"><div class="num"><code class="join-code">{{ $subject->join_code }}</code></div><div class="label">Kode Kelas</div></div>
    </div>

    <div style="display:flex; gap:8px; margin-bottom:20px;">
        <a href="{{ route('guru.subjects.edit', $subject->id) }}" class="btn btn-sm">Edit Info</a>
        <form method="POST" action="{{ route('guru.subjects.join-code.regenerate', $subject->id) }}"
              onsubmit="return confirm('Buat ulang kode kelas? Kode lama tidak akan berlaku lagi.')">
            @csrf
            <button type="submit" class="btn btn-sm">Buat Ulang Kode Kelas</button>
        </form>
        <a href="{{ route('guru.subjects.content.topics', $subject->id) }}" class="btn btn-sm btn-primary">Kelola Topik & Materi</a>
        <a href="{{ route('guru.subjects.lesson-plans.index', $subject->id) }}" class="btn btn-sm btn-primary">Kelola RPP</a>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <div class="panel-header">Guru Pengampu</div>
        <table>
            <thead><tr><th>Nama</th><th>Email</th></tr></thead>
            <tbody>
                @foreach($subject->teachers as $t)
                    <tr><td>{{ $t->name }}</td><td>{{ $t->email }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="panel">
        <div class="panel-header">
            Siswa Terdaftar
        </div>

        <div style="padding:14px 18px; border-bottom:1px solid var(--border); position:relative;">
            <input type="text" id="student-search" placeholder="Cari nama siswa untuk ditambahkan..."
                   style="width:100%; max-width:360px; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font-size:13px;"
                   autocomplete="off">
            <div id="student-search-results" style="position:absolute; z-index:10; background:#fff; border:1px solid var(--border); border-radius:8px; margin-top:4px; max-width:360px; width:100%; display:none; box-shadow:0 8px 20px rgba(0,0,0,.08);"></div>
        </div>

        <table>
            <thead><tr><th>Nama</th><th>Email</th><th>Tipe</th><th></th></tr></thead>
            <tbody>
                @forelse($subject->students as $s)
                    <tr>
                        <td>{{ $s->name }}</td>
                        <td>{{ $s->email }}</td>
                        <td>{{ $s->pivot->enrollment_type === 'self_joined' ? 'Gabung Sendiri' : 'Ditambahkan Guru' }}</td>
                        <td class="actions">
                            <form method="POST" action="{{ route('guru.subjects.students.remove', [$subject->id, $s->id]) }}"
                                  onsubmit="return confirm('Keluarkan {{ $s->name }} dari mata pelajaran ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Keluarkan</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Belum ada siswa terdaftar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Form tambah siswa tersembunyi, di-submit lewat JS setelah pilih dari hasil pencarian --}}
    <form id="add-student-form" method="POST" action="{{ route('guru.subjects.students.add', $subject->id) }}" style="display:none;">
        @csrf
        <input type="hidden" name="user_id" id="add-student-user-id">
    </form>

    <script>
        const searchInput   = document.getElementById('student-search');
        const resultsBox    = document.getElementById('student-search-results');
        let debounceTimer;

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const q = searchInput.value.trim();

            if (q.length < 2) {
                resultsBox.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(async () => {
                const res = await fetch(`{{ route('guru.students.search') }}?q=${encodeURIComponent(q)}`);
                const students = await res.json();

                if (!students.length) {
                    resultsBox.innerHTML = '<div style="padding:10px 12px; font-size:13px; color:#6b7280;">Tidak ditemukan.</div>';
                } else {
                    resultsBox.innerHTML = students.map(s => `
                        <div class="student-result" data-id="${s.id}" style="padding:10px 12px; font-size:13px; cursor:pointer; border-bottom:1px solid #f0f0f0;">
                            <strong>${s.name}</strong><br>
                            <span style="color:#6b7280;">${s.email}</span>
                        </div>
                    `).join('');
                }

                resultsBox.style.display = 'block';
            }, 300);
        });

        resultsBox.addEventListener('click', (e) => {
            const row = e.target.closest('.student-result');
            if (!row) return;

            document.getElementById('add-student-user-id').value = row.dataset.id;
            document.getElementById('add-student-form').submit();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#student-search') && !e.target.closest('#student-search-results')) {
                resultsBox.style.display = 'none';
            }
        });
    </script>
@endsection