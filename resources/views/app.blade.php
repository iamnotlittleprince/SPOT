<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Spot - Organize, planeje e execute seus projetos.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="/spot-favicon.png?v=8">
    <link rel="apple-touch-icon" href="/spot-favicon.png?v=8">
    <title>Spot</title>
    <script>
        window.__SPOT__ = {{ Illuminate\Support\Js::from([
            'authenticated' => auth()->check(),
            'user' => auth()->user() ? [
                ...auth()->user()->only(['name', 'email', 'avatar_url', 'timezone', 'job_title', 'department']),
                'can_manage_identity' => auth()->user()->can('security.manage'),
            ] : null,
        ]) }};
    </script>
    @viteReactRefresh
    @vite('resources/js/app.jsx')
</head>
<body>
    <div id="root"></div>
</body>
</html>
