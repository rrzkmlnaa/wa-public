<?php

namespace App\Filament\Resources\PipelineStagesResource\Pages;

use App\Filament\Resources\PipelineStagesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPipelineStages extends EditRecord
{
    protected static string $resource = PipelineStagesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
