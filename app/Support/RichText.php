<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitises rich-text HTML (product descriptions, page content) before it is
 * output. Content is written by trusted staff, but it is still cleaned on the
 * way out so a pasted <script>, inline event handler or javascript: link can
 * never reach a visitor's browser.
 */
class RichText
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): HtmlString
    {
        if ($html === null || trim($html) === '') {
            return new HtmlString('');
        }

        return new HtmlString(self::sanitizer()->sanitize($html));
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer === null) {
            $config = (new HtmlSanitizerConfig)
                // Safe formatting elements only: no script, style, iframe, form or event attributes.
                ->allowSafeElements()
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->dropAttribute('style', '*')
                ->dropAttribute('class', '*')
                ->dropAttribute('id', '*')
                ->withMaxInputLength(200_000);

            self::$sanitizer = new HtmlSanitizer($config);
        }

        return self::$sanitizer;
    }
}
