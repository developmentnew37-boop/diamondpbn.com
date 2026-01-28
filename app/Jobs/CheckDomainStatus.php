<?php

namespace App\Jobs;

use App\Models\Admin\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckDomainStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $domain, $category_id, $admin_id, $da, $dr, $tf, $ss, $ip, $api_key;

    public function __construct($domain, $category_id, $admin_id, $da, $dr, $tf, $ss, $ip, $api_key)
    {
        $this->domain = $domain;
        $this->category_id = $category_id;
        $this->admin_id = $admin_id;
        $this->da = $da;
        $this->dr = $dr;
        $this->tf = $tf;
        $this->ss = $ss;
        $this->ip = $ip;
        $this->api_key = $api_key;
    }

    public function handle()
    {
        $url = "https://{$this->domain}/wp-json/external/v1/status";

        $status = 0;  // Default: Not connected
        $message = "Plugin Missing / API Route Not Found"; // Default message

        try {
            $response = Http::withoutVerifying()->timeout(30)->get($url);

            if ($response->successful() && $response->json('status') == true) {
                $status = 1;
                $message = $response->json('message') ?? "Connected Successfully";
            } else {
                $status = 0;
                $message = $response->json('message') ?? "Plugin Not Installed OR Invalid Endpoint";
            }

        } catch (\Exception $e) {
            $status = 0;
            $message = "Request Failed: ".$e->getMessage();
        }

        // Save or update domain (no duplicates)
        Domain::updateOrCreate(
            ['name' => $this->domain],

            [
                'domain_category_id' => $this->category_id,
                'admin_id' => $this->admin_id,
                'da' => $this->da,
                'dr' => $this->dr,
                'tf' => $this->tf,
                'ss' => $this->ss,
                'ip' => $this->ip,
                'api_key' => $this->api_key,
                'status' => $status,
            ]
        );

        // Log result clearly
        Log::info("Domain Check → {$this->domain} | Status: {$status} | Message: {$message}");
    }
}
