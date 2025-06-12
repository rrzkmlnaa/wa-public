<?php

namespace App\Filament\Resources\ContactWithDelayResource\Pages;

use App\Filament\Resources\ContactWithDelayResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactWithDelay extends EditRecord
{
    protected static string $resource = ContactWithDelayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
