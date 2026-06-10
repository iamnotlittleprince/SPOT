<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SPOT - Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&family=Barlow+Condensed:wght@700;800&display=swap" rel="stylesheet">
</head>
<body class="app-body">
    <main class="dashboard-shell">
        <header class="dashboard-header">
            <div>
                <p class="dashboard-kicker">SPOT</p>
                <h1>Dashboard</h1>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout">Sair</button>
            </form>
        </header>

        <section class="dashboard-panel">
            <h2>Login realizado com sucesso</h2>
            <p>Conectado como {{ auth()->user()->Email }} usando o banco SQL Server SPOT.</p>
        </section>
    </main>
</body>
</html>
