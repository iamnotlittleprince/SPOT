<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPOT - Recuperar senha</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Barlow+Condensed:wght@700;800&display=swap" rel="stylesheet">
</head>
<body class="app-body">
    <main class="dashboard-shell">
        <section class="dashboard-panel">
            <p class="dashboard-kicker">SPOT</p>
            <h1>Recuperar senha</h1>
            <p>A recuperacao de senha ainda nao foi configurada. Volte para o login e entre com seu usuario.</p>
            <a href="{{ route('login') }}" class="btn-link">Voltar ao login</a>
        </section>
    </main>
</body>
</html>
