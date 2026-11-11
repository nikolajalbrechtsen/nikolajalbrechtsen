<?php

namespace App\Support;

use App\Models\Page;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Turns a CMS page (its blocks) into Markdown.
 * Used for /{slug}.md — e.g. the GitHub README pulls /about.md.
 */
class PageMarkdown
{
    protected HtmlConverter $converter;

    public function __construct(protected string $baseUrl)
    {
        $this->converter = new HtmlConverter([
            'strip_tags' => true,
            'remove_nodes' => 'script style iframe form',
            'hard_break' => true,
            'header_style' => 'atx',
        ]);
    }

    public function render(Page $page): string
    {
        $parts = [];

        foreach ($page->blocks ?? [] as $block) {
            $md = $this->block($block['type'] ?? '', $block['data'] ?? []);
            if (filled(trim($md))) {
                $parts[] = trim($md);
            }
        }

        return trim(implode("\n\n", $parts))."\n";
    }

    protected function block(string $type, array $d): string
    {
        return match ($type) {
            'hero' => $this->lines([
                $this->heading($d['heading'] ?? null, 2),
                $d['subheading'] ?? null,
                $this->image($d['image'] ?? null),
            ]),
            'rich_text' => $this->lines([
                $this->heading($d['heading'] ?? null, 2),
                $this->html((string) rich($d['content'] ?? null)),
            ]),
            'image_text' => $this->lines([
                $this->heading($d['heading'] ?? null, 2),
                $this->image($d['image'] ?? null),
                $this->html((string) rich($d['content'] ?? null)),
                $this->link($d['button_label'] ?? null, $d['button_url'] ?? null),
            ]),
            'features' => $this->lines([
                $this->heading($d['heading'] ?? null, 2),
                $d['subheading'] ?? null,
                collect($d['items'] ?? [])
                    ->map(fn ($i) => '- **'.trim($i['title'] ?? '').'**'.(filled($i['text'] ?? null) ? ' — '.trim($i['text']) : ''))
                    ->join("\n"),
            ]),
            'stats' => $this->lines([
                $this->heading($d['heading'] ?? null, 2),
                collect($d['items'] ?? [])->map(fn ($i) => '- **'.trim($i['value'] ?? '').'** '.trim($i['label'] ?? ''))->join("\n"),
            ]),
            'faq' => $this->lines([
                $this->heading($d['heading'] ?? null, 2),
                collect($d['items'] ?? [])->map(fn ($i) => '**'.trim($i['question'] ?? '')."**  \n".trim($i['answer'] ?? ''))->join("\n\n"),
            ]),
            'cta' => $this->lines([
                $this->heading($d['heading'] ?? null, 3),
                $d['text'] ?? null,
                $this->link($d['button_label'] ?? null, $d['button_url'] ?? null),
            ]),
            'html' => $this->html((string) ($d['html'] ?? '')),
            default => '', // dynamic blocks (contact form, latest posts, events) don't belong in a README
        };
    }

    protected function lines(array $lines): string
    {
        return collect($lines)->filter(fn ($l) => filled(is_string($l) ? trim($l) : $l))->map(fn ($l) => trim($l))->join("\n\n");
    }

    protected function heading(?string $text, int $level): ?string
    {
        return filled($text) ? str_repeat('#', $level).' '.trim($text) : null;
    }

    protected function image(?string $path): ?string
    {
        return filled($path) ? '![]('.$this->absolute(public_url($path)).')' : null;
    }

    protected function link(?string $label, ?string $url): ?string
    {
        return filled($label) && filled($url) ? '['.trim($label).' →]('.$this->absolute($url).')' : null;
    }

    protected function html(string $html): string
    {
        if (blank(trim(strip_tags($html, '<img>')))) {
            return '';
        }
        // Relative links/images must point at the website when shown on GitHub.
        $html = preg_replace('/\b(src|href)="\/(?!\/)/', '$1="'.$this->baseUrl.'/', $html);

        return trim($this->converter->convert($html));
    }

    protected function absolute(string $url): string
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $this->baseUrl.$url : $url;
    }
}
