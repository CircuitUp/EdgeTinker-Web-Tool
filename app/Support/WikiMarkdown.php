<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Turns wiki Markdown into safe HTML plus a table of contents.
 *
 * Safety: raw HTML in the Markdown is escaped (shown as text, never run) and
 * unsafe links such as javascript: are removed, so page content cannot inject
 * scripts. Everything added afterwards (ids, callouts, copy buttons) is
 * generated here, not taken from the author.
 *
 * Supported on top of standard Markdown: tables, task lists, strikethrough,
 * fenced code blocks with a copy button, and callouts written as
 *
 *     > [!NOTE]
 *     > Text of the note.
 *
 * with NOTE, TIP or WARNING.
 */
class WikiMarkdown
{
    private const CALLOUTS = ['NOTE' => 'Note', 'TIP' => 'Tip', 'WARNING' => 'Warning'];

    private static ?MarkdownConverter $converter = null;

    /** @return array{html: string, toc: list<array{id: string, text: string, level: int}>} */
    public static function render(?string $markdown): array
    {
        $markdown = trim((string) $markdown);

        if ($markdown === '') {
            return ['html' => '', 'toc' => []];
        }

        $html = (string) self::converter()->convert($markdown);

        $document = new DOMDocument('1.0', 'UTF-8');
        // The wrapper gives the fragment a single root; the meta tag makes libxml read it as UTF-8.
        @$document->loadHTML('<meta charset="utf-8"><div id="wiki-root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $root = $document->getElementById('wiki-root');

        $toc = self::addHeadingIds($xpath);
        self::convertCallouts($document, $xpath);
        self::wrapCodeBlocks($document, $xpath);
        self::wrapTables($document, $xpath);
        self::finishLinksAndImages($xpath);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return ['html' => $output, 'toc' => $toc];
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'escape',          // raw HTML is displayed, never executed
                'allow_unsafe_links' => false,     // drops javascript:, vbscript:, data: links
                'max_nesting_level' => 20,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new GithubFlavoredMarkdownExtension);

            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }

    /** Gives h2–h4 unique ids and returns h2/h3 as the "On this page" list. */
    private static function addHeadingIds(DOMXPath $xpath): array
    {
        $toc = [];
        $used = [];

        foreach ($xpath->query('//h1|//h2|//h3|//h4') as $heading) {
            /** @var DOMElement $heading */
            $text = trim($heading->textContent);
            $base = Str::slug($text) ?: 'section';
            $id = $base;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = "$base-$n";
            }
            $used[$id] = true;
            $heading->setAttribute('id', $id);

            $level = (int) substr($heading->tagName, 1);
            if ($level === 2 || $level === 3) {
                $toc[] = ['id' => $id, 'text' => $text, 'level' => $level];
            }
        }

