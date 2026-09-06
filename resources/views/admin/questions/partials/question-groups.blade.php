{{--
    Partial untuk render Level 2 (grup kelas target) + Level 3 (tabel soal).
    Variabel: $groups (array dari groupQuestionsByClassroom), $subject (Subject), $search (string).
--}}
@if (empty($groups))
    <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
        Belum ada soal untuk mata pelajaran ini.
    </div>
@else
    @foreach ($groups as $groupKey => $group)
        @php
            $groupIdentifier = $subject->id . '-' . $groupKey;
        @endphp
        <div class="border-t border-gray-100 dark:border-gray-800">
            <div
                role="button"
                tabindex="0"
                @click="toggleGroup('{{ $groupIdentifier }}')"
                @keydown.enter="toggleGroup('{{ $groupIdentifier }}')"
                @keydown.space.prevent="toggleGroup('{{ $groupIdentifier }}')"
                class="flex w-full items-center justify-between gap-3 bg-gray-50/60 px-6 py-2.5 text-left transition hover:bg-gray-100/60 dark:bg-gray-800/20 dark:hover:bg-gray-800/40"
            >
                <span class="flex min-w-0 items-center gap-2.5">
                    <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200 dark:text-gray-500"
                         :class="isGroupExpanded('{{ $groupIdentifier }}') ? 'rotate-180' : ''"
                         fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Kelas: {{ \App\Models\Classroom::summarizeTargets($group['classroom_ids']) }}
                    </span>
                    <span class="shrink-0 rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {{ count($group['questions']) }} soal
                    </span>
                    @php
                        $groupWeightBadges = [];
                        foreach (($group['classroom_ids'] ?? []) as $cid) {
                            $chk = $weightChecks[$subject->id][$cid] ?? null;
                            if ($chk === null) continue;
                            $total = (float) ($chk['total'] ?? 0);
                            $delta = (float) ($chk['delta'] ?? 0);
                            $status = $chk['status'] ?? 'ok';
                            $groupWeightBadges[] = [
                                'cid' => $cid,
                                'name' => $classroomIdToName[$cid] ?? "Kelas #{$cid}",
                                'total' => $total,
                                'delta' => $delta,
                                'status' => $status,
                            ];
                        }
                    @endphp
                    @if (! empty($groupWeightBadges))
                        <span class="flex flex-wrap items-center gap-1.5">
                            @foreach ($groupWeightBadges as $badge)
                                @php
                                    $cls = $badge['status'] === 'ok'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/20'
                                        : ($badge['status'] === 'over'
                                            ? 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300 border-rose-200 dark:border-rose-500/20'
                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300 border-amber-200 dark:border-amber-500/20');
                                    $totalFmt = number_format($badge['total'], 2, ',', '.');
                                    $deltaFmt = number_format($badge['delta'], 2, ',', '.');
                                    $label = $badge['status'] === 'ok'
                                        ? "Bobot {$totalFmt} ✓"
                                        : ($badge['status'] === 'over' ? "Bobot {$totalFmt} · kelebihan {$deltaFmt}" : "Bobot {$totalFmt} · kurang {$deltaFmt}");
                                    $title = $badge['name'] . ': total ' . $totalFmt . ' (harus 100, ' . ($badge['status'] === 'ok' ? 'pas' : ($badge['status'] === 'over' ? 'kelebihan '.$deltaFmt : 'kekurangan '.$deltaFmt)) . ')';
                                @endphp
                                <span title="{{ $title }}" class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $cls }}">
                                    {{ $badge['name'] }} · {{ $label }}
                                </span>
                            @endforeach
                        </span>
                    @endif
                </span>
                <span class="flex shrink-0 items-center gap-1.5">
                    <button type="button"
                            @click.stop="groupEdit.open(@js($group['classroom_ids']), {{ count($group['questions']) }}, @js($group['questions']->pluck('id')->values()->all()))"
                            title="Ubah kelas target untuk semua soal dalam grup ini"
                            class="rounded bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                        Edit
                    </button>
                    <button type="button"
                            @click.stop="groupDelete.open(@js($group['questions']->pluck('id')->values()->all()), @js(implode(', ', $group['classroom_names'])))"
                            title="Hapus semua soal dalam grup ini"
                            class="rounded bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20">
                        Hapus
                    </button>
                </span>
            </div>

            <div x-show="isGroupExpanded('{{ $groupIdentifier }}')" x-transition x-cloak
                 class="border-t border-gray-100 dark:border-gray-800">
                @include('admin.questions.partials.question-table', [
                    'questions' => $group['questions'],
                    'subject' => $subject,
                    'search' => $search,
                ])
            </div>
        </div>
    @endforeach
@endif
