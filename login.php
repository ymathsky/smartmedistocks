<?php
session_start();

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

$error   = '';
$message = '';
if (isset($_SESSION['login_error']))   { $error   = $_SESSION['login_error'];   unset($_SESSION['login_error']); }
if (isset($_SESSION['login_message'])) { $message = $_SESSION['login_message']; unset($_SESSION['login_message']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — SmartMediStocks</title>
    <link rel="shortcut icon" type="image/png" href="logo.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { font-family: 'Inter', sans-serif; }

        html, body { height: 100%; margin: 0; }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #f1f5f9;
        }

        /* ── Left panel image with overlay ── */
        .login-hero {
            position: relative;
            overflow: hidden;
        }
        .login-hero img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .login-hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                160deg,
                rgba(6,18,55,0.72) 0%,
                rgba(15,40,100,0.55) 50%,
                rgba(2,10,30,0.80) 100%
            );
        }
        .login-hero-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 2.5rem;
        }

        /* ── Form card ── */
        .login-card {
            background: #fff;
            box-shadow: 0 20px 60px rgba(15,23,42,.10), 0 4px 16px rgba(15,23,42,.06);
        }

        /* ── Input focus ring ── */
        .sms-input {
            width: 100%;
            border: 1.5px solid #e2e8f0;
            border-radius: .6rem;
            padding: .65rem 1rem;
            font-size: .9rem;
            color: #1e293b;
            background: #f8fafc;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .sms-input:focus {
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }

        /* ── Password toggle ── */
        .pw-wrap { position: relative; }
        .pw-toggle {
            position: absolute;
            right: .75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            color: #94a3b8;
            line-height: 0;
        }
        .pw-toggle:hover { color: #475569; }

        /* ── Sign-in button ── */
        .btn-signin {
            width: 100%;
            padding: .72rem 1.5rem;
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            font-size: .95rem;
            border-radius: .6rem;
            border: none;
            cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(37,99,235,.35);
        }
        .btn-signin:hover {
            background: #1d4ed8;
            box-shadow: 0 6px 20px rgba(37,99,235,.45);
            transform: translateY(-1px);
        }
        .btn-signin:active { transform: translateY(0); }

        /* ── Badge pill on hero ── */
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            color: #e2e8f0;
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            padding: .3rem .75rem;
            border-radius: 9999px;
            backdrop-filter: blur(8px);
            margin-bottom: .85rem;
        }
        .hero-badge span { width: 6px; height: 6px; border-radius: 50%; background: #34d399; display: inline-block; }

        /* ── Fade-in card ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp .45s cubic-bezier(.4,0,.2,1) both; }

        /* ── Alert ── */
        .alert {
            display: flex; gap: .6rem; align-items: flex-start;
            padding: .7rem 1rem; border-radius: .55rem; font-size: .83rem;
            margin-bottom: 1.25rem;
        }
        .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    </style>
</head>
<body>

<main class="flex-1 flex items-stretch" style="min-height:100vh;">

    <!-- ── Left: Hero Panel ── -->
    <div class="login-hero hidden md:flex md:w-1/2 lg:w-[55%]">
        <img src="image.png" alt="SmartMediStocks hero">
        <div class="login-hero-overlay"></div>
        <div class="login-hero-content">
            <div class="hero-badge">
                <span></span> AI-Powered Platform
            </div>
            <h2 class="text-3xl lg:text-4xl font-extrabold text-white leading-snug mb-3">
                Optimize Your<br>Healthcare Supply Chain
            </h2>
            <p class="text-blue-100 text-sm lg:text-base leading-relaxed max-w-xs" style="opacity:.85;">
                Where AI meets efficient inventory management — reduce waste, prevent stockouts, and make data-driven decisions.
            </p>
            <div class="mt-6 flex gap-5">
                <div>
                    <div class="text-white font-bold text-xl">98%</div>
                    <div class="text-blue-200 text-xs mt-0.5">Stock accuracy</div>
                </div>
                <div style="border-left:1px solid rgba(255,255,255,.2); padding-left:1.25rem">
                    <div class="text-white font-bold text-xl">3×</div>
                    <div class="text-blue-200 text-xs mt-0.5">Faster reorders</div>
                </div>
                <div style="border-left:1px solid rgba(255,255,255,.2); padding-left:1.25rem">
                    <div class="text-white font-bold text-xl">AI</div>
                    <div class="text-blue-200 text-xs mt-0.5">Demand forecast</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Right: Sign-in Card ── -->
    <div class="w-full md:w-1/2 lg:w-[45%] flex items-center justify-center p-6 sm:p-10 login-card">
        <div class="w-full max-w-sm fade-up">

            <!-- Logo -->
            <div class="flex justify-center mb-6">
                <img src="logo.png" alt="SmartMediStocks" class="h-16 w-auto">
            </div>

            <!-- Heading -->
            <div class="text-center mb-7">
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Welcome Back</h1>
                <p class="text-sm text-slate-400 mt-1">Please sign in to access your account.</p>
            </div>

            <!-- Alerts -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-success" role="alert">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="login_handler.php" method="post" novalidate>
                <div class="mb-4">
                    <label for="username" class="block text-sm font-semibold text-slate-700 mb-1.5">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="username"
                        placeholder="Enter your username"
                        class="sms-input">
                </div>

                <div class="mb-5">
                    <div class="flex justify-between items-center mb-1.5">
                        <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                        <a href="change_password.php" class="text-xs text-blue-500 hover:text-blue-700 hover:underline transition-colors">Forgot your password?</a>
                    </div>
                    <div class="pw-wrap">
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            placeholder="Enter your password"
                            class="sms-input" style="padding-right:2.6rem;">
                        <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Toggle password visibility">
                            <svg id="pw-eye" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-signin">Sign In</button>
            </form>

            <!-- Footer note -->
            <p class="text-center text-xs text-slate-400 mt-7">
                &copy; <?php echo date('Y'); ?> SmartMediStocks &nbsp;&middot;&nbsp;
                <a href="terms.php" class="hover:text-blue-500 transition-colors">Terms</a>
                &nbsp;&middot;&nbsp;
                <a href="privacy.php" class="hover:text-blue-500 transition-colors">Privacy</a>
            </p>
        </div>
    </div>

</main>

<script>
function togglePw() {
    var input = document.getElementById('password');
    var eye   = document.getElementById('pw-eye');
    if (input.type === 'password') {
        input.type = 'text';
        eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 012.11-3.563M6.53 6.53A9.955 9.955 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.965 9.965 0 01-4.293 5.222M15 12a3 3 0 01-4.243 2.757M9.88 9.88A3 3 0 0115 12M3 3l18 18"/>';
    } else {
        input.type = 'password';
        eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
    }
}
</script>
</body>
</html>

