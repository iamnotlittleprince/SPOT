<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>Atualizações do Spot</title></head>
<body style="font-family:Arial,sans-serif;color:#183447;line-height:1.5;max-width:640px;margin:auto;padding:24px">
<h1>Atualizações do Spot</h1>
@foreach ($items as $item)
<section style="border-bottom:1px solid #dde5eb;padding:12px 0">
<h2 style="font-size:18px">{{ $item['title'] }}</h2>
<p>{{ $item['body'] }}</p>
<p style="font-size:12px;color:#526878">{{ $item['actor_name'] }}</p>
</section>
@endforeach
<p><a href="{{ url('/') }}">Abrir o Spot</a></p>
<p style="font-size:12px">Você pode ajustar os canais, resumos e o horário de silêncio em Meu perfil → Notificações.</p>
</body></html>
