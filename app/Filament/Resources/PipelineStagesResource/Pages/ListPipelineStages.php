<?php

namespace App\Filament\Resources\PipelineStagesResource\Pages;

use App\Filament\Resources\PipelineStagesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPipelineStages extends ListRecords
{
    protected static string $resource = PipelineStagesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