        return $toc;
    }

    /** Blockquotes starting with [!NOTE] / [!TIP] / [!WARNING] become callout boxes. */
    private static function convertCallouts(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach (iterator_to_array($xpath->query('//blockquote')) as $quote) {
            /** @var DOMElement $quote */
            $first = $xpath->query('./p[1]', $quote)->item(0);
            if (! $first || ! preg_match('/^\s*\[!(NOTE|TIP|WARNING)\]\s*/i', $first->textContent, $match)) {
                continue;
            }

            $kind = strtoupper($match[1]);

            // Remove the "[!NOTE]" marker (and a line break that may follow it) from the first paragraph.
            $textNode = $first->firstChild;
            if ($textNode && $textNode->nodeType === XML_TEXT_NODE) {
                $textNode->nodeValue = preg_replace('/^\s*\[!(NOTE|TIP|WARNING)\]\s*/i', '', $textNode->nodeValue);
                if ($textNode->nodeValue === '' && $textNode->nextSibling?->nodeName === 'br') {
                    $first->removeChild($textNode->nextSibling);
                }
            }
            if (trim($first->textContent) === '' && ! $first->getElementsByTagName('*')->length) {
                $quote->removeChild($first);
            }

            $box = $document->createElement('div');
            $box->setAttribute('class', 'callout callout-'.strtolower($kind));
            $box->setAttribute('role', 'note');

            $title = $document->createElement('p', self::CALLOUTS[$kind]);
            $title->setAttribute('class', 'callout-title');
            $box->appendChild($title);

            while ($quote->firstChild) {
                $box->appendChild($quote->firstChild);
            }
            $quote->parentNode->replaceChild($box, $quote);
        }
    }

    /** Wraps each code block so a "Copy" button can sit in its corner (wired up in wiki.js). */
    private static function wrapCodeBlocks(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach (iterator_to_array($xpath->query('//pre[code]')) as $pre) {
            /** @var DOMElement $pre */
            $wrapper = $document->createElement('div');
            $wrapper->setAttribute('class', 'code-block');

            $language = '';
            $code = $pre->getElementsByTagName('code')->item(0);
            if ($code instanceof DOMElement && preg_match('/language-([\w+#.-]+)/', $code->getAttribute('class'), $match)) {
                $language = $match[1];
            }

            $bar = $document->createElement('div');
            $bar->setAttribute('class', 'code-block-bar');
            $bar->appendChild($document->createElement('span', $language));

            $button = $document->createElement('button', 'Copy');
            $button->setAttribute('type', 'button');
            $button->setAttribute('class', 'code-copy');
            $button->setAttribute('data-copy', '');
            $bar->appendChild($button);

            $pre->setAttribute('tabindex', '0'); // scrollable region must be keyboard reachable
            $pre->parentNode->replaceChild($wrapper, $pre);
            $wrapper->appendChild($bar);
            $wrapper->appendChild($pre);
        }
    }

    /** Wide tables scroll sideways inside their own box instead of breaking the page. */
    private static function wrapTables(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach (iterator_to_array($xpath->query('//table')) as $table) {
            $wrapper = $document->createElement('div');
            $wrapper->setAttribute('class', 'table-scroll');
            $wrapper->setAttribute('tabindex', '0');
            $wrapper->setAttribute('role', 'region');
            $wrapper->setAttribute('aria-label', 'Table');
            $table->parentNode->replaceChild($wrapper, $table);
            $wrapper->appendChild($table);
        }
    }

    private static function finishLinksAndImages(DOMXPath $xpath): void
    {
        foreach ($xpath->query('//img') as $image) {
            /** @var DOMElement $image */
            $image->setAttribute('loading', 'lazy');
            $image->setAttribute('decoding', 'async');
            if (! $image->hasAttribute('alt')) {
                $image->setAttribute('alt', '');
            }
        }

        foreach ($xpath->query('//a[@href]') as $link) {
            /** @var DOMElement $link */
            $href = $link->getAttribute('href');

            if (preg_match('#^https?://#i', $href) && ! str_starts_with($href, url('/'))) {
                $link->setAttribute('rel', 'noopener noreferrer');
            }
            // Links to uploaded documents download instead of opening.
            if (preg_match('/\.(pdf|zip|step|stp|stl|bin|hex|uf2|csv)$/i', parse_url($href, PHP_URL_PATH) ?? '')) {
                $link->setAttribute('class', 'download-link');
                $link->setAttribute('download', '');
            }
        }
    }

    /** Plain text of a page, for search-result excerpts. */
    public static function plainText(?string $markdown, int $limit = 220): string
    {
        $text = preg_replace(['/```.*?```/s', '/!\[[^\]]*\]\([^)]*\)/', '/\[([^\]]*)\]\([^)]*\)/', '/^\s*>\s*\[![A-Z]+\]\s*$/mi', '/[#>*_`|~-]+/'], [' ', ' ', '$1', ' ', ' '], (string) $markdown);

        return Str::limit(trim(preg_replace('/\s+/', ' ', $text)), $limit);
    }
}
