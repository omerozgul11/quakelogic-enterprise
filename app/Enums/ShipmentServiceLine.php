<?php

namespace App\Enums;

/**
 * Which product family a rate quote is for. Picks the relevant inputs (parcel
 * weight/dimensions for Express vs. freight class/pallets for Freight) and, with
 * a live integration, which carrier API to call. Null means "general / not sure
 * yet" — both sets of inputs are available.
 */
enum ShipmentServiceLine: string
{
    case Express = 'express';
    case Freight = 'freight';

    public function label(): string
    {
        return match ($this) {
            self::Express => 'Express (parcel)',
            self::Freight => 'Freight (LTL)',
        };
    }
}
