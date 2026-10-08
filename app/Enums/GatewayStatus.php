<?php

namespace App\Enums;

/**
 * Status of a transaction as reported by the payment aggregator API.
 */
enum GatewayStatus: string
{
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Waiting = 'waiting';
}
