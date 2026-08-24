<?php

namespace Database\Seeders;

use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\GarmentCategory;
use App\Models\GarmentOptionGroup;
use App\Models\MeasurementField;
use App\Models\OfficeAvailabilityWindow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BookingSystemSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAppointmentTypes();
        $this->seedGarments();
        $this->seedMeasurements();
        $this->seedAvailability();
    }

    protected function seedAppointmentTypes(): void
    {
        collect([
            ['measurement', 'Measurement Appointment', 30, true],
            ['fitting', 'Fitting Appointment', 30, true],
            ['consultation', 'Style Consultation', 45, true],
            ['alteration_dropoff', 'Alteration Drop-off', 20, true],
            ['pickup', 'Pickup Appointment', 15, false],
            ['bulk_consultation', 'Bulk/Uniform Consultation', 60, true],
        ])->each(function (array $row, int $index): void {
            AppointmentType::query()->updateOrCreate(
                ['code' => $row[0]],
                [
                    'name' => $row[1],
                    'description' => $row[1].' booking slot.',
                    'default_duration_minutes' => $row[2],
                    'requires_approval' => $row[3],
                    'is_public' => true,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        });
    }

    protected function seedGarments(): void
    {
        $categories = [
            'Shirt', 'Trouser', 'Suit', 'Dress', 'Skirt', 'Blouse', 'Kitenge Outfit', 'Abaya',
            'School Uniform', 'Corporate Uniform', 'Wedding Outfit', 'Other',
        ];

        foreach ($categories as $index => $name) {
            GarmentCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'sort_order' => $index + 1]
            );
        }

        $definitions = [
            'shirt' => [
                'Collar type' => ['Standard', 'Mandarin', 'Button-down', 'Cutaway'],
                'Sleeve type' => ['Short sleeve', 'Long sleeve'],
                'Cuff type' => ['Standard cuff', 'French cuff', 'Button cuff'],
                'Button type' => ['Plastic', 'Metal', 'Hidden buttons', 'Custom buttons'],
                'Pocket option' => ['No pocket', 'One chest pocket', 'Two chest pockets'],
                'Fit type' => ['Slim', 'Regular', 'Loose'],
                'Embroidery' => ['None', 'Initials', 'Logo', 'Custom embroidery'],
            ],
            'trouser' => [
                'Waist style' => ['Standard waist', 'High waist', 'Elastic waist'],
                'Pocket type' => ['Side pockets', 'Back pockets', 'No pockets'],
                'Pleats' => ['No pleats', 'Single pleat', 'Double pleat'],
                'Fit' => ['Slim', 'Straight', 'Relaxed'],
                'Bottom style' => ['Plain hem', 'Turn-up hem', 'Elastic bottom'],
                'Closure type' => ['Button', 'Hook', 'Zip', 'Drawstring'],
            ],
            'suit' => [
                'Suit type' => ['Two-piece', 'Three-piece'],
                'Jacket style' => ['Single-breasted', 'Double-breasted'],
                'Lapel type' => ['Notch', 'Peak', 'Shawl'],
                'Button count' => ['One button', 'Two buttons', 'Three buttons'],
                'Vent type' => ['No vent', 'Single vent', 'Double vent'],
                'Lining color' => ['Same color', 'Contrast color', 'Custom'],
                'Waistcoat' => ['Yes', 'No'],
            ],
            'dress' => [
                'Dress length' => ['Knee length', 'Midi', 'Ankle length', 'Floor length'],
                'Sleeve type' => ['Sleeveless', 'Short sleeve', 'Long sleeve'],
                'Neckline' => ['Round', 'V-neck', 'Square', 'Off-shoulder'],
                'Fit' => ['Slim', 'Regular', 'Loose'],
                'Zip position' => ['Back', 'Side', 'Front', 'No zip'],
                'Decoration' => ['None', 'Lace', 'Beads', 'Embroidery'],
            ],
            'school-uniform' => [
                'Branding' => ['None', 'Logo embroidery', 'Logo print'],
                'Logo position' => ['Chest', 'Sleeve', 'Back', 'Custom'],
                'Gender category' => ['Male', 'Female', 'Unisex', 'Children'],
                'Size list available' => ['Yes', 'No'],
            ],
            'corporate-uniform' => [
                'Branding' => ['None', 'Logo embroidery', 'Logo print'],
                'Logo position' => ['Chest', 'Sleeve', 'Back', 'Custom'],
                'Gender category' => ['Male', 'Female', 'Unisex', 'Children'],
                'Size list available' => ['Yes', 'No'],
            ],
        ];

        foreach ($definitions as $categorySlug => $groups) {
            $category = GarmentCategory::query()->where('slug', $categorySlug)->first();
            if (! $category) {
                continue;
            }

            $sort = 1;
            foreach ($groups as $groupName => $options) {
                $group = GarmentOptionGroup::query()->updateOrCreate(
                    ['garment_category_id' => $category->id, 'slug' => Str::slug($groupName)],
                    [
                        'name' => $groupName,
                        'input_type' => 'select',
                        'is_active' => true,
                        'sort_order' => $sort++,
                    ]
                );

                foreach ($options as $optionSort => $option) {
                    $group->options()->updateOrCreate(
                        ['value' => Str::slug($option, '_')],
                        [
                            'label' => $option,
                            'is_active' => true,
                            'sort_order' => $optionSort + 1,
                        ]
                    );
                }
            }
        }
    }

    protected function seedMeasurements(): void
    {
        $definitions = [
            null => ['Height', 'Weight'],
            'shirt' => ['Shoulder', 'Chest', 'Neck', 'Sleeve Length', 'Wrist', 'Shirt Length', 'Armhole'],
            'trouser' => ['Waist', 'Hip', 'Thigh', 'Knee', 'Ankle', 'Inseam', 'Trouser Length', 'Rise'],
            'dress' => ['Bust', 'Waist', 'Hip', 'Shoulder', 'Sleeve Length', 'Dress Length', 'Armhole'],
            'suit' => ['Shoulder', 'Chest', 'Waist', 'Hip', 'Sleeve Length', 'Jacket Length', 'Trouser Waist', 'Trouser Length', 'Inseam'],
        ];

        foreach ($definitions as $categorySlug => $fields) {
            $categoryId = $categorySlug ? GarmentCategory::query()->where('slug', $categorySlug)->value('id') : null;
            foreach ($fields as $sort => $field) {
                $code = Str::upper(Str::snake($field));
                $measurement = MeasurementField::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $field,
                        'slug' => Str::slug($field),
                        'garment_category_id' => null,
                        'unit' => in_array($field, ['Weight'], true) ? 'kg' : 'cm',
                        'default_unit' => in_array($field, ['Weight'], true) ? 'kg' : 'cm',
                        'is_global' => $categoryId === null,
                        'is_active' => true,
                        'sort_order' => $sort + 1,
                    ]
                );

                if ($categoryId) {
                    $measurement->garmentCategories()->syncWithoutDetaching([
                        $categoryId => ['is_required' => false, 'sort_order' => $sort + 1],
                    ]);
                }
            }
        }
    }

    protected function seedAvailability(): void
    {
        $branchId = Branch::query()->orderBy('id')->value('id');

        for ($day = 1; $day <= 5; $day++) {
            OfficeAvailabilityWindow::query()->firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'appointment_type_id' => null,
                    'day_of_week' => $day,
                ],
                [
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'slot_interval_minutes' => 30,
                    'capacity' => 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
