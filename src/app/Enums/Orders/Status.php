<?php

namespace App\Enums\Orders;

enum Status: string
{
	case Created = 'Created';
	case PendingDriver = 'Pending Driver';
	case AssigningDriver = 'Assigning Driver';
	case AssignedDriver = 'Assigned Driver';
	case PickupEnroute = 'Pickup Enroute';
	case PickupArrived = 'Pickup Arrived';
	case DropoffEnroute = 'Dropoff Enroute';
	case DropoffArrived = 'Dropoff Arrived';
	case Delivered = 'Delivered'; //
	case Other = 'Other'; //
	case Canceled = 'Canceled'; //
	case CanceledDriver = 'Canceled Driver'; //


	
	public function isFinal(): bool
	{
		return match ($this) {
			self::Delivered, self::Canceled, self::CanceledDriver, self::Other => true,
			default => false,
		};
	}

	   
	public function isCanceled(): bool
	{
		return match ($this) {
			self::Canceled, self::CanceledDriver => true,
			default => false,
		};
	}    
}
