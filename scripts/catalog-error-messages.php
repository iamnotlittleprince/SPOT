<?php

// Static catalog: no database access and no application mutation.
$root = dirname(__DIR__);
function flattenMessages(array $values, string $prefix = ''): array {
    $result = [];
    foreach ($values as $key => $value) {
        if (in_array($key, ['attributes', 'custom'], true) && $prefix === '') continue;
        $path = $prefix ? $prefix.'.'.$key : $key;
        if (is_array($value)) $result += flattenMessages($value, $path);
        else $result[$path] = $value;
    }
    return $result;
}
function cell(string $value): string { return str_replace(['|', "\n", "\r"], ['\\|', '<br>', ''], $value); }
$pt = require $root.'/lang/pt_BR/validation.php';
$en = require $root.'/lang/en/validation.php';
$es = require $root.'/lang/es/validation.php';
$lines = [
    '# Mensagens de erro do Spot', '',
    'Catálogo gerado a partir dos textos do código e dos arquivos de idioma. Reproduzir com `./php scripts/catalog-error-messages.php`. Não consulta nem altera o banco.', '',
    '## Como os erros são apresentados', '',
    '- Validação: resposta HTTP 422 com resumo em `message` e mensagens específicas em `errors`, por campo. O editor de projetos e cadastros associa as mensagens com `aria-describedby` e marca os campos com `aria-invalid`; preserva os valores e anuncia o resumo sem deslocar o foco.',
    '- Duplicidade de proposta: **Este número de proposta já está em uso, inclusive em projetos arquivados. Informe outro número.** A reserva de números dos projetos arquivados continua em vigor.',
    '- Regras de negócio: preservar a explicação específica do fluxo. Os textos encontrados no código estão no inventário abaixo.',
    '- Falhas HTTP: mensagens padrão identificam acesso, sessão, limite de tentativas, item indisponível ou falha do serviço e orientam o próximo passo. Detalhes internos de falhas 5xx não são retornados.',
    '- Rede: explicar a falha de conexão e orientar a conferir o resultado antes de repetir uma operação. Cancelamentos intencionais não são tratados como falha de rede.',
    '- Idiomas: catálogos comuns de validação, autenticação, recuperação de senha e HTTP em pt/en/es. O cliente envia `X-Spot-Locale` com a escolha da interface. Mensagens específicas já existentes mantêm suas traduções existentes; o inventário não certifica tradução integral de todo texto dinâmico.', '',
    '## Validações comuns', '',
    'Os marcadores `:attribute`, `:min`, `:max`, `:date` e similares são substituídos pelo campo e pelos limites da regra. Arquivos usam KB nos limites de validação. Nomes de pessoas e conteúdo de usuários não são traduzidos.', '',
    '| Regra | Português | English | Español |', '| --- | --- | --- | --- |',
];
$english = flattenMessages($en); $spanish = flattenMessages($es);
foreach (flattenMessages($pt) as $rule => $message) $lines[] = '| '.cell($rule).' | '.cell($message).' | '.cell($english[$rule]).' | '.cell($spanish[$rule]).' |';
$lines = [...$lines, '', '## Nomes dos campos', '', '| Campo da API | Português | English | Español |', '| --- | --- | --- | --- |'];
foreach ($pt['attributes'] as $field => $label) $lines[] = '| '.cell($field).' | '.cell($label).' | '.cell($en['attributes'][$field]).' | '.cell($es['attributes'][$field]).' |';
$lines = [...$lines, '', '## Autenticação e recuperação de senha', '', '| Grupo/regra | Português | English | Español |', '| --- | --- | --- | --- |'];
foreach (['auth', 'passwords'] as $group) {
    $catalogs = array_map(fn ($locale) => require $root.'/lang/'.$locale.'/'.$group.'.php', ['pt_BR', 'en', 'es']);
    foreach ($catalogs[0] as $key => $message) $lines[] = '| '.$group.'.'.$key.' | '.cell($message).' | '.cell($catalogs[1][$key]).' | '.cell($catalogs[2][$key]).' |';
}
$lines = [...$lines, '', '## HTTP, rede e interface', '', '| Código ou origem | Título | Orientação em português |', '| --- | --- | --- |'];
foreach (json_decode(file_get_contents($root.'/resources/errors/messages.json'), true, flags: JSON_THROW_ON_ERROR) as $code => [$title, $description]) $lines[] = '| '.$code.' | '.cell($title).' | '.cell($description).' |';
$lines = [...$lines, '', '## Inventário dos textos específicos no código', '',
    'Textos literais encontrados em contextos de erro, validação ou exceção. Inclui fragmentos de mensagens montadas com valores dinâmicos e expressões de cliente; não enumera cada resultado possível de interpolação nem mensagens de serviços externos. As referências apontam para o local da declaração. As mensagens genéricas da seção anterior cobrem falhas externas sem texto aproveitável.', '',
    '| Origem | Texto ou fragmento |', '| --- | --- |'];
$entries = [];
foreach (['app', 'resources/js'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js', 'jsx'], true)) continue;
        if (str_contains($file->getPathname(), '/Mail/') || str_ends_with($file->getPathname(), '/translations.js')) continue;
        $source = file_get_contents($file->getPathname());
        $sourceLines = explode("\n", $source);
        preg_match_all('/([\'\"])((?:\\\\.|(?!\1).)*?)\1/s', $source, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[2] as [$literal, $offset]) {
            if (strlen($literal) < 15 || !preg_match('/\s/', $literal) || str_contains($literal, '<') || str_contains($literal, '\n')) continue;
            if (!preg_match('/não |inválid|incorret|expir|revogad|indispon|falh|erro|negad|insufic|aguarde|preencha|informe|escolha|selecione|confir|remova|obrigat|apenas |somente |exige|precisa|permit|imposs|incompat|exced|limite|antes de|deve |reconect|reabra|sem acesso|sem permissão|failed|error|unauthor|must |not found|forbidden/iu', $literal)) continue;
            $line = substr_count(substr($source, 0, $offset), "\n");
            $context = implode("\n", array_slice($sourceLines, max(0, $line - 3), 5));
            if (!preg_match('/abort|throw|withMessages|errors\(|errors\(\)->add|setError|setFeedback|\.message|\.required|\.unique|\.exists|messages\(|requiredMessage|minLengthMessage|error\s*[:=]/i', $context)) continue;
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $entries[$relative.':'.($line + 1).'|'.$literal] = [$relative, $line + 1, $literal];
        }
    }
}
ksort($entries);
foreach ($entries as [$path, $line, $text]) $lines[] = '| ['.$path.':'.$line.'](../'.$path.'#L'.$line.') | '.cell($text).' |';
$lines = [...$lines, '', '## Verificação e limites', '',
    'Os resultados desta alteração estão em [verificação das mensagens de erro](verificacao-mensagens-de-erro.md). A cobertura automatizada não substitui revisão humana com teclado, leitor de tela, zoom e pessoas com deficiência. A associação visual e programática por campo foi ajustada no editor compartilhado de projetos e cadastros; outros formulários mantêm seus mecanismos existentes.', ''];
file_put_contents($root.'/docs/mensagens-de-erro.md', implode("\n", $lines));
echo count($entries).' declarações catalogadas.'.PHP_EOL;
