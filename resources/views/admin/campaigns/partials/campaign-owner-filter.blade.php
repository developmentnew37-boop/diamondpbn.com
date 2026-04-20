{{-- Super Admin: default to own campaigns; filter_user=all or filter_user={id} --}}
@if (!empty($showCampaignOwnerFilter) && $showCampaignOwnerFilter && isset($campaignOwnerUsers) && $campaignOwnerUsers->isNotEmpty())
    @php
        $meId = Auth::guard('admin')->id();
    @endphp
    <div class="w-full sm:w-auto shrink-0 min-w-0">
        <form method="GET" action="{{ url()->current() }}" class="w-full sm:w-auto sm:inline-block min-w-0">
            @foreach (request()->except(['filter_user', 'page']) as $key => $value)
                @continue(is_array($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <select name="filter_user" id="campaign-owner-filter" onchange="this.form.submit()"
                aria-label="Filter campaigns by owner"
                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full sm:min-w-[12rem] sm:max-w-[18rem] rounded outline-none focus:border-orange-600">
                <option value="" @selected(($campaignOwnerFilter ?? '') === 'mine')>My campaigns</option>
                <option value="all" @selected(($campaignOwnerFilter ?? '') === 'all')>All users</option>
                @foreach ($campaignOwnerUsers as $u)
                    @if ((int) $u->id !== (int) $meId)
                        <option value="{{ $u->id }}" @selected(($campaignOwnerFilter ?? '') === (string) (int) $u->id)>
                            {{ $u->name }}@if (!empty($u->email)) — {{ $u->email }}@endif
                        </option>
                    @endif
                @endforeach
            </select>
        </form>
    </div>
@endif
