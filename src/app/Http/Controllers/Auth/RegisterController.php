<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StudyProgram;
use App\Models\User;
use App\Support\Notify;
use App\Support\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Pendaftaran mandiri mahasiswa. Tanpa SMTP email tidak bisa diverifikasi, jadi pengamannya:
 * domain email kampus, NIM & email unik, rate limit, Turnstile (bila dikonfigurasi), dan admin diberi tahu setiap ada pendaftar.
 */
class RegisterController extends Controller
{
    public function show()
    {
        return Auth::check() ? redirect(Auth::user()->homeUrl())
            : view('auth.register', ['prodis' => StudyProgram::orderBy('name')->pluck('name', 'id'), 'domains' => config('fasinova.student_email_domains')]);
    }

    public function store(Request $r)
    {
        $domains = config('fasinova.student_email_domains');
        $r->merge(['email' => mb_strtolower(trim((string) $r->email)), 'nim' => trim((string) $r->nim)]);
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'nim' => 'required|alpha_num:ascii|between:5,20|unique:users,nim',
            'email' => array_filter(['required', 'email', 'max:150', 'unique:users,email', $domains ? 'ends_with:'.collect($domains)->map(fn ($x) => '@'.$x)->join(',') : null]),
            'study_program_id' => 'required|exists:study_programs,id',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'pernyataan' => 'accepted',
        ], [
            'email.ends_with' => 'Gunakan email kampus ('.collect($domains)->map(fn ($x) => '@'.$x)->join(' / ').').',
            'nim.unique' => 'NIM ini sudah terdaftar. Silakan masuk, atau hubungi admin bila bukan Anda yang mendaftar.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk.',
        ]);
        Turnstile::verify($r);

        $u = User::create(collect($d)->except('pernyataan')->all() + ['role' => 'mahasiswa']);
        Notify::users(Notify::admins(), 'Mahasiswa baru mendaftar', "{$u->name} ({$u->nim}) — {$u->email}", '/admin/users', 'info');

        Auth::login($u);
        $r->session()->regenerate();
        return redirect($u->homeUrl());
    }
}
