<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Editable text of the Home, Services and Contact pages.
 *
 * Each page has a SCHEMA (the fields the Content Studio form shows and
 * validates) and DEFAULTS (the copy the site ships with). Stored content
 * overrides the defaults field by field, so a page always renders even
 * before anyone has edited it.
 *
 * Field types: text, textarea, lines (one item per line), icon, image,
 * products (a list of product ids), repeater (rows of sub-fields).
 */
class PageContent
{
    public const CACHE_KEY = 'pages.content';

    public const TITLES = ['home' => 'Home', 'services' => 'Services', 'contact' => 'Contact Us'];

    /** Icons offered for service cards (names from the icon component). */
    public const ICONS = ['cpu', 'layers', 'code', 'box', 'monitor', 'smartphone', 'book', 'check-circle', 'mail', 'search', 'filter'];

    /** Content for a page: defaults with any saved values laid over them. */
    public function get(string $slug): array
    {
        $stored = $this->stored()[$slug]['content'] ?? [];

        // Only keys that exist in the defaults are used, so an old saved field can never leak into a template.
        return array_replace(self::defaults($slug), array_intersect_key($stored, self::defaults($slug)));
    }

    /** @return array{title: ?string, description: ?string} */
    public function seo(string $slug): array
    {
        $page = $this->stored()[$slug] ?? [];

        return ['title' => $page['seo_title'] ?? null, 'description' => $page['seo_description'] ?? null];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function stored(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => Page::all()
                ->mapWithKeys(fn (Page $page) => [$page->slug => $page->only(['content', 'seo_title', 'seo_description'])])->all());
        } catch (Throwable) {
            return []; // database not migrated yet
        }
    }

    // ── Field definitions ────────────────────────────────────────────────────

    /** @return list<array> fields in form order; each has key, label, type and optional group, hint, required, max, rows, fields */
    public static function schema(string $slug): array
    {
        return match ($slug) {
            'home' => [
                ['key' => 'slides', 'group' => 'Hero slideshow', 'label' => 'Slides', 'type' => 'repeater', 'min_rows' => 1, 'max_rows' => 6, 'row_label' => 'Slide',
                    'hint' => 'The home page opens with these. Three slides work best.',
                    'fields' => [
                        ['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'required' => true, 'max' => 120],
                        ['key' => 'sub_text', 'label' => 'Sub-text', 'type' => 'textarea', 'max' => 300],
                        ['key' => 'button_label', 'label' => 'Button text', 'type' => 'text', 'max' => 40],
                        ['key' => 'button_url', 'label' => 'Button link', 'type' => 'link', 'max' => 255, 'hint' => 'A page on this site, e.g. /products, or a full https:// address.'],
                        ['key' => 'image', 'label' => 'Background image', 'type' => 'image', 'hint' => 'Wide images (about 1920 × 900) work best. Text is shown over a purple overlay.'],
                        ['key' => 'image_alt', 'label' => 'Image description for screen readers', 'type' => 'text', 'max' => 255, 'hint' => 'Leave empty if the image is purely decorative.'],
                    ]],
                ['key' => 'featured_product_ids', 'group' => 'Featured products', 'label' => 'Products to feature', 'type' => 'products', 'max_rows' => 12,
                    'hint' => 'Shown as cards on the home page, in this order.'],
                ['key' => 'featured_heading', 'group' => 'Featured products', 'label' => 'Heading', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'featured_intro', 'group' => 'Featured products', 'label' => 'Introduction', 'type' => 'textarea', 'max' => 300],
                ['key' => 'categories_heading', 'group' => 'Category tiles', 'label' => 'Heading', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'categories_intro', 'group' => 'Category tiles', 'label' => 'Introduction', 'type' => 'textarea', 'max' => 300],
                ['key' => 'services_heading', 'group' => 'Services overview', 'label' => 'Heading', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'services_intro', 'group' => 'Services overview', 'label' => 'Introduction', 'type' => 'textarea', 'max' => 500,
                    'hint' => 'The list of services itself comes from the Services page.'],
                ['key' => 'why_heading', 'group' => 'Why EdgeTinker', 'label' => 'Heading', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'why', 'group' => 'Why EdgeTinker', 'label' => 'Reasons', 'type' => 'repeater', 'max_rows' => 8, 'row_label' => 'Reason',
                    'fields' => [
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'max' => 80],
                        ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'required' => true, 'max' => 300],
                    ]],
                ['key' => 'contact_heading', 'group' => 'Contact call-to-action', 'label' => 'Heading', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'contact_text', 'group' => 'Contact call-to-action', 'label' => 'Text', 'type' => 'textarea', 'max' => 300],
                ['key' => 'partners_heading', 'group' => 'Partners', 'label' => 'Heading', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'partners', 'group' => 'Partners', 'label' => 'Partner logos', 'type' => 'repeater', 'max_rows' => 30, 'row_label' => 'Partner',
                    'hint' => 'Shown as a strip of logos just above the footer, moving continuously from right to left. Only the logos are displayed. With no partners, the section is hidden.',
                    'fields' => [
                        ['key' => 'name', 'label' => 'Partner name', 'type' => 'text', 'required' => true, 'max' => 80,
                            'hint' => 'Not shown on the page. It is read out by screen readers in place of the logo.'],
                        ['key' => 'logo', 'label' => 'Logo', 'type' => 'image', 'required' => true,
                            'hint' => 'A PNG or WebP with a transparent background works best. It is shown about 48 px high.'],
                    ]],
            ],
            'services' => [
                ['key' => 'heading', 'group' => 'Introduction', 'label' => 'Page heading', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'intro', 'group' => 'Introduction', 'label' => 'Introduction', 'type' => 'textarea', 'required' => true, 'max' => 800],
                ['key' => 'services', 'group' => 'Services', 'label' => 'Services', 'type' => 'repeater', 'min_rows' => 1, 'max_rows' => 12, 'row_label' => 'Service',
                    'fields' => [
                        ['key' => 'icon', 'label' => 'Icon', 'type' => 'icon', 'required' => true],
                        ['key' => 'title', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 80],
                        ['key' => 'text', 'label' => 'Short description', 'type' => 'textarea', 'required' => true, 'max' => 500],
                        ['key' => 'deliverables', 'label' => 'Typical deliverables', 'type' => 'lines', 'max' => 120, 'max_rows' => 10, 'hint' => 'One per line.'],
                    ]],
                ['key' => 'process_heading', 'group' => 'How we work', 'label' => 'Heading', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'process', 'group' => 'How we work', 'label' => 'Steps', 'type' => 'repeater', 'max_rows' => 8, 'row_label' => 'Step',
                    'fields' => [
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'max' => 80],
                        ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'required' => true, 'max' => 300],
                    ]],
                ['key' => 'cta_heading', 'group' => 'Enquiry', 'label' => 'Heading', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'cta_text', 'group' => 'Enquiry', 'label' => 'Text', 'type' => 'textarea', 'max' => 300],
                ['key' => 'cta_label', 'group' => 'Enquiry', 'label' => 'Button text', 'type' => 'text', 'required' => true, 'max' => 40],
            ],
            'contact' => [
                ['key' => 'heading', 'group' => 'Page text', 'label' => 'Page heading', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'intro', 'group' => 'Page text', 'label' => 'Introduction', 'type' => 'textarea', 'max' => 500],
                ['key' => 'form_heading', 'group' => 'Page text', 'label' => 'Heading above the form', 'type' => 'text', 'required' => true, 'max' => 120],
                ['key' => 'details_heading', 'group' => 'Page text', 'label' => 'Heading above the contact details', 'type' => 'text', 'required' => true, 'max' => 120,
                    'hint' => 'The address, email, phone and map are edited in Site settings.'],
                ['key' => 'success_message', 'group' => 'Page text', 'label' => 'Message shown after the form is sent', 'type' => 'textarea', 'required' => true, 'max' => 300],
            ],
            default => [],
        };
    }

    public static function defaults(string $slug): array
    {
        return match ($slug) {
            'home' => [
                // Slides and featured products live in their own tables; listed here so the editor knows the fields.
                'slides' => [],
                'featured_product_ids' => [],
                'categories_heading' => 'Browse by category',
                'categories_intro' => 'Boards and modules for every stage of an embedded design.',
                'featured_heading' => 'Featured products',
                'featured_intro' => 'A selection of our most popular boards.',
                'services_heading' => 'Engineering services',
                'services_intro' => 'Need something that is not in the catalogue? Our team designs custom hardware, firmware and software from concept to production.',
                'why_heading' => 'Why EdgeTinker',
                'why' => [
                    ['title' => 'Designed by engineers', 'text' => 'Every board is designed, laid out and tested in-house by the people who support it.'],
                    ['title' => 'Documented properly', 'text' => 'Pinouts, schematics, datasheets and firmware guides are published in our wiki for every product.'],
                    ['title' => 'Built to integrate', 'text' => 'Standard headers, sensible footprints and open examples make our boards easy to design into your product.'],
                    ['title' => 'Support that answers', 'text' => 'Questions go to engineers who know the hardware, not a ticket queue.'],
                ],
                'contact_heading' => 'Have a project in mind?',
                'contact_text' => 'Tell us what you are building. We will help you pick the right board or scope a custom design.',
                'partners_heading' => 'Our partners',
                'partners' => [],
            ],
            'services' => [
                'heading' => 'Engineering services',
                'intro' => 'EdgeTinker designs electronics products from first idea to production. You can hand us a whole product, or bring us in for one part of it: a board, the firmware, an enclosure, or the app that goes with it.',
                'services' => [
                    ['icon' => 'cpu', 'title' => 'Custom Electronics Development', 'text' => 'We turn a product idea or a set of requirements into working hardware, and carry it through prototyping to a design that is ready to manufacture.',
                        'deliverables' => ['Requirements and architecture document', 'Working prototypes', 'Test report', 'Production-ready design files']],
                    ['icon' => 'layers', 'title' => 'PCB Design', 'text' => 'Schematic capture and multi-layer PCB layout, including high-speed, RF and power designs, laid out for manufacture and assembly.',
                        'deliverables' => ['Schematics', 'PCB layout and Gerber files', 'Bill of materials', 'Assembly drawings']],
                    ['icon' => 'code', 'title' => 'Firmware Development', 'text' => 'Reliable embedded firmware for microcontrollers and wireless modules, from bare-metal drivers to connected applications with over-the-air updates.',
                        'deliverables' => ['Source code with build instructions', 'Hardware drivers', 'Bootloader and update mechanism', 'Test firmware for production']],
                    ['icon' => 'box', 'title' => 'Enclosure Design', 'text' => 'Enclosures designed around your board: 3D-printed for prototypes and short runs, or designed for injection moulding.',
                        'deliverables' => ['3D CAD models', 'Printed prototypes', 'Manufacturing drawings']],
                    ['icon' => 'monitor', 'title' => 'Web Application Development', 'text' => 'Dashboards and cloud back ends that collect data from your devices, show it clearly and let you manage a fleet.',
                        'deliverables' => ['Web dashboard', 'Device and user management', 'API documentation', 'Deployment guide']],
                    ['icon' => 'smartphone', 'title' => 'Mobile App Development', 'text' => 'Companion apps for iOS and Android that set up and control your device over Bluetooth or Wi-Fi.',
                        'deliverables' => ['iOS and Android apps', 'Device pairing and setup flow', 'App store submission']],
                ],
                'process_heading' => 'How we work',
                'process' => [
                    ['title' => 'Discuss', 'text' => 'We talk through what you need, the constraints and the budget.'],
                    ['title' => 'Propose', 'text' => 'You receive a written scope, timeline and fixed quotation.'],
                    ['title' => 'Design and prototype', 'text' => 'We build and test, with regular reviews so there are no surprises.'],
                    ['title' => 'Hand over', 'text' => 'You get the design files, documentation and support into production.'],
                ],
                'cta_heading' => 'Tell us about your project',
                'cta_text' => 'A short description is enough to start. We reply to every enquiry.',
                'cta_label' => 'Enquire about a project',
            ],
            'contact' => [
                'heading' => 'Contact us',
                'intro' => 'Questions about a product, a quotation, or a custom design? Send us a message and an engineer will reply.',
                'form_heading' => 'Send us a message',
                'details_heading' => 'Contact details',
                'success_message' => 'Thank you. Your message has been sent and we will reply by email.',
            ],
            default => [],
        };
    }
}
