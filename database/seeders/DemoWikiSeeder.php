<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\WikiPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample wiki content: the standard page set for three demo products.
 * All technical details are illustrative placeholders, not real documentation.
 */
class DemoWikiSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['ET-DEV-S3-01', 'ET-PWR-BK-0503', 'ET-SNS-ENV-01'] as $sku) {
            $product = Product::with('specs')->where('sku', $sku)->first();
            if (! $product) {
                continue;
            }

            foreach (WikiPage::STANDARD_PAGES as $order => $title) {
                WikiPage::updateOrCreate(
                    ['product_id' => $product->id, 'slug' => Str::slug(str_replace('&', 'and', $title))],
                    ['title' => $title, 'sort_order' => $order + 1, 'body' => $this->body($title, $product)],
                );
            }
        }
    }

    private function body(string $title, Product $product): string
    {
        $name = $product->name;
        $sku = $product->sku;
        $specs = $product->specs->map(fn ($spec) => "| {$spec->label} | {$spec->value} |")->implode("\n");
        $features = collect($product->key_features ?? [])->map(fn ($feature) => "- $feature")->implode("\n");

        return match ($title) {
            'Overview' => <<<MD
            > [!NOTE]
            > This is sample documentation generated for the demo site. Replace it in Content Studio.

            ## Product description

            {$product->short_description}

            The **$name** (part number `$sku`) is part of the EdgeTinker range. This page gives a short introduction; the other pages in this section cover setup, pinout, schematics and firmware.

            ## Product features

            $features

            ## Specifications

            | Parameter | Value |
            |---|---|
            $specs

            ## What is in the box

            - 1 × $name
            - 2 × pin header strips (not soldered)
            MD,

            'Quick Start' => <<<MD
            This guide takes you from unpacking the $name to a first working test.

            ## What you need

            - The $name
            - A USB-C cable or a suitable DC supply
            - A computer with a serial terminal

            ## Connect the board

            1. Check the supply voltage against the [Datasheet](datasheet).
            2. Connect power.
            3. The power LED lights when the board is running.

            > [!WARNING]
            > Do not exceed the maximum input voltage. Reversed polarity can permanently damage the board.

            ## Run a first test

            Open a serial terminal at 115200 baud and send:

            ```text
            AT
            ```

            The board replies with:

            ```text
            OK
            ```

            > [!TIP]
            > If nothing appears, check that the correct serial port is selected and try another cable. Some USB cables carry power only.

            ## Next steps

            - [Pinout](pinout)
            - [Firmware & Software](firmware-and-software)
            MD,

            'Datasheet' => <<<MD
            ## Electrical characteristics

            | Parameter | Value |
            |---|---|
            $specs

            ## Absolute maximum ratings

            | Parameter | Min | Max | Unit |
            |---|---|---|---|
            | Operating temperature | −40 | +85 | °C |
            | Storage temperature | −40 | +125 | °C |
            | ESD (human body model) | – | 2 | kV |

            > [!WARNING]
            > Stresses beyond the absolute maximum ratings may cause permanent damage. These are illustrative demo values.

            ## Mechanical

            Board dimensions and mounting-hole positions are shown in the mechanical drawing on the [Downloads](downloads) page.
            MD,

            'Pinout' => <<<'MD'
            ## Header pinout

            | Pin | Name | Function |
            |---|---|---|
            | 1 | VIN | Supply input |
            | 2 | GND | Ground |
            | 3 | 3V3 | Regulated 3.3 V output |
            | 4 | SDA | I²C data |
            | 5 | SCL | I²C clock |
            | 6 | EN | Enable, active high |

            > [!NOTE]
            > Pin 1 is marked with a square pad on the board.

            ## Pin notes

            ### Power pins

            `VIN` accepts the input range given in the [Datasheet](datasheet). `3V3` can supply a small external load.

            ### Signal pins

            All signal pins use 3.3 V logic. They are **not** 5 V tolerant.
            MD,

            'Schematics' => <<<'MD'
            ## Schematic

            The full schematic is available as a PDF on the [Downloads](downloads) page.

            ## Design notes

            - The input is protected against reverse polarity.
            - Decoupling capacitors are placed next to every supply pin.
            - Test points are provided for the main supply rails.

            ## Revision history

            | Revision | Date | Changes |
            |---|---|---|
            | A | – | Initial release |
            MD,

            'Firmware & Software' => <<<'MD'
            ## Supported environments

            - Arduino IDE
            - PlatformIO
            - Vendor SDK

            ## Example

            ```cpp
            #include <Arduino.h>

            void setup() {
              Serial.begin(115200);
              pinMode(LED_BUILTIN, OUTPUT);
            }

            void loop() {
              digitalWrite(LED_BUILTIN, HIGH);
              delay(500);
              digitalWrite(LED_BUILTIN, LOW);
              delay(500);
            }
            ```

            ## Updating the firmware

            1. Hold the **BOOT** button.
            2. Press and release **RESET**.
            3. Release **BOOT**. The board appears as a serial device ready for upload.

            > [!TIP]
            > Example projects are placeholders in this demo. Add links to your repositories here.
            MD,

            default => <<<MD
            ## Documents

            Files for the $name are listed in the **Documents & Downloads** tab of the product page once they have been uploaded in Content Studio.

            | Document | Format |
            |---|---|
            | Datasheet | PDF |
            | Schematic | PDF |
            | 3D model | STEP |

            > [!NOTE]
            > To add a download link to a wiki page, upload the file in the wiki editor and it inserts the link for you.
            MD,
        };
    }
}
