<x-filament::page>
    @forelse ($record->messageLogs as $log)
        @php $chats = json_decode($log->chats, true); @endphp

        <div class="border p-4 rounded bg-white shadow overflow-auto">
            <h2 class="text-xl font-medium">Pipeline Information</h2>
            <div class="grid grid-cols-3 gap-x-3 mt-3">
                <h3 class="font-medium">Program: <span>{{ strtoupper($log->program) }}</span></h3>
                <h3 class="font-medium">Stage: <span>{{ strtoupper($log->stage) }}</span></h3>
                <h3 class="font-medium">City:
                    <span>{{ strtoupper(empty($log->city) ? 'unknown' : $log->city) }}</span>
                </h3>
                <h3 class="font-medium mt-3">Keterangan:</h3>
                <p>{{ empty($log->information) ? '-' : $log->information }}</p>
            </div>
        </div>

        <div class="border p-4 rounded mb-6 bg-white shadow overflow-auto" style="max-height: 85vh !important">
            @if (is_array($chats))
                <div class="flex flex-col space-y-3 w-full mx-auto">
                    @foreach ($chats as $chat)
                        @php
                            // Anggap 'fromMe' ada di data chat, atau default false
                            $fromMe = $chat['fromMe'] ?? false;
                            $time = \Carbon\Carbon::createFromTimestamp($chat['timestamp'])
                                ->setTimezone('Asia/Jakarta')
                                ->translatedFormat('l, d F Y | H:i');
                        @endphp

                        @if ($fromMe)
                            {{-- Pesan dari saya --}}
                            <div class="flex justify-end">
                                <div
                                    class="bg-green-500 text-gray-800 px-4 py-2 rounded-lg rounded-br-none max-w-3xl shadow">
                                    <p>{{ $chat['body'] ?? ($chat['text'] ?? '(kosong)') }}</p>
                                    <span
                                        class="text-xs text-green-200 block text-right mt-1">{{ $time }}</span>
                                </div>
                            </div>
                        @else
                            {{-- Pesan dari lawan --}}
                            <div class="flex justify-start">
                                <div class="bg-gray-100 px-4 py-2 rounded-lg rounded-bl-none max-w-3xl shadow">
                                    <p class="text-gray-800">{{ $chat['body'] ?? ($chat['text'] ?? '(kosong)') }}</p>
                                    <span class="text-xs text-gray-500 block text-right mt-1">{{ $time }}</span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500">Chats tidak valid</p>
            @endif
        </div>
    @empty
        <p class="text-gray-500">Tidak ada log tersedia.</p>
    @endforelse
</x-filament::page>
