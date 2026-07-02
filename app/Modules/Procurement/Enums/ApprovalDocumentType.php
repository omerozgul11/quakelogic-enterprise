<?php

namespace App\Modules\Procurement\Enums;

use App\Modules\Procurement\Models\BillPayment;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;

/**
 * Document types that can run through an approval chain.
 */
enum ApprovalDocumentType: string
{
    case PurchaseRequest = 'purchase_request';
    case PurchaseOrder = 'purchase_order';
    case BillPayment = 'bill_payment';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseRequest => 'Purchase Request',
            self::PurchaseOrder => 'Purchase Order',
            self::BillPayment => 'Bill Payment',
        };
    }

    /** @return class-string */
    public function modelClass(): string
    {
        return match ($this) {
            self::PurchaseRequest => PurchaseRequest::class,
            self::PurchaseOrder => PurchaseOrder::class,
            self::BillPayment => BillPayment::class,
        };
    }

    public static function forModel(object $model): self
    {
        return match ($model::class) {
            PurchaseRequest::class => self::PurchaseRequest,
            PurchaseOrder::class => self::PurchaseOrder,
            BillPayment::class => self::BillPayment,
            default => throw new \InvalidArgumentException('Not an approvable procurement document: '.$model::class),
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
