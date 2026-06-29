@extends('admin.layout.layout')

@section('title', 'Create Webhook Secret')

@section('main-content')
    {{-- Breadcrumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Create Webhook Secret</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.webhook-secrets.index') }}" class="breadcrumb-link">Webhook Secrets</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Create</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="w-full flex flex-col gap-4 items-center !mt-4">
        <div class="w-full max-w-[900px] content-card min-w-0">
            <div class="flex flex-col gap-5">
                <h2 class="text-xl font-semibold capitalize">Add Webhook Secret</h2>

                <form action="{{ route('admin.webhook-secrets.store') }}" method="POST" class="w-full flex flex-col gap-5">
                    @csrf

                    <div class="w-full flex flex-col gap-3 !p-2">
                        <label for="name" class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                            Secret Name
                        </label>
                        <input type="text"
                            class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600 @error('name') border-red-500 @enderror"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            placeholder="e.g., Production API, Development, Partner Integration">
                        <small class="text-gray-500 text-xs">A descriptive name to identify this webhook secret</small>
                        @error('name')
                            <div class="w-full text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="w-full !p-4 bg-blue-50 border border-blue-200 rounded">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <div class="flex-1">
                                <strong class="text-blue-800 text-sm">Note:</strong>
                                <span class="text-blue-700 text-sm">A secure secret will be automatically generated when you create this record. You'll be able to copy it after creation.</span>
                            </div>
                        </div>
                    </div>

                    <div class="w-full flex items-center gap-2 !p-2">
                        <button type="submit"
                            class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                            Create Webhook Secret
                        </button>
                        <a href="{{ route('admin.webhook-secrets.index') }}"
                            class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 transition-all bg-gray-200 hover:bg-gray-300 text-gray-800 rounded cursor-pointer">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Information Box --}}
    <div class="w-full flex flex-col gap-4 items-center !mt-4">
        <div class="w-full max-w-[900px] content-card min-w-0">
            <div class="flex flex-col gap-4">
                <h2 class="text-xl font-semibold capitalize">How to Use Webhook Secrets</h2>

                <p class="text-gray-700 text-sm">Webhook secrets are used to validate incoming domain submissions. Here's how they work:</p>

                <ol class="list-decimal list-inside space-y-2 text-gray-700 text-sm !pl-2">
                    <li>Create a webhook secret with a descriptive name</li>
                    <li>Copy the generated secret token</li>
                    <li>Share the secret with the external system that will submit domains</li>
                    <li>External system sends POST request to:
                        <code class="!px-2 !py-1 bg-gray-100 text-xs rounded !ml-1">{{ url('/api/webhook/domains') }}</code>
                        <pre class="!mt-2 !p-3 bg-gray-800 text-gray-100 rounded overflow-x-auto text-xs"><code>{
  "domain_name": "https://example.com",
  "api_key": "wp_api_key_here",
  "secret": "your_webhook_secret_token"
}</code></pre>
                    </li>
                    <li>Domain appears in "Pending Domains" for approval</li>
                </ol>

                <div class="!p-4 bg-yellow-50 border border-yellow-200 rounded">
                    <strong class="text-yellow-800 text-sm block !mb-2">Security Tips:</strong>
                    <ul class="list-disc list-inside space-y-1 text-yellow-700 text-sm">
                        <li>Keep webhook secrets confidential</li>
                        <li>Use different secrets for different integrations</li>
                        <li>Regenerate secrets if compromised</li>
                        <li>Deactivate unused secrets instead of deleting them</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
