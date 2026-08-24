<!DOCTYPE html>
<html lang="pt-BR">
<body style="margin:0;padding:32px;background:#f3f6f8;font-family:Arial,sans-serif;color:#25313c">
<table role="presentation" style="width:100%;max-width:600px;margin:auto;background:#fff;border-radius:12px;border-collapse:separate;padding:32px">
    <tr><td>
        <h1 style="margin:0 0 16px;font-size:24px">Você recebeu um convite para o Spot</h1>
        <p style="line-height:1.6"><strong>{{ $inviterName }}</strong> convidou você para visualizar o projeto <strong>{{ $project->name }}</strong>.</p>
        <p style="line-height:1.6">Este link é pessoal, pode ser usado uma única vez e expira em {{ $expiresInHours }} horas.</p>
        <p style="margin:28px 0"><a href="{{ $acceptUrl }}" style="display:inline-block;padding:13px 20px;border-radius:7px;background:#0c588a;color:#fff;text-decoration:none;font-weight:bold">Aceitar convite</a></p>
        <p style="font-size:12px;color:#71808c;line-height:1.5">Se você não esperava este convite, ignore esta mensagem.</p>
    </td></tr>
</table>
</body>
</html>
