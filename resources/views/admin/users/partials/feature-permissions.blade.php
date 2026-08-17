@php
    $featurePermissions = $featurePermissions ?? config('admin_permissions', []);
    $assignedPermissions = $assignedPermissions ?? [];
    $adminRoleId = \App\Models\Admin::ADMIN;
@endphp

@if (!empty($featurePermissions))
    <div id="feature-permissions-panel" class="w-full flex flex-col gap-2 !mt-4" data-admin-role-id="{{ $adminRoleId }}">
        <label class="text-sm font-medium text-gray-700">Feature permissions</label>
        <p class="text-xs text-gray-500">
            Admin role only. Grant extra powers without making this user a Super Admin.
            Members cannot receive these permissions.
        </p>
        <div class="flex flex-col gap-2 !mt-1 border border-gray-200 rounded !p-3 bg-gray-50">
            @foreach ($featurePermissions as $key => $meta)
                <label class="flex it
                
                
                ems-start gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox"
                        name="permissions[]"
                        value="{{ $key }}"
                        class="!mt-1 feature-permission-checkbox"
                        {{ in_array($key, old('permissions', $assignedPermissions), true) ? 'checked' : '' }}>
                    <span>
                        <span class="font-medium">{{ $meta['label'] ?? $key }}</span>
                        @if (! empty($meta['description']))
                            <span class="block text-xs text-gray-500">{{ $meta['description'] }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
        @error('permissions')
            <p class="!m-0 text-sm text-red-500">{{ $message }}</p>
        @enderror
        @error('permissions.*')
            <p class="!m-0 text-sm text-red-500">{{ $message }}</p>
        @enderror
    </div>
    <script>
        (function () {
            var panel = document.getElementById('feature-permissions-panel');
            if (!panel) return;
            var adminRoleId = String(panel.getAttribute('data-admin-role-id') || '1');
            var roleSelect = document.querySelector('select[name="roles"]');
            if (!roleSelect) return;

            function syncVisibility() {
                var isAdmin = String(roleSelect.value) === adminRoleId;
                panel.style.display = isAdmin ? '' : 'none';
                if (!isAdmin) {
                    panel.querySelectorAll('.feature-permission-checkbox').forEach(function (el) {
                        el.checked = false;
                    });
                }
            }

            roleSelect.addEventListener('change', syncVisibility);
            syncVisibility();
        })();
    </script>
@endif
