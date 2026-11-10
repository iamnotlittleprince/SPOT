<?php

namespace App\Domain\Chat;

use Illuminate\Validation\ValidationException;

/** Stores a small, explicit document schema; never accepts or renders user HTML. */
class ChatFormatting
{
    private int $nodes = 0;

    public function normalize(mixed $document): ?array
    {
        if ($document === null || $document === '') return null;
        if (is_string($document)) {
            if (strlen($document) > 60000) $this->invalid();
            try { $document = json_decode($document, true, 20, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { $this->invalid(); }
        }
        if (! is_array($document) || strlen(json_encode($document)) > 60000) $this->invalid();
        $this->nodes = 0;
        $result = $this->node($document, 0, ['doc']);
        if (mb_strlen($this->text($result)) > 5000) $this->invalid();
        return $result;
    }

    public function text(array $node): string
    {
        if ($node['type'] === 'text') return $node['text'];
        if ($node['type'] === 'hardBreak') return "\n";
        $separator = in_array($node['type'], ['doc', 'blockquote', 'bulletList', 'orderedList', 'listItem'], true) ? "\n" : '';
        return implode($separator, array_map(fn ($child) => $this->text($child), $node['content'] ?? []));
    }

    private function node(array $node, int $depth, array $allowed): array
    {
        if (++$this->nodes > 1200 || $depth > 12 || ! in_array($node['type'] ?? null, $allowed, true)
            || array_diff(array_keys($node), ['type', 'content', 'attrs', 'marks', 'text'])) $this->invalid();
        $type = $node['type'];
        $result = ['type' => $type];
        $attrs = $node['attrs'] ?? [];
        if (! is_array($attrs)) $this->invalid();
        $validAttrs = match ($type) { 'heading' => ['level'], 'orderedList' => ['start', 'type'], 'codeBlock' => ['language'], default => [] };
        if (array_diff(array_keys($attrs), $validAttrs)) $this->invalid();
        if ($type === 'heading') {
            if (! in_array($attrs['level'] ?? null, [1, 2, 3], true)) $this->invalid();
            $result['attrs'] = ['level' => $attrs['level']];
        } elseif ($type === 'orderedList') {
            $start = $attrs['start'] ?? 1;
            if (! is_int($start) || $start < 1 || $start > 9999 || ! in_array($attrs['type'] ?? null, [null, '1'], true)) $this->invalid();
            $result['attrs'] = ['start' => $start];
        } elseif ($type === 'codeBlock' && isset($attrs['language'])) {
            if (! is_string($attrs['language']) || ! preg_match('/^[a-z0-9+#-]{1,30}$/i', $attrs['language'])) $this->invalid();
            $result['attrs'] = ['language' => $attrs['language']];
        }
        if ($type === 'text') {
            if (! is_string($node['text'] ?? null) || $node['text'] === '' || isset($node['content'])) $this->invalid();
            $result['text'] = $node['text'];
        } elseif (isset($node['text'])) $this->invalid();
        if (isset($node['marks'])) {
            if (! in_array($type, ['text', 'hardBreak'], true) || ! is_array($node['marks']) || count($node['marks']) > 8) $this->invalid();
            $marks = []; $seen = [];
            foreach ($node['marks'] as $mark) {
                $clean = $this->mark($mark);
                if (in_array($clean['type'], $seen, true)) $this->invalid();
                $seen[] = $clean['type']; $marks[] = $clean;
            }
            if ($marks) $result['marks'] = $marks;
        }
        $inline = ['text', 'hardBreak'];
        $blocks = ['paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'codeBlock', 'horizontalRule'];
        $children = match ($type) {
            'doc', 'blockquote', 'listItem' => $blocks,
            'paragraph', 'heading' => $inline,
            'bulletList', 'orderedList' => ['listItem'],
            'codeBlock' => ['text'],
            default => [],
        };
        if (isset($node['content']) && (! is_array($node['content']) || ! array_is_list($node['content']))) $this->invalid();
        if (! $children && ! empty($node['content'])) $this->invalid();
        if ($children) {
            $result['content'] = [];
            foreach ($node['content'] ?? [] as $child) {
                if (! is_array($child)) $this->invalid();
                $clean = $this->node($child, $depth + 1, $children);
                if ($type === 'codeBlock' && ! empty($clean['marks'])) $this->invalid();
                $result['content'][] = $clean;
            }
            if (in_array($type, ['doc', 'blockquote', 'listItem', 'bulletList', 'orderedList'], true) && ! $result['content']) $this->invalid();
            if ($type === 'listItem' && $result['content'][0]['type'] !== 'paragraph') $this->invalid();
        }
        return $result;
    }

    private function mark(mixed $mark): array
    {
        if (! is_array($mark) || array_diff(array_keys($mark), ['type', 'attrs'])
            || ! in_array($mark['type'] ?? null, ['bold', 'italic', 'underline', 'strike', 'code', 'highlight', 'textStyle', 'link'], true)) $this->invalid();
        $type = $mark['type']; $attrs = $mark['attrs'] ?? [];
        if (! is_array($attrs)) $this->invalid();
        $allowed = match ($type) { 'textStyle' => ['color', 'fontSize'], 'link' => ['href', 'target', 'rel', 'class'], 'highlight' => ['color'], default => [] };
        if (array_diff(array_keys($attrs), $allowed)) $this->invalid();
        $result = ['type' => $type];
        if ($type === 'link') {
            $href = $attrs['href'] ?? null;
            if (! is_string($href) || strlen($href) > 2048 || preg_match('/[\x00-\x20\x7f]/', $href)
                || ! filter_var($href, FILTER_VALIDATE_URL) || ! in_array(strtolower(parse_url($href, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)
                || parse_url($href, PHP_URL_USER) !== null || parse_url($href, PHP_URL_PASS) !== null) $this->invalid();
            $result['attrs'] = ['href' => $href];
        } elseif ($type === 'textStyle') {
            $clean = [];
            foreach (['color' => ['purple', 'red', 'green', 'blue'], 'fontSize' => ['normal', 'large', 'larger']] as $key => $values) {
                if (isset($attrs[$key])) {
                    if (! in_array($attrs[$key], $values, true)) $this->invalid();
                    $clean[$key] = $attrs[$key];
                }
            }
            if ($clean) $result['attrs'] = $clean;
        } elseif ($type === 'highlight' && isset($attrs['color'])) $this->invalid();
        return $result;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['body_format' => 'A formatação da mensagem é inválida ou excede o limite. Revise o texto e tente novamente.']);
    }
}
