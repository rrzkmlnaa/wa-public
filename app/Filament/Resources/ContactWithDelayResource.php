<?php

namespace App\Filament\Resources;

use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use AlperenErsoy\FilamentExport\Actions\FilamentExportHeaderAction;
use App\Filament\Resources\ContactWithDelayResource\Pages;
use App\Filament\Resources\ContactWithDelayResource\RelationManagers;
use App\Models\Contact;
use App\Models\ContactWithDelay;
use App\Models\MessageLog;
use App\Models\Program;
use App\Models\Stage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class ContactWithDelayResource extends Resource
{
    protected static ?string $model = Contact::class;
    protected static ?string $navigationLabel = 'Messages';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Pelanggan')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('program_id')
                    ->label('Nama Program')
                    ->options(Program::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Forms\Components\Select::make('stage_id')
                    ->label('Pilih Progress')
                    ->options(
                        Stage::all()->pluck('name', 'id')->mapWithKeys(function ($name, $id) {
                            return [$id => strtoupper($name)];
                        })
                    )
                    ->required()
                    ->searchable(),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->query(Contact::with('messageLogs')) // eager load untuk efisiensi
            ->columns([
                Tables\Columns\TextColumn::make('device.name')
                    ->label('Agen')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pelanggan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('number')
                    ->label('Nomor Pelanggan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('messageLogs.replied')
                    ->label('Sudah di balas?')
                    ->alignCenter()
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return $state == '1' ? 'Ya' : 'Belum';
                    }),

                Tables\Columns\TextColumn::make('average_delay')
                    ->label('Kec. Menjawab')
                    ->getStateUsing(function (Contact $record) {
                        $messageLogs = $record->messageLogs;

                        $delays = [];

                        foreach ($messageLogs as $messageLog) {
                            // Abaikan kalau belum dibalas
                            if (!$messageLog->replied) {
                                continue;
                            }

                            $chats = json_decode($messageLog->chats, true);
                            if (is_array($chats) && count($chats) > 1) {
                                $chats = array_reverse($chats); // Biar kita cek dari akhir

                                $lastFromMe = null;
                                $lastNotFromMe = null;

                                foreach ($chats as $chat) {
                                    if ($chat['fromMe'] === true && !$lastFromMe) {
                                        $lastFromMe = $chat;
                                    }
                                    if ($chat['fromMe'] === false && !$lastNotFromMe) {
                                        $lastNotFromMe = $chat;
                                    }

                                    if ($lastFromMe && $lastNotFromMe) {
                                        // Hitung delay
                                        $delay = abs($lastFromMe['timestamp'] - $lastNotFromMe['timestamp']);
                                        $delays[] = $delay;
                                        break; // Hanya ambil pasangan terakhir
                                    }
                                }
                            }
                        }

                        if (count($delays) > 0) {
                            $avg = round(array_sum($delays) / count($delays));

                            $hours = floor($avg / 3600);
                            $minutes = floor(($avg % 3600) / 60);

                            $formatted = '';
                            if ($hours > 0) {
                                $formatted .= $hours . 'j';
                            }
                            if ($minutes > 0 || $hours == 0) {
                                if ($formatted !== '') {
                                    $formatted .= ' ';
                                }
                                $formatted .= $minutes . 'm';
                            }

                            $record->avgDelay = $avg;
                            return $formatted;
                        }

                        $record->avgDelay = 0;
                        return '-';
                    })

                    ->color(function ($record) {
                        if (!isset($record->avgDelay)) {
                            return 'gray';
                        }

                        if ($record->avgDelay > 900) { // > 15 menit
                            return 'danger'; // merah
                        } elseif ($record->avgDelay === 900) { // tepat 15 menit
                            return 'warning'; // kuning
                        } else {
                            return 'success'; // hijau
                        }
                    }),

                Tables\Columns\TextColumn::make('program.name')
                    ->label('Nama Program')
                    ->searchable(),

                Tables\Columns\TextColumn::make('stage.name')
                    ->label('Staging')
                    ->alignCenter()
                    ->badge() // tampilkan sebagai badge
                    ->searchable()
                    ->formatStateUsing(fn($state) => strtoupper($state)) // huruf besar semua
                    ->color(function ($state) {
                        return match (strtolower($state)) {
                            'forecast' => 'gray',
                            'foreseen' => 'warning',
                            'firm' => 'info',
                            'backlog' => 'success',
                            default => 'secondary',
                        };
                    }),

                Tables\Columns\TextColumn::make('latest_message_timestamp')
                    ->label('Terakhir Komunikasi')
                    ->formatStateUsing(function ($state) {
                        return $state ? \Carbon\Carbon::parse($state)->translatedFormat('l, d F Y H:i') : '-';
                    }),

            ])
            ->filters([
                Tables\Filters\Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal')
                            ->label('Tanggal Pesan'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->whereHas('messageLogs', function ($q) use ($data) {
                            if ($data['tanggal']) {
                                $q->whereDate('timestamp', $data['tanggal']);
                            }
                        });
                    }),


                Tables\Filters\SelectFilter::make('waktu')
                    ->label('Filter Waktu Pesan')
                    ->options([
                        // 'today' => 'Pesan Hari Ini',
                        'morning' => 'Pesan Pagi Hari (06:00 - 09:59)',
                        'afternoon' => 'Pesan Siang Hari (10:00 - 14:59)',
                        'evening' => 'Pesan Sore Hari (15:00 - 17:59)',
                        'night' => 'Pesan Malam Hari (18:00 - 05:59)',
                    ])
                    ->default('morning')
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'];

                        return $query
                            ->when($value === 'today', function ($query) {
                                $query->whereHas('messageLogs', function ($q) {
                                    $q->whereDate('timestamp', now()->toDateString());
                                });
                            })
                            ->when($value === 'morning', function ($query) {
                                $query->whereHas('messageLogs', function ($q) {
                                    $q->whereTime('timestamp', '>=', '06:00:00')
                                        ->whereTime('timestamp', '<', '10:00:00');
                                });
                            })
                            ->when($value === 'afternoon', function ($query) {
                                $query->whereHas('messageLogs', function ($q) {
                                    $q->whereTime('timestamp', '>=', '10:00:00')
                                        ->whereTime('timestamp', '<', '15:00:00');
                                });
                            })
                            ->when($value === 'evening', function ($query) {
                                $query->whereHas('messageLogs', function ($q) {
                                    $q->whereTime('timestamp', '>=', '15:00:00')
                                        ->whereTime('timestamp', '<', '18:00:00');
                                });
                            })
                            ->when($value === 'night', function ($query) {
                                $query->whereHas('messageLogs', function ($q) {
                                    $q->whereTime('timestamp', '>=', '18:00:00')
                                        ->orWhereTime('timestamp', '<', '06:00:00');
                                });
                            });
                    }),

                Tables\Filters\SelectFilter::make('replied')
                    ->label('Status Balasan')
                    ->options([
                        'all' => 'Semua',
                        '1' => 'Sudah Dibalas',
                        '0' => 'Belum Dibalas',
                    ])
                    ->default('all') // opsional
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'];

                        if ($value === 'all') {
                            return $query;
                        }

                        return $query->whereHas('messageLogs', function ($q) use ($value) {
                            $q->where('replied', $value);
                        });
                    }),


                Tables\Filters\SelectFilter::make('device')
                    ->label('Filter Device')
                    ->options(function () {
                        return [
                            'all' => 'Semua Device',
                        ] + \App\Models\Contact::with('device')
                            ->get()
                            ->pluck('device.name', 'device_id')
                            ->filter()
                            ->unique()
                            ->toArray();
                    })
                    ->default('all') // opsional, jika ingin default-nya "Semua"
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'];

                        // Jika pilih "all", abaikan filter
                        if ($value === 'all') {
                            return $query;
                        }

                        return $query->whereHas('messageLogs.contact', function ($q) use ($value) {
                            $q->where('device_id', $value);
                        });
                    }),



            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                FilamentExportHeaderAction::make('export')
                    ->label('Export Excel')
                    ->fileName('data-pesan')
                    ->defaultFormat('xlsx') // bisa juga 'csv'
                    ->withHiddenColumns() // jika ingin hanya kolom yang terlihat
            ])
            ->bulkActions([
                FilamentExportBulkAction::make('export')
                    ->label('Export Terpilih')
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPluralLabel(): string
    {
        return 'Messages';
    }

    public static function getModelLabel(): string
    {
        return 'Message';
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactWithDelays::route('/'),
            'create' => Pages\CreateContactWithDelay::route('/create'),
            // 'edit' => Pages\EditContactWithDelay::route('/{record}/edit'),
            'view' => Pages\ViewContactChats::route('/{record}')
        ];
    }
}
