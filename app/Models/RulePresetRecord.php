<?php

namespace App\Models;

use App\Game\Data\RuleConfiguration;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved rule configuration a host can pick when creating a game.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property array<string, mixed>|null $rules
 * @property bool $is_built_in
 * @property int $sort_order
 *
 * The two built-in presets are seeded from {@see RuleConfiguration::presetRules()};
 * the table exists so hosts can save their own configurations later without a
 * schema change.
 */
class RulePresetRecord extends Model
{
    protected $table = 'rule_presets';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_built_in' => 'boolean',
        ];
    }

    public function configuration(): RuleConfiguration
    {
        return RuleConfiguration::fromArray($this->rules ?? []);
    }
}
