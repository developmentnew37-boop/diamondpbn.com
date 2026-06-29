<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\PendingDomain;
use App\Models\Admin\WebhookSecret;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DomainWebhookController extends Controller
{
    /**
     * Receive pending domain submissions via webhook
     *
     * Expected payload:
     * {
     *   "domain_name": "https://example.com",
     *   "api_key": "wp_api_key_here",
     *   "secret": "webhook_secret_token"
     * }
     */
    public function receiveDomain(Request $request): JsonResponse
    {
        // Validate request payload
        $validator = Validator::make($request->all(), [
            'domain_name' => 'required|string|max:255',
            'api_key' => 'required|string',
            'secret' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Webhook validation failed', [
                'ip' => $request->ip(),
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $domainName = normalizeDomainName($request->input('domain_name'));
        $apiKey = $request->input('api_key');
        $providedSecret = $request->input('secret');

        if ($domainName === '') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid domain name',
            ], 422);
        }

        // Find matching webhook secret
        $webhookSecret = WebhookSecret::where('secret', $providedSecret)
            ->where('is_active', true)
            ->first();

        if (! $webhookSecret) {
            Log::warning('Invalid webhook secret provided', [
                'ip' => $request->ip(),
                'domain' => $domainName,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive webhook secret',
            ], 403);
        }

        // Validate secret using constant-time comparison
        if (! $webhookSecret->validateSecret($providedSecret)) {
            Log::warning('Webhook secret validation failed', [
                'ip' => $request->ip(),
                'domain' => $domainName,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook secret',
            ], 403);
        }

        // Check if this domain is already pending (indexed normalized name lookup)
        $existingPending = PendingDomain::query()
            ->where('status', 'pending')
            ->where('normalized_domain_name', $domainName)
            ->first();

        if ($existingPending) {
            // Update existing pending domain with new API key
            $existingPending->update([
                'domain_name' => $domainName,
                'normalized_domain_name' => $domainName,
                'api_key' => $apiKey,
                'webhook_secret_id' => $webhookSecret->id,
            ]);

            $webhookSecret->markAsUsed();

            Log::info('Updated existing pending domain', [
                'domain' => $domainName,
                'pending_domain_id' => $existingPending->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pending domain updated successfully',
                'data' => [
                    'pending_domain_id' => $existingPending->id,
                    'status' => 'pending',
                ],
            ], 200);
        }

        // Create new pending domain
        $pendingDomain = PendingDomain::create([
            'domain_name' => $domainName,
            'normalized_domain_name' => $domainName,
            'api_key' => $apiKey,
            'viewed' => false,
            'webhook_secret_id' => $webhookSecret->id,
            'status' => 'pending',
        ]);

        // Mark webhook secret as used
        $webhookSecret->markAsUsed();

        Log::info('New pending domain received via webhook', [
            'domain' => $domainName,
            'pending_domain_id' => $pendingDomain->id,
            'webhook_secret_name' => $webhookSecret->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Domain submitted successfully and pending approval',
            'data' => [
                'pending_domain_id' => $pendingDomain->id,
                'status' => 'pending',
            ],
        ], 201);
    }
}
