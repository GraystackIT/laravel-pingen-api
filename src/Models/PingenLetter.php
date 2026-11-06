<?php

declare(strict_types=1);

namespace GraystackIT\Pingen\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A letter this application sent, when database storage is switched on.
 *
 * Optional by design: an application that keeps its own record of what it sent
 * does not need a second one. Nothing in the package reads this table to make
 * a decision — it is a record, not state.
 *
 * @property int $id
 * @property string $letter_id
 * @property string $organisation_id
 * @property string $file_original_name
 * @property int|null $file_pages
 * @property string $status
 * @property string|null $country
 * @property string|null $price_currency
 * @property float|null $price_value
 * @property string|null $tracking_number
 * @property CarbonInterface|null $submitted_at
 * @property array<string, mixed>|null $payload
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class PingenLetter extends Model
{
    protected $table = 'pingen_letters';

    protected $fillable = [
        'letter_id',
        'organisation_id',
        'file_original_name',
        'file_pages',
        'status',
        'country',
        'price_currency',
        'price_value',
        'tracking_number',
        'submitted_at',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_pages' => 'integer',
            'price_value' => 'float',
            'submitted_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
