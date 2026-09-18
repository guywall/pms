<?php

namespace App\Filament\Staff\Resources\CustomerResource\Pages;

use App\Filament\Staff\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
