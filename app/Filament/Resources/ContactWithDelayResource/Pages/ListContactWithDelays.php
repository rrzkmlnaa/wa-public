<?php

namespace App\Filament\Resources\ContactWithDelayResource\Pages;

use App\Filament\Resources\ContactWithDelayResource;
use App\Filament\Widgets\AverageDelayChart;
use App\Filament\Widgets\MessageGrowthRateChart;
use App\Filament\Widgets\MessagesByTimeChart;
use App\Filament\Widgets\PerformanceByDeviceChart;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContactWithDelays extends ListRecords
{
    protected static string $resource = ContactWithDelayResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            MessagesByTimeChart::class,
            PerformanceByDeviceChart::class,
            // MessageGrowthRateChart::class
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }
}
