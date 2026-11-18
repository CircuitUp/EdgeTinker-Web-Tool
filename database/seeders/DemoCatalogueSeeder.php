<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Support\Banner\BannerConfig;
use App\Support\PlaceholderArt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Demo catalogue: 5 categories, 12 products, 3 hero slides.
 * All products, prices and specifications here are illustrative demo data,
 * and every image is a generated, clearly labelled placeholder.
 */
class DemoCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        File::ensureDirectoryExists(public_path('placeholders'));

        $this->seedSlides();
        $categories = $this->seedCategories();
        $this->seedProducts($categories);
    }

    private function seedSlides(): void
    {
        $slides = [
            [
                'heading' => 'Embedded hardware, engineered to build on',
                'sub_text' => 'Development boards, power modules and sensor boards designed, tested and documented by engineers.',
                'button_label' => 'Browse products',
                'button_url' => '/products',
            ],
            [
                'heading' => 'Power your design with confidence',
                'sub_text' => 'Compact DC/DC converters and battery chargers with full schematics and layout guidance.',
                'button_label' => 'See power boards',
                'button_url' => '/products/dc-dc-converter-boards',
            ],
            [
                'heading' => 'From idea to finished product',
                'sub_text' => 'Custom electronics, PCB design, firmware and enclosures from one engineering team.',
                'button_label' => 'Our services',
                'button_url' => '/services',
            ],
        ];

        $rows = [];
        foreach ($slides as $i => $slide) {
            $path = 'placeholders/slide-'.($i + 1).'.svg';
            File::put(public_path($path), PlaceholderArt::slide($i));
            $rows[] = $slide + ['image_path' => $path, 'image_alt' => ''];
        }

        // The banner in its default design (the same as the original hard-coded one).
        Banner::updateOrCreate(['key' => Banner::HOME], ['config' => BannerConfig::fromLegacySlides($rows)]);
    }

    /** @return array<string, Category> keyed by slug */
    private function seedCategories(): array
    {
        $rows = [
            ['Development Boards', 'Microcontroller boards ready for prototyping and production.'],
            ['DC/DC Converter Boards', 'Efficient buck, boost and buck-boost power modules.'],
            ['Battery Charging Boards', 'Li-ion, LiFePO4 and solar charging with protection built in.'],
            ['Communication Boards', 'LoRa, RS-485, CAN and cellular connectivity for your project.'],
            ['Sensor Boards', 'Calibrated environmental, motion and current sensing breakouts.'],
        ];

        $categories = [];
        foreach ($rows as $i => [$name, $tagline]) {
            $slug = Str::slug(str_replace('/', '-', $name));
            $path = "placeholders/category-$slug.svg";
            File::put(public_path($path), PlaceholderArt::category($name, $i));

            $categories[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'tagline' => $tagline, 'image_path' => $path, 'sort_order' => $i + 1],
            );
        }

        return $categories;
    }

    /** @param array<string, Category> $categories */
    private function seedProducts(array $categories): void
    {
        foreach ($this->products() as $i => $row) {
            $slug = Str::slug($row['name']);
            // Three placeholder views per product so the gallery has something to switch between.
            $views = ['Front view', 'Angled view', 'Alternate view'];
            $paths = [];
            foreach ($views as $view => $caption) {
                $paths[$view] = "placeholders/product-$slug".($view === 0 ? '' : '-'.($view + 1)).'.svg';
                File::put(public_path($paths[$view]), PlaceholderArt::product($row['name'], $row['sku'], $i, $view));
            }

            $product = Product::updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'category_id' => $categories[$row['category']]->id,
                    'name' => $row['name'],
                    'slug' => $slug,
                    'short_description' => $row['short'],
                    'description' => $row['description'],
                    'key_features' => $row['features'],
                    'price_usd' => $row['usd'],
                    'price_lkr' => $row['lkr'],
                    'stock_status' => $row['stock'],
                    'is_featured' => $row['featured'] ?? false,
                    'seo_title' => $row['name'].' | EdgeTinker',
                    'seo_description' => $row['short'],
                ],
            );

            $product->images()->delete();
            foreach ($paths as $view => $path) {
                $product->images()->create([
                    'path' => $path,
                    'alt' => $row['name'].', '.strtolower($views[$view]).' (placeholder image)',
                    'is_thumbnail' => $view === 0,
                    'sort_order' => $view + 1,
                ]);
            }

            $product->specs()->delete();
            $order = 0;
            foreach ($row['specs'] as $label => $value) {
                $product->specs()->create(['label' => $label, 'value' => $value, 'sort_order' => ++$order]);
            }

            $tagIds = collect($row['tags'])->map(
                fn (string $name) => Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id
            );
            $product->tags()->sync($tagIds);
        }
    }

    private function products(): array
    {
        return [
            // ── Development Boards ────────────────────────────────────────────
            [
                'category' => 'development-boards',
                'name' => 'EdgeCore ESP32-S3 Dev Board',
                'sku' => 'ET-DEV-S3-01',
                'short' => 'Dual-core Wi-Fi and Bluetooth LE development board with USB-C and LiPo charging.',
                'description' => '<p>The EdgeCore ESP32-S3 is a compact development board built around the ESP32-S3 module. It breaks out every usable GPIO on breadboard-friendly headers and includes an on-board LiPo charger, so a prototype can move from the bench to the field without extra hardware.</p><p>It is supported by the Arduino core, ESP-IDF and MicroPython.</p>',
                'features' => ['ESP32-S3 dual-core, 240 MHz', '8 MB flash, 2 MB PSRAM', 'USB-C with native USB', 'On-board LiPo charger', 'Breadboard-friendly 2.54 mm headers'],
                'specs' => ['Microcontroller' => 'ESP32-S3-WROOM-1', 'Flash / PSRAM' => '8 MB / 2 MB', 'Wireless' => 'Wi-Fi 802.11 b/g/n, Bluetooth 5 LE', 'Input voltage' => '5 V (USB-C) or 3.7 V LiPo', 'GPIO' => '36', 'Dimensions' => '52 × 25 mm'],
                'tags' => ['ESP32', 'Wi-Fi', 'Bluetooth', 'USB-C'],
                'usd' => 14.90, 'lkr' => 4500, 'stock' => 'in_stock', 'featured' => true,
            ],
            [
                'category' => 'development-boards',
                'name' => 'EdgeCore STM32G4 Motor Dev Board',
                'sku' => 'ET-DEV-G4-01',
                'short' => 'STM32G4 board with high-resolution timers and CAN FD for motor-control prototyping.',
                'description' => '<p>A development board for motor-control and digital-power projects. The STM32G4 provides high-resolution PWM timers, fast ADCs and CAN FD, with an on-board debugger for drag-and-drop programming.</p>',
                'features' => ['STM32G474 Cortex-M4F, 170 MHz', 'On-board SWD debugger', 'CAN FD transceiver', '3 op-amp and comparator inputs broken out', 'Arduino-style shield headers'],
                'specs' => ['Microcontroller' => 'STM32G474RET6', 'Flash / RAM' => '512 KB / 128 KB', 'Interfaces' => 'CAN FD, USB, UART, SPI, I²C', 'Input voltage' => '5 V USB or 7–24 V DC', 'Dimensions' => '70 × 55 mm'],
                'tags' => ['STM32', 'CAN', 'Motor control'],
                'usd' => 24.50, 'lkr' => 7400, 'stock' => 'low_stock', 'featured' => true,
            ],
            [
                'category' => 'development-boards',
                'name' => 'EdgeCore RP2040 Mini',
                'sku' => 'ET-DEV-RP-01',
                'short' => 'Thumb-sized RP2040 board with castellated edges for soldering straight onto your PCB.',
                'description' => '<p>A small RP2040 board with castellated pads, so it works on a breadboard during development and as a surface-mount module in the final design.</p>',
                'features' => ['RP2040 dual-core Cortex-M0+', '4 MB flash', 'Castellated edge pads', 'USB-C', 'RGB status LED'],
                'specs' => ['Microcontroller' => 'RP2040', 'Flash' => '4 MB', 'GPIO' => '20', 'Input voltage' => '5 V (USB-C)', 'Dimensions' => '21 × 18 mm'],
                'tags' => ['RP2040', 'USB-C', 'MicroPython'],
                'usd' => 6.90, 'lkr' => 2100, 'stock' => 'in_stock',
            ],

            // ── DC/DC Converter Boards ────────────────────────────────────────
            [
                'category' => 'dc-dc-converter-boards',
                'name' => 'PowerStep 5V 3A Buck Converter',
                'sku' => 'ET-PWR-BK-0503',
                'short' => 'Synchronous step-down module: 7–36 V in, fixed 5 V out at up to 3 A.',
                'description' => '<p>A fixed-output buck converter for powering 5 V logic and single-board computers from 12 V or 24 V rails. Synchronous rectification keeps it cool without a heatsink across most of its load range.</p>',
                'features' => ['7–36 V input range', 'Fixed 5 V output, 3 A continuous', 'Up to 94% efficiency', 'Over-current and thermal protection', 'Screw terminals and solder pads'],
                'specs' => ['Topology' => 'Synchronous buck', 'Input voltage' => '7–36 V DC', 'Output' => '5 V, 3 A', 'Switching frequency' => '500 kHz', 'Ripple' => '< 30 mV p-p', 'Dimensions' => '28 × 20 mm'],
                'tags' => ['Buck', '5V', 'Power'],
                'usd' => 5.50, 'lkr' => 1650, 'stock' => 'in_stock', 'featured' => true,
            ],
            [
                'category' => 'dc-dc-converter-boards',
                'name' => 'PowerStep Adjustable Buck-Boost',
                'sku' => 'ET-PWR-BB-ADJ',
                'short' => 'Buck-boost module with 1.8–12 V adjustable output from a 2.7–18 V input.',
                'description' => '<p>Holds a steady output whether the input is above or below it, which makes it a good fit for battery-powered designs where the cell voltage crosses the rail voltage during discharge.</p>',
                'features' => ['2.7–18 V input', '1.8–12 V adjustable output', '2 A output current', 'Seamless buck/boost transition', 'Enable pin for low-power shutdown'],
                'specs' => ['Topology' => 'Four-switch buck-boost', 'Input voltage' => '2.7–18 V DC', 'Output' => '1.8–12 V adjustable, 2 A', 'Quiescent current' => '45 µA', 'Dimensions' => '25 × 18 mm'],
                'tags' => ['Buck-boost', 'Adjustable', 'Power', 'Battery'],
                'usd' => 8.90, 'lkr' => 2700, 'stock' => 'in_stock',
            ],
            [
                'category' => 'dc-dc-converter-boards',
                'name' => 'PowerStep Isolated 24V to 5V',
                'sku' => 'ET-PWR-ISO-2405',
                'short' => 'Isolated 5 V, 1 A supply for industrial 24 V systems, with 1.5 kV isolation.',
                'description' => '<p>An isolated DC/DC module for powering sensors and communication interfaces from an industrial 24 V supply while breaking ground loops.</p>',
                'features' => ['18–36 V input', 'Isolated 5 V, 1 A output', '1.5 kV DC isolation', 'Short-circuit protection', 'DIN-rail adapter available'],
                'specs' => ['Topology' => 'Isolated flyback', 'Input voltage' => '18–36 V DC', 'Output' => '5 V, 1 A', 'Isolation' => '1.5 kV DC', 'Dimensions' => '40 × 25 mm'],
                'tags' => ['Isolated', '5V', 'Industrial', 'Power'],
                'usd' => 12.00, 'lkr' => 3600, 'stock' => 'coming_soon',
            ],

            // ── Battery Charging Boards ───────────────────────────────────────
            [
                'category' => 'battery-charging-boards',
                'name' => 'ChargeMate 1S Li-ion USB-C Charger',
                'sku' => 'ET-CHG-1S-C',
                'short' => 'Single-cell Li-ion charger with USB-C input, power-path and cell protection.',
                'description' => '<p>Charges one Li-ion or LiPo cell from USB-C and powers the load at the same time. Battery protection is built in, so a bare cell can be connected directly.</p>',
                'features' => ['USB-C input, up to 1 A charge current', 'Power-path: run the load while charging', 'Over-charge, over-discharge and short-circuit protection', 'Charge and done status LEDs', 'JST-PH battery connector'],
                'specs' => ['Battery' => '1S Li-ion / LiPo (4.2 V)', 'Charge current' => '100 mA – 1 A (resistor set)', 'Input' => '5 V USB-C', 'Protection' => 'OVP, UVP, OCP, short circuit', 'Dimensions' => '24 × 17 mm'],
                'tags' => ['Li-ion', 'USB-C', 'Battery', 'Charger'],
                'usd' => 4.20, 'lkr' => 1250, 'stock' => 'in_stock', 'featured' => true,
            ],
            [
                'category' => 'battery-charging-boards',
                'name' => 'ChargeMate Solar MPPT Charger',
                'sku' => 'ET-CHG-MPPT-01',
                'short' => 'MPPT solar charger for 1S Li-ion or LiFePO4 from 5–20 V panels.',
                'description' => '<p>Tracks the maximum power point of small solar panels to get the most charge into a single cell. Intended for remote sensor nodes and outdoor data loggers.</p>',
                'features' => ['5–20 V solar panel input', 'Maximum power point tracking', 'Li-ion or LiFePO4 selectable', 'Up to 2 A charge current', 'Battery temperature sensor input'],
                'specs' => ['Battery' => '1S Li-ion or LiFePO4', 'Panel voltage' => '5–20 V', 'Charge current' => 'Up to 2 A', 'Efficiency' => 'Up to 95%', 'Dimensions' => '38 × 28 mm'],
                'tags' => ['Solar', 'MPPT', 'LiFePO4', 'Battery', 'Charger'],
                'usd' => 16.50, 'lkr' => 4950, 'stock' => 'low_stock',
            ],

            // ── Communication Boards ──────────────────────────────────────────
            [
                'category' => 'communication-boards',
                'name' => 'LinkWave LoRa Transceiver Board',
                'sku' => 'ET-COM-LORA-868',
                'short' => 'Long-range sub-GHz LoRa transceiver breakout with SMA antenna connector.',
                'description' => '<p>A LoRa transceiver breakout for long-range, low-power links. It connects over SPI and works with common LoRa and LoRaWAN libraries.</p>',
                'features' => ['SX1262 LoRa transceiver', 'Up to +22 dBm output power', 'SPI interface, 3.3 V logic', 'SMA and u.FL antenna options', 'Low sleep current'],
                'specs' => ['Transceiver' => 'SX1262', 'Frequency' => '863–870 MHz', 'Output power' => 'Up to +22 dBm', 'Sensitivity' => 'Down to −148 dBm', 'Interface' => 'SPI', 'Dimensions' => '30 × 22 mm'],
                'tags' => ['LoRa', 'Wireless', 'IoT'],
                'usd' => 11.90, 'lkr' => 3600, 'stock' => 'in_stock', 'featured' => true,
            ],
            [
                'category' => 'communication-boards',
                'name' => 'LinkWave Isolated RS-485 Board',
                'sku' => 'ET-COM-RS485-ISO',
                'short' => 'Galvanically isolated RS-485 transceiver with automatic direction control.',
                'description' => '<p>Adds a robust, isolated RS-485 port to any 3.3 V or 5 V microcontroller. Automatic direction control means no extra GPIO is needed for half-duplex Modbus links.</p>',
                'features' => ['2.5 kV galvanic isolation', 'Automatic TX/RX direction control', 'Switchable 120 Ω termination', '3.3 V and 5 V logic compatible', 'TVS surge protection'],
                'specs' => ['Interface' => 'UART to RS-485 (half duplex)', 'Data rate' => 'Up to 500 kbps', 'Isolation' => '2.5 kV RMS', 'Logic voltage' => '3.3–5 V', 'Dimensions' => '36 × 20 mm'],
                'tags' => ['RS-485', 'Modbus', 'Isolated', 'Industrial'],
                'usd' => 9.50, 'lkr' => 2850, 'stock' => 'out_of_stock',
            ],

            // ── Sensor Boards ─────────────────────────────────────────────────
            [
                'category' => 'sensor-boards',
                'name' => 'SenseKit Environmental Sensor Board',
                'sku' => 'ET-SNS-ENV-01',
                'short' => 'Temperature, humidity and barometric pressure on one I²C breakout.',
                'description' => '<p>Measures temperature, relative humidity and barometric pressure over I²C. Daisy-chain connectors make it quick to add to an existing sensor bus.</p>',
                'features' => ['Temperature, humidity and pressure', 'I²C with selectable address', 'Daisy-chain 4-pin connectors', '1.8–5 V supply', 'Low-power sleep mode'],
                'specs' => ['Temperature' => '−40 to +85 °C, ±0.5 °C', 'Humidity' => '0–100% RH, ±3%', 'Pressure' => '300–1100 hPa, ±1 hPa', 'Interface' => 'I²C', 'Supply' => '1.8–5 V', 'Dimensions' => '18 × 18 mm'],
                'tags' => ['I2C', 'Temperature', 'Humidity', 'IoT'],
                'usd' => 7.40, 'lkr' => 2250, 'stock' => 'in_stock', 'featured' => true,
            ],
            [
                'category' => 'sensor-boards',
                'name' => 'SenseKit 6-Axis IMU Board',
                'sku' => 'ET-SNS-IMU-6X',
                'short' => 'Low-noise 3-axis accelerometer and 3-axis gyroscope with I²C and SPI.',
                'description' => '<p>A six-axis motion sensor for orientation, vibration and gesture sensing, with interrupt outputs for wake-on-motion applications.</p>',
                'features' => ['3-axis accelerometer and 3-axis gyroscope', 'I²C and SPI interfaces', 'Two programmable interrupt pins', 'Wake-on-motion', '1.8–5 V supply'],
                'specs' => ['Accelerometer range' => '±2 / ±4 / ±8 / ±16 g', 'Gyroscope range' => '±125 to ±2000 °/s', 'Interface' => 'I²C, SPI', 'Supply' => '1.8–5 V', 'Dimensions' => '16 × 16 mm'],
                'tags' => ['I2C', 'IMU', 'Motion'],
                'usd' => 8.20, 'lkr' => 2500, 'stock' => 'in_stock',
            ],
        ];
    }
}
