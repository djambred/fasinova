<?php

namespace App\Filament\Dosen\Resources;

use App\Filament\App\Resources\InquiryResource as BaseInquiryResource;
use App\Filament\Dosen\Resources\InquiryResource\Pages\ListInquiries;
use Illuminate\Database\Eloquent\Builder;

/** Inquiry untuk karya bimbingan (dan lingkup kaprodi/dekan), sama seperti daftar "Karya bimbingan". */
class InquiryResource extends BaseInquiryResource
{
    protected static function innovations(): Builder
    {
        return BimbinganResource::getEloquentQuery();
    }

    public static function getPages(): array { return ['index' => ListInquiries::route('/')]; }
}
