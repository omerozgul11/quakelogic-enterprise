<?php

namespace App\Enums;

/**
 * The kind of number a J.B. Hunt shipment is tracked by. These map 1:1 to the
 * "reference type" dropdown on J.B. Hunt's public tracker — the value is the
 * `k` query param of the deep link (?k=<type>&v=<number>), so they must match
 * J.B. Hunt's own codes exactly.
 */
enum JbHuntReferenceType: string
{
    case OrderNumber = 'orderNbr';
    case TrackingNumber = 'utn';
    case BillOfLading = 'BOL';
    case PoNumber = 'poNbr';
    case ShipperId = 'shipperId';
    case PickupNumber = 'pickupNbr';
    case DeliveryAppointmentNumber = 'deliveryApptNbr';
    case SealNumber = 'sealNbr';

    /** Freight is most commonly tracked by order number, and it's J.B. Hunt's own default. */
    public const Default = self::OrderNumber;

    public function label(): string
    {
        return match ($this) {
            self::OrderNumber => 'Order Number',
            self::TrackingNumber => 'Tracking Number',
            self::BillOfLading => 'Bill of Lading',
            self::PoNumber => 'PO Number',
            self::ShipperId => 'Shipper ID',
            self::PickupNumber => 'Pick Up Number',
            self::DeliveryAppointmentNumber => 'Delivery Appointment Number',
            self::SealNumber => 'Seal Number',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            self::cases(),
        );
    }
}
