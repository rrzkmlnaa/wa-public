<?php

namespace App\Filament\Widgets;

use App\Models\MessageLog;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class MessagesByTimeChart extends ChartWidget
{
    protected static ?string $heading = 'Pesan Berdasarkan Waktu';

    protected function getData(): array
    {
        // Label kategori waktu
        $timeLabels = ['Pagi', 'Siang', 'Sore', 'Malam'];

        // Ambil pesan hari ini per kategori waktu
        $today = Carbon::now()->toDateString();

        $countsToday = [
            'Pagi' => $this->countMessagesByTimeRange($today, '06:00:00', '10:00:00'),
            'Siang' => $this->countMessagesByTimeRange($today, '10:00:00', '15:00:00'),
            'Sore' => $this->countMessagesByTimeRange($today, '15:00:00', '18:00:00'),
            'Malam' => $this->countMessagesNight($today),
        ];

        // Grafik 7 hari terakhir: jumlah pesan per kategori waktu tiap hari
        $dates = collect(range(10, 0))->map(fn($i) => Carbon::now()->subDays($i)->toDateString());

        $datasets = [];

        foreach ($timeLabels as $label) {
            $data = [];
            foreach ($dates as $date) {
                switch ($label) {
                    case 'Pagi':
                        $data[] = $this->countMessagesByTimeRange($date, '06:00:00', '10:00:00');
                        break;
                    case 'Siang':
                        $data[] = $this->countMessagesByTimeRange($date, '10:00:00', '15:00:00');
                        break;
                    case 'Sore':
                        $data[] = $this->countMessagesByTimeRange($date, '15:00:00', '18:00:00');
                        break;
                    case 'Malam':
                        $data[] = $this->countMessagesNight($date);
                        break;
                }
            }
            $datasets[] = [
                'label' => $label,
                'data' => $data,
                'fill' => true,
                'borderColor' => $this->getColorForLabel($label),
                'backgroundColor' => $this->getColorWithOpacity($label, 0.3),
                'tension' => 0.3,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $dates->map(fn($d) => Carbon::parse($d)->format('d M'))->toArray(),
            'extra' => [
                'todayCounts' => $countsToday,
            ],
        ];
    }


    protected function getType(): string
    {
        return 'line';
    }

    protected function countMessagesByTimeRange(string $date, string $startTime, string $endTime): int
    {
        return MessageLog::whereDate('timestamp', $date)
            ->whereTime('timestamp', '>=', $startTime)
            ->whereTime('timestamp', '<', $endTime)
            ->count();
    }

    protected function getColorForLabel(string $label): string
    {
        return match ($label) {
            'Pagi' => 'rgba(54, 162, 235, 1)',     // biru solid
            'Siang' => 'rgba(255, 206, 86, 1)',    // kuning solid
            'Sore' => 'rgba(255, 99, 132, 1)',     // merah solid
            'Malam' => 'rgba(75, 192, 192, 1)',    // hijau muda solid
            default => 'rgba(0, 0, 0, 1)',          // fallback hitam
        };
    }

    protected function getColorWithOpacity(string $label, float $opacity = 0.5): string
    {
        return match ($label) {
            'Pagi' => "rgba(54, 162, 235, $opacity)",
            'Siang' => "rgba(255, 206, 86, $opacity)",
            'Sore' => "rgba(255, 99, 132, $opacity)",
            'Malam' => "rgba(75, 192, 192, $opacity)",
            default => "rgba(0, 0, 0, $opacity)",
        };
    }


    protected function countMessagesNight(string $date): int
    {
        // Malam: 18:00 - 23:59 + 00:00 - 05:59 (gabungan 2 rentang waktu)
        $count1 = MessageLog::whereDate('timestamp', $date)
            ->whereTime('timestamp', '>=', '18:00:00')
            ->count();

        $count2 = MessageLog::whereDate('timestamp', Carbon::parse($date)->addDay()->toDateString())
            ->whereTime('timestamp', '<', '06:00:00')
            ->count();

        return $count1 + $count2;
    }
}
