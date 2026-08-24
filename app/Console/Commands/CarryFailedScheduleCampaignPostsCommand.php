<?php

namespace App\Console\Commands;

use App\Services\ScheduleCampaignFailedPostCarryService;
use Illuminate\Console\Command;

class CarryFailedScheduleCampaignPostsCommand extends Command
{
    protected $signature = 'schedule-campaigns:carry-failed-posts';

    protected $description = 'Re-queue failed schedule posts through schedule_to_date plus grace days';

    public function handle(ScheduleCampaignFailedPostCarryService $carry): int
    {
        $summary = $carry->carryFailedPosts();

        $this->info(sprintf(
            'Carried %d failed post(s) across %d schedule campaign(s).',
            $summary['posts'],
            $summary['campaigns'],
        ));

        return self::SUCCESS;
    }
}
