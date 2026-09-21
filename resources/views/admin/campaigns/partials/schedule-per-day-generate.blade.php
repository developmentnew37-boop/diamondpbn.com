@php
    $perDayId = $perDayId ?? 'sch-per-day';
    $offsetId = $offsetId ?? 'sch-day-offset';
    $perDayBtnId = $perDayBtnId ?? 'sch-generate-per-day';
    $unitLabel = $unitLabel ?? 'posts';
@endphp
<div class="w-full flex flex-col gap-3">
    <label class="text-sm font-medium text-gray-700">Generate from per day (optional)</label>
    <p class="text-sm text-gray-600">Enter how many {{ $unitLabel }} per day and how many days back from today. This fills From date, To date, and the table below. You can still use Generate date table for a blank range.</p>
    <div class="w-full flex flex-col gap-3">
        <label for="{{ $perDayId }}" class="text-sm">Per day</label>
        <input type="number" min="1" step="1" id="{{ $perDayId }}" placeholder="e.g. 20"
            class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
    </div>
    <div class="w-full flex flex-col gap-3">
        <label for="{{ $offsetId }}" class="text-sm">Offset (days back from today)</label>
        <input type="number" min="0" step="1" value="0" id="{{ $offsetId }}" placeholder="e.g. 2"
            class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
    </div>
    <button type="button" id="{{ $perDayBtnId }}"
        class="!px-3 !py-2 rounded bg-blue-600 text-white text-sm w-fit hover:bg-blue-700">Generate from per day</button>
</div>
