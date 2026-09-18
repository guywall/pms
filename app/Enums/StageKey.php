<?php

namespace App\Enums;

enum StageKey: string
{
    case NewOrder = 'new_order';
    case Artwork = 'artwork';
    case PreProduction = 'pre_production';
    case Production = 'production';
    case Printing = 'printing';
    case Embroidery = 'embroidery';
    case Finishing = 'finishing';
    case Qc = 'qc';
    case Packing = 'packing';
    case Complete = 'complete';
    case Invoiced = 'invoiced';
}
