<?php

namespace App\Filament\Widgets;

use App\Models\MessageLog;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class AverageDelayChart extends ChartWidget
{
    protected static ?string $heading = 'Rata-Rata Delay Harian';
    protected static string $color = 'info';

    protected function getData(): array
    {
        $dates = collect(range(0, 6))->map(function ($i) {
            return Carbon::now()->subDays($i)->toDateString();
        })->reverse();

        $labels = [];
        $data = [];

        foreach ($dates as $date) {
            $logs = MessageLog::whereDate('updated_at', $date)->get();

            $totalDelay = 0;
            $count = 0;

            foreach ($logs as $log) {
                $chats = json_decode($log->chats, true);
                if (is_array($chats) && count($chats) > 1) {
                    $chats = array_slice($chats, -10);
                    for ($i = 1; $i < count($chats); $i++) {
                        if ($chats[$i]['fromMe'] !== $chats[$i - 1]['fromMe']) {
                            $delay = $chats[$i]['timestamp'] - $chats[$i - 1]['timestamp'];
                            $totalDelay += $delay;
                            $count++;
                        }
                    }
                }
            }

            $average = $count > 0 ? round($totalDelay / $count) : 0;

            $labels[] = Carbon::parse($date)->format('d M');
            $data[] = round($average / 60, 2); // menit
        }

        return [
            'datasets' => [
                [
                    'label' => 'Delay (menit)',
                    'data' => $data,
                    'borderColor' => '#3b82f6',
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
