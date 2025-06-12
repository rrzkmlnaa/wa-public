<?php

namespace App\Filament\Widgets;

use App\Models\MessageLog;
use Filament\Widgets\ChartWidget;

class PerformanceByDeviceChart extends ChartWidget
{
    protected static ?string $heading = 'Performa Pesan per Device';
    protected static ?int $sort = 2; // Optional: urutan tampilan widget

    protected function getData(): array
    {
        $startDate = now()->subDays(10)->startOfDay();
        $endDate = now()->endOfDay();

        $logs = MessageLog::with(['contact.device'])
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->get();

        $dates = collect(range(0, 10))->map(fn($i) => now()->subDays(10 - $i)->format('Y-m-d'));

        $groupedByDevice = $logs->groupBy(fn($log) => $log->contact?->device?->name ?? 'Unknown');

        $colorMap = $this->getDeviceColorMap();
        $datasets = [];

        foreach ($groupedByDevice as $deviceName => $logsForDevice) {
            $repliedCounts = [];
            $unrepliedCounts = [];

            foreach ($dates as $date) {
                $dailyLogs = $logsForDevice->filter(fn($log) => \Carbon\Carbon::parse($log->timestamp)->format('Y-m-d') === $date);

                $repliedCounts[] = $dailyLogs->where('replied', true)->count();
                $unrepliedCounts[] = $dailyLogs->where('replied', false)->count();
            }

            $color = $colorMap[$deviceName] ?? 'rgba(0, 0, 0, 1)';

            $datasets[] = [
                'label' => "$deviceName - Dibalas",
                'data' => $repliedCounts,
                'borderColor' => $color,
                'backgroundColor' => $color,
                'tension' => 0.3,
            ];

            $datasets[] = [
                'label' => "$deviceName - Tidak Dibalas",
                'data' => $unrepliedCounts,
                'borderColor' => $this->adjustColorOpacity($color, 0.5),
                'backgroundColor' => $this->adjustColorOpacity($color, 0.3),
                'tension' => 0.3,
            ];
        }

        return [
            'labels' => $dates->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M'))->toArray(),
            'datasets' => $datasets,
        ];
    }

    protected function getDeviceColorMap(): array
    {
        return [
            'Device A' => 'rgba(54, 162, 235, 1)',    // biru
            'Admin 1' => 'rgba(255, 99, 132, 1)',    // merah
            'Admin 2' => 'rgba(255, 206, 86, 1)',    // kuning
            'Device D' => 'rgba(75, 192, 192, 1)',    // hijau muda
            // tambahkan device lain di sini
        ];
    }

    protected function randomColor(): string
    {
        return sprintf('rgba(%d, %d, %d, 1)', rand(50, 200), rand(50, 200), rand(50, 200));
    }

    protected function adjustColorOpacity(string $rgba, float $opacity): string
    {
        return preg_replace('/rgba\((\d+),\s*(\d+),\s*(\d+),\s*\d+(\.\d+)?\)/', "rgba($1, $2, $3, $opacity)", $rgba);
    }


    protected function getType(): string
    {
        return 'line'; // Bisa diganti jadi 'horizontalBar' kalau ingin horizontal
    }

    // Optional: Tentukan posisi di dashboard
    protected static ?string $maxHeight = '300px';
}
