<?php

namespace App\Filament\App\Resources\InnovationResource\Pages;

use App\Filament\App\Resources\InnovationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInnovations extends ListRecords
{
    protected static string $resource = InnovationResource::class;

    protected function getHeaderActions(): array { return [CreateAction::make()->label('Buat karya')]; }
}
