@php

    $steps = [

        1 => 'Select campaign',

        2 => 'Review & recover',

        3 => 'Schedule dates',

        4 => 'Confirm & convert',

    ];

@endphp

<div class="convert-stepper flex flex-wrap gap-3 !mb-6">

    @foreach ($steps as $num => $label)

        @php

            $active = ($currentStep ?? 1) === $num;

            $done = ($currentStep ?? 1) > $num;

        @endphp

        <div class="flex items-center gap-2 text-sm">

            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full font-semibold

                {{ $active ? 'bg-[var(--primary-color)] text-white' : ($done ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600') }}">

                {{ $num }}

            </span>

            <span class="{{ $active ? 'font-semibold' : 'text-gray-600' }}">{{ $label }}</span>

        </div>

    @endforeach

</div>

