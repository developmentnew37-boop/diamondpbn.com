@php
    $canRetryRemaining = $canRetryRemaining ?? false;
    $remainingRetryCount = (int) ($remainingRetryCount ?? 0);
    $retryAction = $retryAction ?? '#';
    $retryNoun = $retryNoun ?? 'item(s)';
@endphp
@if ($canRetryRemaining)
    <form action="{{ $retryAction }}" method="post" class="inline"
          onsubmit="return confirm('Retry {{ $remainingRetryCount }} remaining {{ $retryNoun }} now?\n\nFailed, queued, and stuck publishing rows will be re-queued. Successful items will not change.');">
        @csrf
        <button type="submit"
                class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-blue-600 text-white hover:bg-blue-700 text-sm"
                title="Retry failed, queued, and stuck publishing items">
            Bulk retry remaining ({{ $remainingRetryCount }})
        </button>
    </form>
@endif
