<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Spot · {{ $status }} · {{ $title }}</title>
    <link rel="icon" href="/spot-favicon.png">
    <link rel="stylesheet" href="/spot-errors.css">
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('spot.theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); } catch {}</script>
</head>
<body style="margin:0">
<main class="spot-error-shell">
    <section class="spot-error-card" aria-labelledby="error-title">
        <div>
            <img class="spot-error-brand" src="/spot-logo.png" alt="Spot">
            <span class="spot-error-code">Erro {{ $status }}</span>
            <h1 id="error-title">{{ $title }}</h1>
            <p>{{ $description }}</p>
            <nav class="spot-error-actions" aria-label="Opções para continuar">
                <a href="/">{{ in_array($status, [401, 419]) ? 'Entrar novamente' : 'Ir para o início' }}</a>
                @if(request()->isMethod('GET') && $status >= 500)
                    <a class="spot-error-secondary" href="{{ '/'.ltrim(request()->getRequestUri(), '/') }}">Tentar abrir novamente</a>
                @endif
            </nav>
            <p class="spot-error-footnote">Se uma ação foi interrompida, confira o resultado antes de enviá-la novamente.</p>
        </div>
        <div class="spot-error-art" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"><rect x="18" y="26" width="84" height="64" rx="12"/><path d="M43 105h34M60 90v15M60 43v18"/><circle cx="60" cy="74" r="3" fill="currentColor" stroke="none"/><path d="m12 12 7 7m89-7-7 7"/></svg></div>
    </section>
</main>
</body>
</html>
