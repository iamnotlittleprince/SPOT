<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Spot — Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Barlow+Condensed:wght@700;800&display=swap" rel="stylesheet">
</head>
<body class="login-body">

    {{-- Blobs decorativos vermelhos --}}
    <div class="blob blob-top-left"></div>
    <div class="blob blob-bottom-left"></div>
    <div class="blob blob-bottom-right"></div>

    {{-- Card principal --}}
    <div class="login-card">

        {{-- Painel esquerdo: formulário --}}
        <div class="panel panel-form">
            <h1 class="welcome-title">Seja Bem-Vindo ao <span>Spot</span></h1>

            @if ($errors->any())
                <div class="alert-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" id="loginForm">
                @csrf

                <div class="field-group">
                    <input
                        type="email"
                        name="email"
                        placeholder="E-mail"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="field-input {{ $errors->has('email') ? 'field-error' : '' }}"
                    >
                </div>

                <div class="field-group">
                    <input
                        type="password"
                        name="password"
                        placeholder="••••••••••"
                        required
                        class="field-input {{ $errors->has('password') ? 'field-error' : '' }}"
                    >
                </div>

                <button type="submit" class="btn-enter" id="btnEnter">
                    <span class="btn-label">Entrar</span>
                    <span class="btn-loading" style="display:none;">Aguarde…</span>
                </button>

                <div class="options-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember"> Lembre-se de mim
                    </label>
                    <label class="checkbox-label">
                        <a href="{{ route('password.request') }}" class="forgot-link">Esqueci minha senha</a>
                    </label>
                </div>
            </form>

            <div class="social-row">
                <a href="{{ route('auth.google') }}" class="btn-social">
                    <img src="{{ asset('images/google-icon.png') }}" alt="Google" width="20" height="20">
                    Entrar com Google
                </a>
                <a href="{{ route('auth.microsoft') }}" class="btn-social">
                    <img src="{{ asset('images/microsoft-icon.png') }}" alt="Microsoft" width="20" height="20">
                    Entre com Microsoft
                </a>
            </div>

            <div class="logo-area">
                <img src="{{ asset('images/spot-logo.png') }}" alt="Spot Logo" class="spot-logo">
            </div>
        </div>

        {{-- Painel direito: ilustração --}}
        <div class="panel panel-illustration">
            <div class="illus-paper-plane">✈</div>
            <div class="illus-gear illus-gear-lg">⚙</div>
            <div class="illus-gear illus-gear-sm">⚙</div>

            <div class="phone-mockup">
                <div class="phone-screen">
                    <div class="phone-avatar">
                        <svg viewBox="0 0 40 40" width="40" height="40" fill="white">
                            <circle cx="20" cy="14" r="7"/>
                            <ellipse cx="20" cy="32" rx="12" ry="7"/>
                            <circle cx="12" cy="14" r="5" opacity=".6"/>
                            <circle cx="28" cy="14" r="5" opacity=".6"/>
                        </svg>
                    </div>
                    <div class="phone-fields">
                        <div class="pf-line pf-long"></div>
                        <div class="pf-line pf-short"></div>
                        <div class="pf-line pf-btn"></div>
                    </div>
                </div>
            </div>

            <div class="stick-figure">
                <div class="sf-head"></div>
                <div class="sf-body"></div>
                <div class="sf-arm-left"></div>
                <div class="sf-arm-right"></div>
                <div class="sf-leg-left"></div>
                <div class="sf-leg-right"></div>
            </div>
        </div>

    </div>

    {{-- Rodapé Computécnica --}}
    <div class="footer-brand">
        <img src="{{ asset('images/computecnica-logo.png') }}" alt="Computécnica" class="computecnica-logo">
    </div>

</body>
</html>

