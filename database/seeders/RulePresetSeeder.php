<?php

namespace Database\Seeders;

use App\Enums\RulePreset;
use App\Game\Data\RuleConfiguration;
use App\Models\RulePresetRecord;
use Illuminate\Database\Seeder;

/**
 * Seeds the two published rule presets. Safe to run repeatedly.
 */
class RulePresetSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;

        foreach (RulePreset::cases() as $preset) {
            if ($preset === RulePreset::Custom) {
                continue;
            }

            RulePresetRecord::updateOrCreate(
                ['slug' => $preset->value],
                [
                    'name' => $preset->label(),
                    'description' => $preset->description(),
                    'rules' => RuleConfiguration::presetRules($preset),
                    'is_built_in' => true,
                    'sort_order' => $order++,
                ],
            );
        }
    }
}
