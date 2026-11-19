<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — FASINOVA</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-600 to-brand-700 flex items-center justify-center p-4">
<main class="w-full max-w-sm">
    <div class="text-center text-white mb-6">
        <h1 class="text-3xl font-bold tracking-tight">FASINOVA</h1>
        <p class="text-sm text-white/80">Fasilkom Innovation Showcase</p>
    </div>
    <form method="post" action="{{ route('login') }}" class="bg-white rounded-xl shadow-xl p-6 space-y-4">
        @csrf
        <h2 class="font-semibold text-slate-800">Masuk ke akun Anda</h2>
        @error('email')<p class="text-sm text-red-600 bg-red-50 rounded px-3 py-2">{{ $message }}</p>@enderror
        <label class="block text-sm text-slate-700">Email
            <input name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus required
                   class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
        </label>
        <label class="block text-sm text-slate-700">Kata sandi
            <input name="password" type="password" autocomplete="current-password" required
                   class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
        </label>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-slate-600"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
        </div>
        <button class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-lg py-2.5 font-medium">Masuk</button>
    </form>
    <p class="text-center mt-4"><a href="/" class="text-sm text-white/80 hover:text-white">← Kembali ke etalase</a></p>
</main>
</body>
</html>
