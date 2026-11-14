<?php

/*
| Public-site interface text. Kept here (not hard-coded in templates) so the
| site can be translated later by adding lang/{locale}/site.php.
*/
return [

    'nav' => [
        'home' => 'Home',
        'products' => 'Products',
        'services' => 'Services',
        'wiki' => 'Wiki',
        'contact' => 'Contact Us',
        'all_categories' => 'All Categories',
        'all_categories_tagline' => 'Browse the full EdgeTinker catalogue.',
        'open_menu' => 'Open menu',
        'close_menu' => 'Close menu',
        'main' => 'Main',
        'skip' => 'Skip to main content',
        'new_tab' => '(opens in a new tab)',
    ],

    'search' => [
        'label' => 'Search products',
        'placeholder' => 'Search products, SKUs…',
        'submit' => 'Search',
        'suggestions' => 'Suggested products',
        'no_suggestions' => 'No matching products. Press Enter to search everything.',
        'see_all' => 'See all results for',
    ],

    'listing' => [
        'all_products' => 'All products',
        'all_products_intro' => 'Every board and module in the EdgeTinker catalogue.',
        'search_heading' => 'Search results for “:query”',
        'search_heading_empty' => 'Search products',
        'count' => '{0} No products|{1} :count product|[2,*] :count products',
        'results_count' => '{0} No results|{1} :count result|[2,*] :count results',
        'did_you_mean' => 'Did you mean',
        'loose_notice' => 'No exact matches for “:query”. Showing the closest matches instead.',

        'filters' => 'Filters',
        'show_filters' => 'Filters',
        'close_filters' => 'Close filters',
        'category' => 'Category',
        'all_categories' => 'All categories',
        'tags' => 'Tags',
        'price' => 'Price (:currency)',
        'price_min' => 'Minimum price',
        'price_max' => 'Maximum price',
        'in_stock_only' => 'In stock only',
        'availability' => 'Availability',
        'apply' => 'Apply filters',
        'show_results' => 'Show results',
        'clear_all' => 'Clear all filters',
        'active_filters' => 'Active filters',
        'remove_filter' => 'Remove filter:',
        'price_chip' => 'Price: :range',

        'sort' => 'Sort by',
        'sorts' => [
            'relevance' => 'Relevance',
            'newest' => 'Newest',
            'price_asc' => 'Price: low to high',
            'price_desc' => 'Price: high to low',
            'name' => 'Name',
        ],

        'empty_title' => 'No products found',
        'empty_search' => 'Nothing matches “:query”. Check the spelling, try a shorter or more general term, or search by SKU.',
        'empty_filtered' => 'No products match the selected filters. Try removing one or two.',
        'empty_category' => 'There are no products in this category yet.',
        'browse_all' => 'Browse all products',
    ],

    'currency' => [
        'label' => 'Display currency',
        'switch_to' => 'Show prices in :currency',
    ],

    'stock' => [
        'in_stock' => 'In stock',
        'low_stock' => 'Low stock',
        'out_of_stock' => 'Out of stock',
        'coming_soon' => 'Coming soon',
    ],

    'product' => [
        'price_on_request' => 'Price on request',
        'sku' => 'SKU',
        'enquire' => 'Enquire / Where to buy',
        'enquiry_subject' => 'Enquiry: :name (:sku)',
        'price_note' => 'Price shown in :currency. Use the toggle in the header to switch.',
        'at_a_glance' => 'At a glance',
        'key_features' => 'Key features',
        'tags' => 'Tags',
        'tabs_label' => 'Product information',
        'overview' => 'Overview',
        'specifications' => 'Specifications',
        'documents' => 'Documents & Downloads',
        'parameter' => 'Parameter',
        'value' => 'Value',
        'no_description' => 'A full description has not been added yet.',
        'no_specs_title' => 'No specifications yet',
        'no_specs_text' => 'Specifications for this product have not been published.',
        'download' => 'Download',
        'no_documents_title' => 'No documents yet',
        'no_documents_text' => 'Datasheets, schematics, pinouts, 3D models and firmware for this product will be listed here.',
        'wiki_heading' => 'Documentation and guides',
        'wiki_text' => 'Pinout, schematics, quick start and firmware guides for this product are in the wiki.',
        'wiki_cta' => 'Open the wiki',
        'related' => 'Related products',
        'gallery' => 'Product images',
        'gallery_open' => 'View larger image',
        'gallery_show' => 'Show image :number',
        'gallery_close' => 'Close image viewer',
        'gallery_previous' => 'Previous image',
        'gallery_next' => 'Next image',
        'gallery_viewer' => 'Image viewer',
        'no_image' => 'No image available',
    ],

    'home' => [
        'slideshow_label' => 'Highlights',
        'slide_of' => 'Slide :current of :total',
        'previous_slide' => 'Previous slide',
        'next_slide' => 'Next slide',
        'go_to_slide' => 'Go to slide :number',
        'pause' => 'Pause slideshow',
        'play' => 'Play slideshow',

        'categories_heading' => 'Browse by category',
        'categories_intro' => 'Boards and modules for every stage of an embedded design.',

        'featured_heading' => 'Featured products',
        'featured_intro' => 'A selection of our most popular boards.',
        'featured_all' => 'View all products',
        'featured_empty_title' => 'No featured products yet',
        'featured_empty_text' => 'Mark products as featured in Content Studio and they will appear here.',

        'services_heading' => 'Engineering services',
        'services_intro' => 'Need something that is not in the catalogue? Our team designs custom hardware, firmware and software from concept to production.',
        'services_cta' => 'Explore our services',

        'why_heading' => 'Why EdgeTinker',
        'why' => [
            ['title' => 'Designed by engineers', 'text' => 'Every board is designed, laid out and tested in-house by the people who support it.'],
            ['title' => 'Documented properly', 'text' => 'Pinouts, schematics, datasheets and firmware guides are published in our wiki for every product.'],
            ['title' => 'Built to integrate', 'text' => 'Standard headers, sensible footprints and open examples make our boards easy to design into your product.'],
            ['title' => 'Support that answers', 'text' => 'Questions go to engineers who know the hardware, not a ticket queue.'],
        ],

        'contact_heading' => 'Have a project in mind?',
        'contact_text' => 'Tell us what you are building. We will help you pick the right board or scope a custom design.',
        'contact_cta' => 'Contact us',
        'contact_secondary' => 'Read the wiki',
    ],

    // Short service summaries used on the home page (the Services page expands on these).
    'services' => [
        ['icon' => 'cpu', 'title' => 'Custom Electronics Development', 'text' => 'From requirements to working prototypes and production-ready designs.'],
        ['icon' => 'layers', 'title' => 'PCB Design', 'text' => 'Schematic capture and multi-layer layout, designed for manufacture.'],
        ['icon' => 'code', 'title' => 'Firmware Development', 'text' => 'Reliable embedded firmware for microcontrollers and wireless modules.'],
        ['icon' => 'box', 'title' => 'Enclosure Design', 'text' => '3D-printed and injection-moulded enclosures that fit your board.'],
        ['icon' => 'monitor', 'title' => 'Web Application Development', 'text' => 'Dashboards and cloud back ends for connected devices.'],
        ['icon' => 'smartphone', 'title' => 'Mobile App Development', 'text' => 'Companion apps for iOS and Android over Bluetooth and Wi-Fi.'],
    ],

    'footer' => [
        'products' => 'Products',
        'company' => 'Company',
        'contact' => 'Contact',
        'follow' => 'Follow us',
        'rights' => 'All rights reserved.',
    ],

    'coming_soon' => [
        'title' => ':page is on its way',
        'text' => 'This page is built in Phase :phase of the project and is not available yet.',
        'back' => 'Back to home',
    ],

];
