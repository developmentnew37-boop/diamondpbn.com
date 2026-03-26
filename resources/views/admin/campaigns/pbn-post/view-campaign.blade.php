    @extends('admin.layout.layout')

    @section('title', 'Campaigns')

    @section('main-content')

        {{-- bread-crumbs --}}
        <div class="page-header">
            <div class="w-full flex flex-wrap items-center">
                <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                    <h2 class="page-title">Dashboards</h2>
                    <div class="breadcrumb">
                        <div class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                            <span>›</span>
                        </div>
                        <div class="breadcrumb-item">
                            <a href="{{ route('admin.campaign.index') }}" class="breadcrumb-link">PBN Post</a>
                        </div>

                    </div>
                </div>
                <div class="w-1/2 flex flex-wrap justify-end items-center">
                    {{-- <a href="{{ url()->previous() }}"
                        class="flex !p-2  text-[16px] font-normal w-fit justify-center duration:300 bg-black 
                        whitespace-nowrap hover:bg-[var(--primary-color)] text-white rounded transition-all duration">
                        Back</a> --}}
                    {{-- <a href="{{ url()->previous() ?: route('admin.campaign.index') }}" --}}
                    <a href="javascript:void(0)" onclick="history.back()"
                        class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 duration-400 hover:bg-gray-300 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7 7-7M3 12h18" />
                        </svg>
                        Back
                    </a>

                </div>
            </div>
        </div>


        {{-- ******************* success & errors alerts ***************** --}}

        <div class="w-full flex flex-col gap-2 items-center !mt-2">
            @if (session('cus__success') || session('cus__error'))
                <div class="w-full flex flex-col gap-2">

                    @if (session('cus__success'))
                        <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full !mb-2" role="alert">
                            <span class="font-medium">{{ session('cus__success') }}</span>
                        </div>
                    @endif

                    @if (session('cus__error'))
                        <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full !mb-2" role="alert">
                            <span class="font-medium">{{ session('cus__error') }}</span>
                        </div>
                    @endif
                </div>

            @endif

            {{-- filters thing here --}}

            {{-- <div class="flex flex-wrap items-center content-card w-full">

                <div class="w-[65%] flex flex-wrap gap-2">

                    <div class="w-1/5">
                        <select name="" id="domain-category"
                            class="bg-gray-100  border border-gray-200 !w-full !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                            <option value="">select</option>
                            @if (isset($domainCategories) && count($domainCategories) > 0)
                                @foreach ($domainCategories as $domainCategory)
                                    <option value="{{ $domainCategory->id }}">{{ $domainCategory->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="w-3/5">
                        <form action="#" class="w-full flex flex-wrap justify-start items-center gap-1" method="post">
                            @csrf
                            <select name="actions" id=""
                                class="bg-gray-100 border border-gray-200 !w-2/5 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                <option value="">Bulk actions</option>
                                <option value="1">Delete</option>
                            </select>
                            <input type="hidden" name="bulk_ids" id="valHolders">
                            <button type="submit"
                                class="flex !p-3  !px-4 text-sm font-normal justify-center duration:600 transition-all bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                                Apply
                            </button>
                        </form>


                    </div>

                </div>
                <div class="w-[35%] flex flex-wrap gap-3 justify-end">


                    <div class="relative w-1/2 max-h-12 overflow-hidden">
                        <form method="GET" action="{{ url()->current() }}" class="relative w-full">

                            @foreach (request()->except('search') as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach

                            <input type="search" name="search" placeholder="search here" id="search_category"
                                value="{{ request('search') }}"
                                class="bg-gray-100 shadow border border-gray-200 !p-3 !pr-[50px] max-h-12 text-sm w-full rounded outline-none">

                            <button type="submit"
                                class="w-12 h-12 flex items-center justify-center bg-[var(--sidebar-bg)] absolute top-0 right-0 rounded-r">
                                <svg class="w-5 h-5 text-white !text-sm" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>

                        </form>


                    </div>

                </div>
            </div> --}}


        </div>


        {{-- ******************* Ends here  ***************** --}}

        <div class="w-full flex flex-wrap justify-between items-start content-card">
            @csrf
            <div class="flex items-center gap-2 !mb-4 flex-wrap">
                <h2 class="text-lg capitalize bg-[var(--primary-color)] text-white w-fit !p-3 rounded">
                    {{ $campaign->campaign_no }} Posts
                </h2>
                @if ($campaign->last_bulk_updated_at)
                    <span class="!px-2 !py-1 rounded text-xs font-semibold bg-green-100 text-green-700">
                        Campaign updated
                    </span>
                @endif
            </div>
            {{-- xxxxxxxxxxxxxxxxxx campaigns button xxxxxxxxxxxxxxxxxxxxxxxxxxxx --}}

            {{-- table code here --}}

            <div class="overflow-x-auto !mt-3 w-full">
                <table
                    class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                    <thead>
                        <tr class="bg-gray-800 text-white">
                            @php
                                $tHead = [
                                    'sno',
                                    'Campaign No',
                                    'Domain',
                                    'Article',
                                    'Type',
                                    'Remote Id',
                                    'Remote Url',
                                    'Attempt Count',
                                    'Last Error',
                                    'Next Retry',
                                    'Status',
                                    'Created At',
                                    'Actions',
                                ];
                            @endphp
                            @foreach ($tHead as $t)
                                <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left">
                                    {{ $t }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>

                        @forelse ($campaignPost as $index => $post)
                            <tr class="hover:bg-gray-50">

                                {{-- sno --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                    {{ $index + 1 + $offset }}
                                </td>

                                {{-- Campaign No --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3">
                                    {{ $campaign->campaign_no }}
                                </td>

                                {{-- Domain --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3">
                                    {{ optional($post->campaignDomain?->domain)->name ?? '-' }}
                                </td>

                                {{-- Article --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3 max-w-[220px] truncate"
                                    title="{{ optional($post->campaignArticle?->article)->name }}">
                                    {{ \Illuminate\Support\Str::limit(optional($post->campaignArticle?->article)->name ?? '-', 70) }}
                                </td>
                                {{-- Type --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3">
                                    {{ $post->is_sticky ? 'sticky Post' : 'Post' }}
                                </td>

                                {{-- Remote Id --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3">
                                    {{ $post->remote_id ?? '-' }}
                                </td>

                                {{-- Remote Url --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                    @if ($post->remote_url)
                                        <a href="{{ $post->remote_url }}" target="_blank"
                                            class="text-white bg-yellow-500 rounded !px-2 !py-1">
                                            view
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>

                                {{-- Attempt Count --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                    {{ $post->attempt_count }}
                                </td>

                                {{-- Last Error --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3 max-w-[220px] truncate"
                                    title="{{ $post->last_error }}">
                                    {{ $post->last_error ?? '-' }}
                                </td>

                                {{-- Next Retry --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3">
                                    {{ optional($post->next_retry_at)?->format('d M Y H:i') ?? '-' }}
                                </td>

                                {{-- Status: show "Updated" when post was updated (single or bulk) --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                    @php
                                        $statusMap = [
                                            'queued' => 'bg-gray-100 text-gray-700',
                                            'publishing' => 'bg-yellow-100 text-yellow-700',
                                            'success' => 'bg-green-100 text-green-700',
                                            'failed' => 'bg-red-100 text-red-700',
                                        ];
                                        $postStatusLabel = ($post->status === 'success' && $post->content_updated_at)
                                            ? 'Updated'
                                            : ucfirst($post->status);
                                    @endphp
                                    <span
                                        class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusMap[$post->status] ?? 'bg-gray-100' }}">
                                        {{ $postStatusLabel }}
                                    </span>
                                </td>

                                {{-- Created At --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3">
                                    {{ $post->created_at->format('d-M-Y H:i') }}
                                </td>

                                {{-- Actions --}}
                                <td class="border border-gray-200 font-sans !px-2 !py-3">
                                    <div class="flex gap-2 justify-center">

                                        {{-- View --}}
                                        @if ($post->remote_url)
                                            <a href="{{ $post->remote_url }}" target="_blank"
                                                class="bg-green-500 rounded w-7 h-7 flex items-center justify-center">
                                                <span
                                                    class="material-symbols-outlined !text-[16px] text-white text-sm">visibility</span>
                                            </a>
                                            <a href="{{ route('admin.campaign.edit.post', $post->id) }}" 
                                                class="bg-yellow-500 rounded w-7 h-7 flex items-center justify-center">
                                                <span
                                                    class="material-symbols-outlined !text-[16px] text-white text-sm">Edit</span>
                                            </a>
                                            {{-- {{ route('admin.campaign.edit.post', $post->id) }} --}}
                                            <a href="{{ route('admin.campaign.delete.post',$post->id) }}" 
                                                class="bg-red-500 rounded w-7 h-7 flex items-center justify-center">
                                                <span
                                                    class="material-symbols-outlined !text-[16px] text-white text-sm">delete</span>
                                            </a>

                                        @endif

                                        {{-- Manual retry: allow for queued, publishing, or failed (e.g. jobs killed) --}}
                                        @if ($post->status !== 'success')
                                            <a href="{{ route('admin.campaign.retry', $post->id) }}"
                                                class="bg-orange-500 rounded w-7 h-7 flex items-center justify-center"
                                                title="Manual retry from first">
                                                <span class="material-symbols-outlined text-white text-sm">refresh</span>
                                            </a>
                                        @endif

                                    </div>
                                </td>

                            </tr>

                        @empty
                            <tr>
                                <td colspan="13" class="text-center !py-4 text-gray-500 bg-gray-100 font-sans">
                                    No campaign posts found...
                                </td>
                            </tr>
                        @endforelse



                    </tbody>
                </table>
            </div>

            <div class="w-full !mt-2">
                {{ $campaignPost->links() }}
            </div>
        </div>


    @endsection




    @push('scripts')
        <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    @endpush
