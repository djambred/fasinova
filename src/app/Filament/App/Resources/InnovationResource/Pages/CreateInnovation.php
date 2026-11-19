<?php

namespace App\Filament\App\Resources\InnovationResource\Pages;

use App\Filament\App\Resources\InnovationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInnovation extends CreateRecord
{
    protected static string $resource = InnovationResource::class;

    /** Setelah dibuat, langsung ke wizard karya itu (bukan kembali ke daftar). */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['owner_id' => auth()->id()];
    }
}
