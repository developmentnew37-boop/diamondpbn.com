<?php

namespace App\Data;

class BulkReplaceResult
{
    /**
     * @param  array<int, array{line: int, failed: string, replacement: string, error: string}>  $failures
     */
    public function __construct(
        public int $domainsReplaced = 0,
        public int $postsRequeued = 0,
        public array $failures = [],
        public string $requeueUnitLabel = 'post(s)',
    ) {}

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }

    public function summaryMessage(): string
    {
        $parts = [];

        if ($this->domainsReplaced > 0) {
            $parts[] = $this->domainsReplaced.' domain(s) replaced';
        }

        if ($this->postsRequeued > 0) {
            $parts[] = $this->postsRequeued.' '.$this->requeueUnitLabel.' re-queued';
        }

        if ($parts === []) {
            return 'No domain replacements were applied.';
        }

        $message = implode(', ', $parts).'.';

        if ($this->hasFailures()) {
            $failureLines = collect($this->failures)
                ->map(fn (array $row) => 'Line '.$row['line'].': '.$row['failed'].' → '.$row['replacement'].' ('.$row['error'].')')
                ->implode(' ');

            $message .= ' '.count($this->failures).' mapping(s) failed: '.$failureLines;
        }

        return $message;
    }
}
