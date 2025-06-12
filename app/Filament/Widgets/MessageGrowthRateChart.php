<?php

namespace App\Filament\Widgets;

use App\Models\MessageLog;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class MessageGrowthRateChart extends ChartWidget
{
    protected static ?string $heading = 'Growth Rate Pesan';

    protected function getData(): array
    {
        // Ambil tanggal 7 hari terakhir
        $dates = collect(range(10, 0))->map(fn($i) => Carbon::now()->subDays($i)->toDateString());

        $totals = [];
        foreach ($dates as $date) {
            $totals[$date] = MessageLog::whereDate('timestamp', $date)->count();
        }

        // Hitung growth rate per hari (persentase perubahan dibanding hari sebelumnya)
        $growthRates = [];
        $previousTotal = null;
        foreach ($dates as $date) {
            $todayTotal = $totals[$date];
            if ($previousTotal === null) {
                $growthRates[] = 0; // hari pertama growth 0%
            } else {
                // Hitung growth rate, hati-hati pembagian 0
                if ($previousTotal == 0) {
                    $growthRates[] = $todayTotal > 0 ? 100 : 0;
                } else {
                    $growth = (($todayTotal - $previousTotal) / $previousTotal) * 100;
                    $growthRates[] = round($growth, 2);
                }
            }
            $previousTotal = $todayTotal;
        }

        return [
            'labels' => $dates->map(fn($d) => Carbon::parse($d)->format('d M'))->toArray(),
            'datasets' => [
                [
                    'label' => 'Growth Rate (%)',
                    'data' => $growthRates,
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.3)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
