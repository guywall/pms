<?php

namespace App\Enums;

enum MovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Adjust = 'adjust';
    case Return = 'return';
}
