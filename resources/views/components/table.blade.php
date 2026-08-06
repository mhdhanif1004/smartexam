@props(['headers' => [], 'empty' => 'Tidak ada data.'])

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            @if (count($headers) > 0)
                <thead class="bg-gray-50">
                    <tr>
                        @foreach ($headers as $header)
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody class="divide-y divide-gray-100 bg-white">
                @if (trim($slot) === '')
                    <tr>
                        <td colspan="{{ max(count($headers), 1) }}" class="px-4 py-8 text-center text-sm text-gray-500">{{ $empty }}</td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>
</div>
