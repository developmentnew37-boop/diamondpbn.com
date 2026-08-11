<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\WebhookRotationSetting;
use App\Models\Admin\WebhookSecret;
use App\Services\WebhookSecretRotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WebhookSecretController extends Controller
{
    /**
     * Display listing of webhook secrets
     */
    public function index(WebhookSecretRotationService $rotationService): Response
    {
        $secrets = WebhookSecret::withCount('awaitingPendingDomains as pending_domains_count')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $rotationSetting = WebhookRotationSetting::current();
        $rotationHourOptions = WebhookRotationSetting::selectableHourOptions();
        $dueForRotationCount = $rotationService->secretsDueForRotation()->count();

        return response()->view('admin.domains.webhook-secrets.index', compact(
            'secrets',
            'rotationSetting',
            'rotationHourOptions',
            'dueForRotationCount'
        ))->header('Cache-Control', 'no-store, private');
    }

    /**
     * Show form to create new webhook secret
     */
    public function create(): View
    {
        return view('admin.domains.webhook-secrets.create');
    }

    /**
     * Store new webhook secret
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:webhook_secrets,name',
        ]);

        $plainSecret = WebhookSecret::generateSecret();
        $secret = WebhookSecret::create([
            'name' => $request->name,
            'secret' => $plainSecret,
            'is_active' => true,
            'secret_rotated_at' => now(),
        ]);

        Log::info('Webhook secret created', [
            'id' => $secret->id,
            'name' => $secret->name,
            'admin' => auth('admin')->user()->email,
        ]);

        return redirect()
            ->route('admin.webhook-secrets.index')
            ->with('success', 'Webhook secret created successfully')
            ->with('one_time_webhook_secret', $plainSecret);
    }

    /**
     * Show form to edit webhook secret
     */
    public function edit(WebhookSecret $webhookSecret): View
    {
        $webhookSecret->loadCount('awaitingPendingDomains as pending_domains_count');

        return view('admin.domains.webhook-secrets.edit', compact('webhookSecret'));
    }

    /**
     * Update webhook secret
     */
    public function update(Request $request, WebhookSecret $webhookSecret): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:webhook_secrets,name,'.$webhookSecret->id,
            'is_active' => 'required|boolean',
        ]);

        $webhookSecret->update([
            'name' => $request->name,
            'is_active' => $request->is_active,
        ]);

        Log::info('Webhook secret updated', [
            'id' => $webhookSecret->id,
            'name' => $webhookSecret->name,
            'admin' => auth('admin')->user()->email,
        ]);

        return redirect()
            ->route('admin.webhook-secrets.index')
            ->with('success', 'Webhook secret updated successfully');
    }

    /**
     * Regenerate the secret token
     */
    public function regenerate(WebhookSecret $webhookSecret): RedirectResponse
    {
        $plainSecret = $webhookSecret->rotateSecret();

        Log::warning('Webhook secret regenerated', [
            'id' => $webhookSecret->id,
            'name' => $webhookSecret->name,
            'admin' => auth('admin')->user()->email,
        ]);

        return redirect()
            ->back()
            ->with('warning', 'Webhook secret regenerated. Copy it now and update all integrations.')
            ->with('one_time_webhook_secret', $plainSecret);
    }

    /**
     * Update global auto-rotation interval (hours). 0 = disabled.
     */
    public function updateRotationSettings(Request $request): RedirectResponse
    {
        $maxHours = max(1, (int) config('webhook.max_rotation_hours', 168));

        $request->validate([
            'rotation_hours' => 'required|integer|min:0|max:'.$maxHours,
        ]);

        $hours = (int) $request->rotation_hours;

        $setting = WebhookRotationSetting::current();
        $setting->update([
            'rotation_hours' => $hours,
            'updated_by_admin_id' => auth('admin')->id(),
        ]);

        Log::info('Webhook secret auto-rotation interval updated', [
            'rotation_hours' => $hours,
            'admin' => auth('admin')->user()->email,
        ]);

        $message = $hours === 0
            ? 'Automatic webhook secret rotation is now disabled.'
            : "Automatic rotation set to every {$hours} hour(s). Secrets rotate when due (checked hourly).";

        return redirect()
            ->route('admin.webhook-secrets.index')
            ->with('success', $message);
    }

    /**
     * Delete webhook secret
     */
    public function destroy(WebhookSecret $webhookSecret): RedirectResponse
    {
        $pendingCount = $webhookSecret->awaitingPendingDomains()->count();

        if ($pendingCount > 0) {
            return redirect()
                ->back()
                ->with('error', "Cannot delete webhook secret. It has {$pendingCount} associated pending domains.");
        }

        $name = $webhookSecret->name;
        $webhookSecret->delete();

        Log::warning('Webhook secret deleted', [
            'name' => $name,
            'admin' => auth('admin')->user()->email,
        ]);

        return redirect()
            ->route('admin.webhook-secrets.index')
            ->with('success', 'Webhook secret deleted successfully');
    }
}
