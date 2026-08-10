<?php

namespace App\Providers;

use App\Models\ExamSchedule;
use App\Models\SupervisorAttendance;
use Carbon\Carbon;
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

            if (! $supervisor || ! $supervisor->room_id) {
                return;
            }

            $now = now();
            $today = $now->copy()->startOfDay();
            $tomorrow = $today->copy()->addDay();
            $nowTime = $now->format('H:i:s');

            // Gunakan filter WAKTU sebagai filter utama (lebih andal dari kolom status statis)
            // Ambil semua jadwal hari ini di ruangan pengawas yang statusnya BUKAN finished
            $todaysSchedules = ExamSchedule::query()
                ->where('room_id', $supervisor->room_id)
                ->where('exam_date', '>=', $today)
                ->where('exam_date', '<', $tomorrow)
                ->where('status', '!=', ExamSchedule::STATUS_FINISHED)
                ->get();

            foreach ($todaysSchedules as $schedule) {
                $start = Carbon::parse($schedule->exam_date->format('Y-m-d').' '.$schedule->start_time);
                $end = $start->copy()->addMinutes($schedule->duration_minutes);

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
                        'room_id' => $supervisor->room_id,
                        'status' => SupervisorAttendance::STATUS_PRESENT,
                        'checked_in_at' => $checkInTime,
                    ]
                );
            }
        });
    }
}
