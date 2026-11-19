<?php

namespace App\Filament\App\Resources\InnovationResource\Pages;

use App\Filament\App\Resources\InnovationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInnovation extends CreateRecord
{
    protected static string $resource = InnovationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['owner_id' => auth()->id()];
    }
}
