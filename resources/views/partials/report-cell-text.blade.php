{{-- Truncated text for report table cells (full value in title tooltip). --}}
@props(['display' => '-', 'title' => ''])

<span class="report-clip" @if ($title !== '') title="{{ $title }}" @endif>{{ $display }}</span>
