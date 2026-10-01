<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::query()->orderBy('category_name')->get()->values();
        $user = User::where('username', 'admin')->first();

        if ($categories->isEmpty() || ! $user) {
            throw new RuntimeException('At least one category and the admin user are required.');
        }

        $itemsByCategory = [
            'Furniture and Fixtures' => [
                ['Teacher Desk', 3500, 'Wooden teacher desk'],
                ['Teacher Chair', 1200, 'Wooden teacher chair'],
                ['Student Armchair', 950, 'Right-handed student armchair'],
                ['Folding Table', 2800, 'Folding activity table'],
                ['Filing Cabinet', 6200, 'Four-drawer filing cabinet'],
                ['Bookshelf', 4100, 'Open metal bookshelf'],
                ['Conference Table', 12500, 'Large conference table'],
                ['Visitor Chair', 1350, 'Padded visitor chair'],
                ['Reception Counter', 18500, 'Front office reception counter'],
                ['Storage Cabinet', 7800, 'Lockable storage cabinet'],
                ['Whiteboard Stand', 2300, 'Mobile whiteboard stand'],
                ['Computer Table', 3200, 'Student computer table'],
                ['Printer Stand', 2500, 'Printer stand with shelf'],
                ['Steel Locker', 8900, 'Six-compartment steel locker'],
                ['Round Activity Table', 3600, 'Round table for group activities'],
                ['Staff Lounge Sofa', 14500, 'Three-seat lounge sofa'],
            ],
            'ICT Equipment' => [
                ['Laptop', 42000, 'Portable computer for classroom instruction'],
                ['Desktop Computer', 38000, 'Desktop computer with monitor and keyboard'],
                ['LCD Projector', 28000, 'Ceiling-mountable classroom projector'],
                ['Network Switch', 8500, 'Managed 24-port network switch'],
                ['Laser Printer', 12500, 'Monochrome network laser printer'],
                ['Document Camera', 16000, 'USB document camera for lessons'],
                ['Interactive Display', 78000, 'Touch-enabled classroom display'],
                ['Tablet', 18000, 'Wi-Fi tablet for instructional use'],
                ['Wireless Access Point', 7200, 'Dual-band wireless access point'],
                ['UPS', 6500, 'Uninterruptible power supply'],
                ['Web Camera', 3200, 'USB high-definition web camera'],
                ['External Hard Drive', 4800, 'Portable data storage drive'],
                ['Barcode Scanner', 3900, 'USB handheld barcode scanner'],
            ],
            'Office Equipment' => [
                ['Photocopier', 68000, 'High-volume office photocopier'],
                ['Document Shredder', 9500, 'Cross-cut office paper shredder'],
                ['Laminator', 5200, 'A3 thermal laminating machine'],
                ['Binding Machine', 7400, 'Manual document binding machine'],
                ['Digital Voice Recorder', 4600, 'Portable digital voice recorder'],
                ['Cash Counting Machine', 11500, 'Banknote counting machine'],
                ['Paper Cutter', 2800, 'Heavy-duty guillotine paper cutter'],
                ['Fax Machine', 8900, 'Multifunction fax machine'],
                ['Electric Typewriter', 14500, 'Electronic office typewriter'],
                ['Mailing Scale', 3600, 'Digital parcel and mailing scale'],
                ['Label Printer', 6300, 'Desktop label printer'],
                ['Air Purifier', 9800, 'Room air filtration unit'],
            ],
            'Laboratory Equipment' => [
                ['Compound Microscope', 18500, 'Binocular compound microscope'],
                ['Digital Balance', 7200, 'Precision digital laboratory balance'],
                ['Centrifuge', 22500, 'Benchtop laboratory centrifuge'],
                ['Hot Plate', 4800, 'Adjustable laboratory hot plate'],
                ['pH Meter', 6500, 'Digital pH meter with probe'],
                ['Laboratory Oven', 32000, 'Temperature-controlled drying oven'],
                ['Spectrophotometer', 48500, 'Visible-light laboratory spectrophotometer'],
                ['Autoclave', 56000, 'Tabletop steam sterilizer'],
                ['Water Bath', 8900, 'Digital laboratory water bath'],
                ['Binocular Microscope', 22000, 'Student binocular microscope'],
                ['Incubator', 28500, 'Laboratory temperature incubator'],
                ['Conductivity Meter', 5800, 'Portable conductivity meter'],
            ],
            'Learning Resources' => [
                ['Mathematics Workbook', 120, 'Student mathematics practice workbook'],
                ['Science Textbook', 480, 'Junior high school science textbook'],
                ['Filipino Reading Set', 950, 'Set of graded Filipino reading books'],
                ['World Atlas', 650, 'Student reference world atlas'],
                ['Dictionary', 420, 'Filipino-English learner dictionary'],
                ['Geometry Set', 180, 'Classroom geometry drawing set'],
                ['Periodic Table Chart', 250, 'Laminated periodic table wall chart'],
                ['Flash Card Set', 160, 'Foundational literacy flash cards'],
                ['Globe', 2400, 'Desktop physical and political globe'],
                ['Reference Encyclopedia', 1850, 'General reference encyclopedia volume'],
                ['Reading Module', 95, 'Printed reading and comprehension module'],
                ['Map Set', 780, 'Classroom geography map set'],
            ],
            'Sports Equipment' => [
                ['Basketball', 1800, 'Official-size rubber basketball'],
                ['Volleyball', 1450, 'Regulation indoor volleyball'],
                ['Soccer Ball', 2100, 'Size 5 training soccer ball'],
                ['Badminton Racket', 950, 'Aluminum badminton racket'],
                ['Table Tennis Set', 1250, 'Two paddles and practice balls'],
                ['Jump Rope', 180, 'Adjustable exercise jump rope'],
                ['Shot Put', 1650, 'Competition training shot put'],
                ['Relay Baton Set', 720, 'Set of four aluminum relay batons'],
                ['Volleyball Net', 3200, 'Outdoor volleyball net with cables'],
                ['Soccer Goal Net', 2800, 'Replacement net for school soccer goal'],
                ['Whistle', 220, 'Pealess referee whistle'],
                ['Gymnastics Mat', 5400, 'Foldable padded exercise mat'],
            ],
            'Tools and Maintenance Equipment' => [
                ['Cordless Drill', 6800, '18V cordless drill with battery'],
                ['Angle Grinder', 4200, 'Handheld electric angle grinder'],
                ['Circular Saw', 7500, 'Portable electric circular saw'],
                ['Pressure Washer', 12800, 'Electric pressure washer for facilities'],
                ['Lawn Mower', 18500, 'Push-type grass lawn mower'],
                ['Welding Machine', 16200, 'Portable inverter welding machine'],
                ['Air Compressor', 14500, 'Portable shop air compressor'],
                ['Bench Vise', 2800, 'Heavy-duty workshop bench vise'],
                ['Electric Hedge Trimmer', 6200, 'Corded electric hedge trimmer'],
                ['Water Pump', 8900, 'Electric utility water pump'],
                ['Portable Generator', 26500, 'Portable gasoline-powered generator'],
                ['Digital Multimeter', 1950, 'Digital electrical testing multimeter'],
            ],
            'Other Equipment' => [
                ['First Aid Kit', 1250, 'Wall-mountable school first aid kit'],
                ['Megaphone', 2800, 'Portable rechargeable voice amplifier'],
                ['Digital Clock', 950, 'Large-display wall clock'],
                ['Fire Extinguisher', 3200, 'ABC dry chemical fire extinguisher'],
                ['Water Dispenser', 7800, 'Hot and cold water dispenser'],
                ['Electric Fan', 2450, 'Oscillating stand fan'],
                ['Emergency Light', 1350, 'Rechargeable emergency lighting unit'],
                ['Portable PA System', 12500, 'Portable public address speaker system'],
                ['Wall-Mount First Aid Cabinet', 2100, 'Lockable first aid storage cabinet'],
                ['Digital Thermometer', 580, 'Non-contact infrared thermometer'],
                ['Folding Ladder', 3900, 'Five-step aluminum folding ladder'],
                ['Water Filter', 4650, 'Countertop water filtration system'],
            ],
        ];
        $itemsWithSerialNumbers = [
            'Laptop', 'Desktop Computer', 'LCD Projector', 'Network Switch', 'Laser Printer',
            'Document Camera', 'Interactive Display', 'Tablet', 'Wireless Access Point', 'UPS',
            'External Hard Drive', 'Barcode Scanner', 'Photocopier', 'Document Shredder',
            'Cash Counting Machine', 'Fax Machine', 'Electric Typewriter', 'Label Printer',
            'Compound Microscope', 'Digital Balance', 'Centrifuge', 'pH Meter', 'Laboratory Oven',
            'Spectrophotometer', 'Autoclave', 'Incubator', 'Cordless Drill', 'Angle Grinder',
            'Circular Saw', 'Pressure Washer', 'Lawn Mower', 'Welding Machine', 'Air Compressor',
            'Electric Hedge Trimmer', 'Water Pump', 'Portable Generator', 'Digital Multimeter',
        ];

        $categoryItemCounts = [];
        $dateAcquired = '2026-08-26';

        for ($index = 1; $index <= 100; $index++) {
            $category = $categories[($index - 1) % $categories->count()];
            $categoryItemIndex = $categoryItemCounts[$category->category_id] ?? 0;
            $categoryItemCounts[$category->category_id] = $categoryItemIndex + 1;
            $categoryItems = $itemsByCategory[$category->category_name] ?? [
                [$category->category_name . ' Item', 1000, 'General school inventory item'],
            ];
            [$itemName, $unitCost, $description] = $categoryItems[$categoryItemIndex % count($categoryItems)];
            $repeatNumber = intdiv($categoryItemIndex, count($categoryItems));
            $inventoryNumber = sprintf('INV-DEMO-%03d', $index);
            $lifespanYears = $category->default_lifespan_years ?? 5;
            $hasSerialNumber = $category->requires_serial_number
                && in_array($itemName, $itemsWithSerialNumbers, true);

            if ($repeatNumber > 0) {
                $itemName .= ' ' . ($repeatNumber + 1);
            }

            Inventory::updateOrCreate(
                ['inventory_item_no' => $inventoryNumber],
                [
                    'item_name' => $itemName,
                    'description' => $description,
                    'category_id' => $category->category_id,
                    'user_id' => $user->id,
                    'building' => 'Main Building',
                    'room' => 'Property Office',
                    'unit' => $category->category_name === 'Learning Resources' ? 'copy' : 'piece',
                    'quantity' => $hasSerialNumber ? 1 : (($index % 10) + 1),
                    'unit_cost' => $unitCost,
                    'ics_no' => sprintf('ICS-DEMO-%03d', $index),
                    'date_acquired' => $dateAcquired,
                    'lifespan_years' => $lifespanYears,
                    'expected_end_date' => $lifespanYears === null
                        ? null
                        : Carbon::parse($dateAcquired)->addYears($lifespanYears)->toDateString(),
                    'status' => 'available',
                    'qr_code' => $category->requires_qr_code ? 'dnhs_qr_demo_' . sprintf('%06d', $index) : null,
                    'serial_number' => $hasSerialNumber
                        ? sprintf('SERIAL-DEMO-%03d', $index)
                        : null,
                    'assigned_to_user_id' => null,
                ]
            );
        }
    }
}
