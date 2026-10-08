<?php

namespace App\Enums;

/**
 * Outcome of a "Supprimer" click: really deleted, or kept for history and
 * archived (deactivated) because sales or receipts refer to it.
 */
enum Removal
{
    case Deleted;
    case Archived;
}
