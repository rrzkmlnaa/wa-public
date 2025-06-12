<?php

namespace App\Filament\Resources\ContactWithDelayResource\Pages;

use App\Filament\Resources\ContactWithDelayResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContactChats extends ViewRecord
{
    protected static string $resource = ContactWithDelayResource::class;

    public function getView(): string
    {
        return 'filament.resources.contact-with-delay-resource.view'; // ini lokasi Blade kamu
    }
}
