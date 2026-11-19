<?php

namespace App\Filament\Admin\Resources\FacultyResource\Pages;

use App\Filament\Admin\Resources\FacultyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFaculties extends ListRecords
{
    protected static string $resource = FacultyResource::class;

    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
