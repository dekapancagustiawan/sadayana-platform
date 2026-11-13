{{--
    Layout halaman error, dipakai seluruh view di resources/views/errors.

    Sengaja TIDAK memakai @vite: halaman ini harus tetap tampil benar saat yang
    rusak justru build aset atau database. Semua gaya ditulis inline, dan setiap
    pembacaan sesi/izin/setting dibungkus rescue() supaya error 500 akibat
    database mati tidak berujung pada error kedua di dalam halaman errornya.

    Warna di :root disamakan dengan palet `brand` di resources/css/app.css.
--}}
@php
    // Saat DB mati, auth()->user() bisa melempar exception — jangan sampai
    // halaman error ikut pecah.
    $user = rescue(fn () => auth()->user(), null, false);

    $site = rescue(
        fn () => app(\App\Services\Settings\SettingsService::class)->publicProfile(),
        ['name' => config('app.name'), 'logo_url' => null],
        false
    );

    // Tujuan tombol utama: dashboard yang memang boleh dibuka user ini
    // (User::landingRoute, sumber yang sama dengan tujuan setelah login), atau
    // halaman utama publik untuk tamu.
    $backUrl = $user
        ? rescue(fn () => route($user->landingRoute()), null, false)
        : null;

    $isStaff = $backUrl !== null;
    $homeUrl = rescue(fn () => route('public.home'), url('/'), false);

    // Halaman error tidak pernah menawarkan tautan masuk ke sistem: pengunjung
    // publik tidak perlu tahu di mana pintu masuk staf berada. Staf yang sudah
    // masuk diarahkan kembali ke dashboard yang memang boleh ia buka; tamu
    // dikembalikan ke halaman utama publik.
    $primaryUrl = $isStaff ? $backUrl : $homeUrl;
    $primaryLabel = $isStaff ? 'Kembali ke Dashboard' : 'Kembali ke Halaman Utama';

    $secondaryUrl = $isStaff ? $homeUrl : null;
    $secondaryLabel = 'Halaman Utama';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') — {{ $site['name'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #1e1b4b;      /* brand-950 */
            --brand: #6366f1;   /* brand-500 */
            --text: #eef2ff;    /* brand-50 */
            --muted: #a5b4fc;   /* brand-300 */
            --accent: #f59e0b;
            --ink: #1e1b4b;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: 'Inter', Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }
        .shell {
            position: relative;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .shell::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(60% 55% at 85% 10%, rgba(99, 102, 241, .45), transparent 70%),
                radial-gradient(40% 40% at 10% 100%, rgba(245, 158, 11, .12), transparent 70%);
        }
        .shell > * { position: relative; z-index: 1; }

        header.bar {
            padding: 22px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .tag {
            font-size: .7rem;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--muted);
        }
        header.bar a { display: flex; align-items: center; gap: 10px; color: var(--text); text-decoration: none; font-weight: 800; }
        header.bar img { height: 28px; width: auto; display: block; }
        .mark {
            width: 30px; height: 30px; border-radius: 8px;
            display: grid; place-items: center;
            background: var(--brand); color: #fff; font-weight: 800;
        }

        main.body {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 24px 28px 56px;
        }
        .content { width: 100%; max-width: 660px; }

        .code {
            font-weight: 600;
            font-size: .75rem;
            letter-spacing: .3em;
            color: var(--accent);
            text-transform: uppercase;
            margin: 0 0 18px;
        }
        .numeral {
            font-size: clamp(4.5rem, 17vw, 9rem);
            font-weight: 800;
            line-height: .84;
            letter-spacing: -.055em;
            margin: 0 0 18px;
        }
        h1 {
            font-size: clamp(1.4rem, 4.6vw, 2.1rem);
            font-weight: 800;
            letter-spacing: -.02em;
            line-height: 1.15;
            margin: 0 0 14px;
            text-wrap: balance;
        }
        .lede {
            font-size: 1rem;
            line-height: 1.6;
            color: rgba(238, 242, 255, .78);
            margin: 0;
            max-width: 52ch;
        }

        .who {
            font-size: .8rem;
            line-height: 1.5;
            color: var(--muted);
            border-left: 2px solid var(--brand);
            padding: 2px 0 2px 12px;
            margin: 22px 0 0;
        }
        .who b { color: var(--text); font-weight: 600; }

        .actions {
            margin-top: 32px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 13px 22px;
            font-size: .85rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 999px;
            border: 1px solid transparent;
            transition: transform .16s ease, background-color .16s ease, color .16s ease;
        }
        .btn-primary { background: #fff; color: var(--ink); }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-ghost {
            border-color: rgba(238, 242, 255, .3);
            color: var(--text);
            background: transparent;
        }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
        .btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

        footer.foot {
            padding: 0 28px 26px;
            font-size: .75rem;
            color: rgba(238, 242, 255, .4);
            display: flex;
            flex-wrap: wrap;
            gap: 6px 18px;
        }
        footer.foot a { color: inherit; text-decoration: none; border-bottom: 1px solid rgba(238, 242, 255, .2); }
        footer.foot a:hover { color: var(--accent); border-color: var(--accent); }

        @media (max-width: 520px) {
            header.bar, main.body, footer.foot { padding-left: 20px; padding-right: 20px; }
            .actions .btn { flex: 1 1 100%; justify-content: center; }
        }
        @media (prefers-reduced-motion: reduce) {
            .btn { transition: none; }
            .btn-primary:hover { transform: none; }
        }
    </style>
</head>
<body>
<div class="shell">
    <header class="bar">
        <a href="{{ $isStaff ? $backUrl : $homeUrl }}" aria-label="{{ $site['name'] }}">
            @if ($site['logo_url'])
                <img src="{{ $site['logo_url'] }}" alt="{{ $site['name'] }}">
            @else
                <span class="mark">{{ mb_substr($site['name'], 0, 1) }}</span>
                <span>{{ $site['name'] }}</span>
            @endif
        </a>
        <span class="tag">@yield('tag')</span>
    </header>

    <main class="body">
        <div class="content">
            <p class="code">Error @yield('code')</p>
            <p class="numeral">@yield('code')</p>
            <h1>@yield('title')</h1>
            <p class="lede">@yield('message')</p>

            @hasSection('note')
                <p class="who">@yield('note')</p>
            @elseif($isStaff && $user)
                <p class="who">
                    Masuk sebagai <b>{{ $user->name }}</b>@if($user->email) · {{ $user->email }}@endif<br>
                    Hak akses ditentukan role akun Anda.
                </p>
            @endif

            <div class="actions">
                <a class="btn btn-primary" href="{{ $primaryUrl }}">
                    @if(str_starts_with($primaryLabel, 'Kembali'))<span>&larr;</span>@endif
                    {{ $primaryLabel }}
                </a>
                @if($secondaryUrl)
                    <a class="btn btn-ghost" href="{{ $secondaryUrl }}">{{ $secondaryLabel }}</a>
                @endif
                @yield('extra_action')
            </div>
        </div>
    </main>

    <footer class="foot">
        <span>&copy; {{ date('Y') }} {{ $site['name'] }}</span>
        @unless($isStaff)
            <a href="{{ $homeUrl }}">Beranda</a>
        @endunless
    </footer>
</div>
</body>
</html>
