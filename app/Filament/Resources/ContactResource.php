<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Filament\Resources\ContactResource\RelationManagers;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;
    protected static ?string $navigationLabel = 'Contacts';
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('device_id')
                    ->relationship('device', 'client_id')
                    ->required(),
                Forms\Components\TextInput::make('name')->label('Nama'),
                Forms\Components\TextInput::make('number')->label('Nomor')->required(),
                Forms\Components\Toggle::make('is_group')->label('Grup?'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device.name')->label('Device'),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('number')->searchable(),
                // Tables\Columns\IconColumn::make('is_group')->boolean()->label('Grup'),
                Tables\Columns\TextColumn::make('synced_at')->dateTime()->label('Disinkronisasi'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('sync_contacts')
                    ->label('Sync Contacts')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () {
                        // Sync semua device (atau pilih 1 jika ingin spesifik)
                        $devices = \App\Models\Device::where('is_connected', true)->get();

                        foreach ($devices as $device) {
                            try {
                                Http::post("http://127.0.0.1:8000/api/sync-contacts/{$device->client_id}");
                            } catch (\Throwable $e) {
                                Log::error("Failed to sync contacts for {$device->client_id}: " . $e->getMessage());
                            }
                        }

                        Notification::make()
                            ->title('Kontak berhasil disinkronkan.')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->color('primary'),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            // 'create' => Pages\CreateContact::route('/create'),
            // 'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }
}
