<?php

namespace App\Enums;

enum JobType: string
{
    case Print = 'print';
    case Embroidery = 'embroidery';
    case Mixed = 'mixed';
}
