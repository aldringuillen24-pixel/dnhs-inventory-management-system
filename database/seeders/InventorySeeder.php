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
        $categories = Category::query()->orderBy('category_id')->get()->values();
        $user = User::where('username', 'admin')->first();

        if ($categories->isEmpty() || ! $user) {
            throw new RuntimeException('At least one category and the admin user are required.');
        }

        $itemsByCategory = [
            'Furniture' => [
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
            'ICT / Computer Equipment' => [
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
            'Library Resources' => [
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
            'Industrial / Workshop Equipment' => [
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
            'Consumables' => [
                ['Bond Paper', 320, 'Ream of A4 bond paper'],
                ['Printer Ink', 850, 'Black printer ink cartridge'],
                ['Cleaning Detergent', 180, 'All-purpose cleaning detergent'],
                ['Trash Bag', 120, 'Pack of disposable trash bags'],
                ['Ballpoint Pen', 45, 'Box of ballpoint pens'],
                ['Folder', 95, 'Manila document folder'],
            ],
            'Audio-Visual Equipment' => [
                ['Projector', 28000, 'Classroom multimedia projector'],
                ['Speaker', 6500, 'Portable classroom speaker'],
                ['Microphone', 2800, 'Wired handheld microphone'],
                ['LED TV', 24500, 'Classroom display television'],
            ],
            'Electrical Equipment' => [
                ['Electric Fan', 2450, 'Oscillating stand fan'],
                ['Air Conditioner', 38500, 'Split-type classroom air conditioner'],
                ['Extension Cord', 650, 'Heavy-duty extension cord'],
                ['Power Strip', 780, 'Surge-protected power strip'],
            ],
            'Classroom Equipment' => [
                ['Whiteboard', 4200, 'Wall-mounted classroom whiteboard'],
                ['Bulletin Board', 1800, 'Cork classroom bulletin board'],
                ['Lectern', 5600, 'Wooden classroom lectern'],
            ],
            'School Supplies' => [
                ['Stapler', 450, 'Desktop paper stapler'],
                ['Scissors', 180, 'Classroom scissors'],
                ['Marker', 95, 'Whiteboard marker'],
                ['Notebook', 85, 'Student composition notebook'],
            ],
            'Cleaning & Sanitation' => [
                ['Broom', 320, 'Soft-bristle cleaning broom'],
                ['Mop', 480, 'Cotton string mop with handle'],
                ['Trash Bin', 750, 'Heavy-duty trash bin'],
            ],
            'Safety & Emergency Equipment' => [
                ['Fire Extinguisher', 3200, 'ABC dry chemical fire extinguisher'],
                ['First Aid Kit', 1250, 'Wall-mountable school first aid kit'],
                ['Emergency Light', 1350, 'Rechargeable emergency lighting unit'],
            ],
            'Medical / Health Equipment' => [
                ['BP Monitor', 3800, 'Digital blood pressure monitor'],
                ['Thermometer', 580, 'Non-contact infrared thermometer'],
                ['First Aid Cabinet', 2100, 'Lockable first aid storage cabinet'],
            ],
            'Home Economics Equipment' => [
                ['Sewing Machine', 12500, 'Electric sewing machine'],
                ['Cooking Pot', 1800, 'Large stainless cooking pot'],
                ['Kitchen Knife Set', 2400, 'Set of kitchen knives with block'],
            ],
            'Agricultural Equipment' => [
                ['Garden Hoe', 650, 'Steel garden hoe with handle'],
                ['Shovel', 780, 'Round-point digging shovel'],
                ['Rake', 520, 'Garden leaf rake'],
            ],
            'Music Equipment' => [
                ['Guitar', 6800, 'Acoustic classroom guitar'],
                ['Amplifier', 9500, 'Portable instrument amplifier'],
                ['Music Stand', 1200, 'Adjustable sheet music stand'],
            ],
            'Science Equipment' => [
                ['Science Model', 3200, 'Anatomical science teaching model'],
                ['Experiment Kit', 4500, 'Classroom science experiment kit'],
                ['Beaker Set', 1800, 'Set of laboratory glass beakers'],
            ],
            'School Vehicles' => [
                ['Service Van', 1250000, 'School service van'],
                ['Motorcycle', 95000, 'School utility motorcycle'],
            ],
            'Communication Equipment' => [
                ['Two-Way Radio', 6800, 'Handheld two-way radio'],
                ['Megaphone', 2800, 'Portable rechargeable voice amplifier'],
                ['Intercom', 4200, 'Wall-mounted intercom unit'],
            ],
            'Security Equipment' => [
                ['CCTV Camera', 5800, 'Outdoor CCTV camera'],
                ['DVR', 12500, 'Digital video recorder for CCTV'],
                ['Alarm Siren', 3200, 'Security alarm siren'],
            ],
            'IT/Network Infrastructure' => [
                ['Router', 9800, 'Dual-band network router'],
                ['Network Switch', 8500, 'Managed 24-port network switch'],
                ['Access Point', 7200, 'Dual-band wireless access point'],
            ],
            'Facilities / Fixtures' => [
                ['LED Light Fixture', 1800, 'Ceiling LED light fixture'],
                ['Door', 8500, 'Steel classroom door'],
                ['Window', 6200, 'Aluminum classroom window'],
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
            'Projector', 'LED TV', 'Air Conditioner', 'Fire Extinguisher', 'BP Monitor',
            'Sewing Machine', 'Guitar', 'Amplifier', 'Router', 'Network Switch', 'Access Point',
            'CCTV Camera', 'DVR', 'Two-Way Radio', 'Service Van', 'Motorcycle',
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
                    'unit' => $category->category_name === 'Library Resources' ? 'copy' : 'piece',
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
