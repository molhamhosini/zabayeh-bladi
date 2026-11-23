<?php

namespace App\Services\Payments;

use RuntimeException;

/** Shown to the customer, e.g. "Tabby can't approve this purchase". */
class BnplException extends RuntimeException {}
