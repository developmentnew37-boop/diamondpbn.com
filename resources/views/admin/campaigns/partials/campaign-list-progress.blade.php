@php
    $progress = (float) ($progress ?? 0);
    $barColor = $progress >= 80 ? 'bg-green-500' : ($progress >= 50 ? 'bg-yellow-500' : 'bg-red-500');
@endphp

<div class="w-full bg-gray-200 rounded h-2">
    <div class="h-2 rounded {{ $barColor }}" style="width: {{ $progress }}%"></div>
</div>
<div class="text-[11px] text-gray-600 text-center mt-1">{{ $progress }}%</div>
