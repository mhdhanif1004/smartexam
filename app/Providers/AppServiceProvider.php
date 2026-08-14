<?php

namespace App\Providers;

use App\Models\ExamSchedule;
use App\Models\SupervisorAttendance;
use App\Models\SupervisorRoomAssignment;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RedirectIfAuthenticated::redirectUsing(function (Request $request) {
            return $request->expectsJson() ? null : route(auth()->user()->dashboardRoute());
        });

        Event::listen(Login::class, function (Login $event) {
            $user = $event->user;

            if (! $user->isPengawas()) {
                return;
            }

            $supervisor = $user->supervisor;

            if (! $supervisor) {
                return;
            }

            $now = now();
            $today = $now->copy()->startOfDay();
            $tomorrow = $today->copy()->addDay();

            // Ruangan hari ini berasal dari penugasan rotasi pengawas; fallback
            // ke ruangan statis lama bila belum ada penugasan rotasi.
            $roomIds = SupervisorRoomAssignment::query()
                ->where('supervisor_id', $supervisor->id)
                ->where('exam_date', '>=', $today)
                ->where('exam_date', '<', $tomorrow)
                ->pluck('room_id')
                ->all();

            if ($roomIds === [] && $supervisor->room_id !== null) {
                $roomIds = [$supervisor->room_id];
            }

            if ($roomIds === []) {
                return;
            }

            // Ambil semua jadwal hari ini di ruangan pengawas; status real-time
            // dihitung via computedStatus() (jangan pakai kolom status statis).
            $todaysSchedules = ExamSchedule::query()
                ->whereIn('room_id', $roomIds)
                ->where('exam_date', '>=', $today)
                ->where('exam_date', '<', $tomorrow)
                ->get();

            foreach ($todaysSchedules as $schedule) {
                if ($schedule->computedStatus() === ExamSchedule::STATUS_FINISHED) {
                    continue;
                }

                $start = $schedule->examStart();
                $end = $schedule->examEnd();

                // Jika pengawas login SEBELUM ujian dimulai, tandai "Hadir" dengan check_in = start_time jadwal
                // Jika login SAAT ujian berlangsung, gunakan now()
                // Jika login SETELAH ujian selesai, tidak tandai hadir (sudah lewat)
                if ($now->lt($start)) {
                    // Login sebelum ujian dimulai - jadwalkan check-in di waktu mulai ujian
                    $checkInTime = $start;
                } elseif ($now->gte($start) && $now->lt($end)) {
                    // Login saat ujian berlangsung - gunakan waktu sekarang
                    $checkInTime = $now;
                } else {
                    // Login setelah ujian selesai - lewati
                    continue;
                }

                SupervisorAttendance::updateOrCreate(
                    [
                        'supervisor_id' => $supervisor->id,
                        'exam_schedule_id' => $schedule->id,
                    ],
                    [
                        'room_id' => $schedule->room_id,
                        'status' => SupervisorAttendance::STATUS_PRESENT,
                        'checked_in_at' => $checkInTime,
                    ]
                );
            }
        });
    }
}
