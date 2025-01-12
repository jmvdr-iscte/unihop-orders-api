<?php

namespace App\Enums\Orders;

enum DeliveryStyle: string
{
    case Standard = 'Standard';
    case StandardLong = 'Standard - Long';
    case Hybrid = 'Hybrid';
    case SpecialHandling = 'Special Handling';
    case Oversize = 'Oversize';
    case StandardLCF = 'Standard LCF';
    case Custom = 'Custom';
    case CateringPro  = 'Catering Pro';
}