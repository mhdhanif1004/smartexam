@props(['user' => null])

@if ($user)
    <td class="px-4 py-3 text-sm">
        <div x-data="{ password: '', visible: false, loading: false }" class="flex items-center gap-2">
            <span class="font-mono text-gray-600 dark:text-gray-300" x-text="visible ? password : '••••••••••'"></span>
            <button type="button"
                x-on:click="if (visible) { visible = false; password = ''; } else if (!loading) { loading = true; fetch('{{ route('admin.users.plain-password', $user) }}').then(r => { if (!r.ok) throw new Error(); return r.json(); }).then(d => { password = d.plain_password ?? '-'; visible = true; }).catch(() => {}).finally(() => { loading = false; }); }"
                :aria-label="visible ? 'Sembunyikan password' : 'Tampilkan password'"
                class="inline-flex items-center text-gray-400 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                <svg x-show="!visible" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178zM15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg x-show="visible" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
            </button>
        </div>
    </td>
@else
    <td class="px-4 py-3 text-sm text-gray-400 dark:text-gray-500">-</td>
@endif
