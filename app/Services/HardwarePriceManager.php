<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HardwarePrice;
use App\Models\PriceHistory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HardwarePriceManager
{
    public function create(
        int $organisationId,
        array $attributes
    ): HardwarePrice {
        $validated =
            $this->validate(
                $attributes
            );

        return DB::transaction(
            fn () =>
                HardwarePrice::create([
                    ...$validated,
                    'organisation_id' =>
                        $organisationId,
                ])
        );
    }

    public function update(
        int $organisationId,
        HardwarePrice $hardwarePrice,
        array $attributes
    ): HardwarePrice {
        abort_unless(
            $hardwarePrice
                ->organisation_id
                === $organisationId,
            403
        );

        $validated =
            $this->validate(
                $attributes
            );

        return DB::transaction(
            function () use (
                $organisationId,
                $hardwarePrice,
                $validated
            ) {
                $price =
                    HardwarePrice::query()
                        ->where(
                            'organisation_id',
                            $organisationId
                        )
                        ->lockForUpdate()
                        ->findOrFail(
                            $hardwarePrice->id
                        );

                if (
                    number_format(
                        (float) $price->price,
                        2,
                        '.',
                        ''
                    )
                    !==
                    number_format(
                        (float) $validated['price'],
                        2,
                        '.',
                        ''
                    )
                ) {
                    PriceHistory::create([
                        'organisation_id' =>
                            $organisationId,

                        'hardware_price_id' =>
                            $price->id,

                        'price' =>
                            $price->price,

                        'currency' =>
                            $price->currency,

                        'supplier' =>
                            $price->supplier,

                        'location' =>
                            $price->location,

                        'source_url' =>
                            $price->source_url,

                        'recorded_at' =>
                            $price->fetched_at,

                        'metadata' => [
                            'change' =>
                                'price_update',

                            'price_type' =>
                                $price->price_type,

                            'new_price' =>
                                number_format(
                                    (float) $validated['price'],
                                    2,
                                    '.',
                                    ''
                                ),
                        ],
                    ]);
                }

                $price->update(
                    $validated
                );

                return $price
                    ->refresh();
            }
        );
    }

    public function deactivate(
        int $organisationId,
        HardwarePrice $hardwarePrice
    ): HardwarePrice {
        return $this->setActive(
            $organisationId,
            $hardwarePrice,
            false
        );
    }

    public function activate(
        int $organisationId,
        HardwarePrice $hardwarePrice
    ): HardwarePrice {
        return $this->setActive(
            $organisationId,
            $hardwarePrice,
            true
        );
    }

    private function setActive(
        int $organisationId,
        HardwarePrice $hardwarePrice,
        bool $active
    ): HardwarePrice {
        abort_unless(
            $hardwarePrice
                ->organisation_id
                === $organisationId,
            403
        );

        return DB::transaction(
            function () use (
                $organisationId,
                $hardwarePrice,
                $active
            ) {
                $price =
                    HardwarePrice::query()
                        ->where(
                            'organisation_id',
                            $organisationId
                        )
                        ->lockForUpdate()
                        ->findOrFail(
                            $hardwarePrice->id
                        );

                $price->update([
                    'is_active' =>
                        $active,
                ]);

                return $price
                    ->refresh();
            }
        );
    }

    private function validate(
        array $attributes
    ): array {
        $attributes =
            Arr::only(
                $attributes,
                [
                    'item_name',
                    'brand',
                    'category',
                    'price_type',
                    'specification',
                    'unit',
                    'price',
                    'currency',
                    'supplier',
                    'location',
                    'source_url',
                    'source_reference',
                    'fetched_at',
                    'is_active',
                ]
            );

        $attributes['price_type'] =
            $attributes['price_type']
            ?? HardwarePrice::TYPE_HARDWARE;

        foreach (
            [
                'item_name',
                'brand',
                'category',
                'price_type',
                'specification',
                'unit',
                'currency',
                'supplier',
                'location',
                'source_url',
                'source_reference',
            ]
            as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $attributes
                )
                && is_string(
                    $attributes[$field]
                )
            ) {
                $attributes[$field] =
                    trim(
                        $attributes[$field]
                    );
            }
        }

        foreach (
            [
                'brand',
                'specification',
                'location',
                'source_url',
                'source_reference',
            ]
            as $field
        ) {
            if (
                ($attributes[$field] ?? null)
                === ''
            ) {
                $attributes[$field] =
                    null;
            }
        }

        return Validator::make(
            $attributes,
            [
                'item_name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'brand' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'category' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'price_type' => [
                    'required',
                    'string',
                    Rule::in([
                        HardwarePrice::TYPE_HARDWARE,
                        HardwarePrice::TYPE_FACTORY,
                    ]),
                ],

                'specification' => [
                    'nullable',
                    'string',
                    'max:191',
                ],

                'unit' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'price' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:9999999999999.99',
                ],

                'currency' => [
                    'required',
                    'string',
                    'size:3',
                    'regex:/^[A-Z]{3}$/',
                ],

                'supplier' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'location' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'source_url' => [
                    'nullable',
                    'url:http,https',
                    'max:500',
                ],

                'source_reference' => [
                    'nullable',
                    'string',
                    'max:191',
                ],

                'fetched_at' => [
                    'required',
                    'date',
                ],

                'is_active' => [
                    'required',
                    'boolean',
                ],
            ]
        )->validate();
    }
}