<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * The aggregator could not open the transaction (down, misconfigured…).
 */
class PaymentGatewayException extends RuntimeException {}
