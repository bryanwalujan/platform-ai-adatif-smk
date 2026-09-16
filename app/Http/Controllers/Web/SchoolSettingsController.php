<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;

class SchoolSettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.school.edit', ['school' => $request->user()->school]);
    }

    public function update(Request $request)
    {
        $request->user()->school->update($request->validate(['name' => 'required|string|max:255']));

        return back()->with('success', 'Nama sekolah diperbarui.');
    }

    public function regenerateCode(Request $request)
    {
        $request->user()->school->update(['code' => School::newCode()]);

        return back()->with('success', 'Kode sekolah diperbarui. Akun lama tetap terdaftar.');
    }
}
