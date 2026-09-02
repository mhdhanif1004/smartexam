<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel per ruangan: hanya pengawas yang ditugaskan di ruangan
// tersebut ATAU admin yang boleh mendengarkan. Dipakai untuk realtime
// pelanggaran — fallback polling tetap jalan bila Reverb down.
Broadcast::channel('violations.room.{roomId}', function ($user, $roomId) {
    if ($user->role === \App\Models\User::ROLE_ADMIN) {
        return true;
    }
    if ($user->role !== \App\Models\User::ROLE_PENGAWAS) {
        return false;
    }
    $supervisor = $user->supervisor;
    if (! $supervisor) {
        return false;
    }
    // cek penugasan hari ini atau ruangan statis
    $today = now()->toDateString();
    $assigned = $supervisor->roomAssignments()
        ->where('exam_date', $today)
        ->where('room_id', (int) $roomId)
        ->exists();
    if ($assigned) {
        return true;
    }
    return (int) ($supervisor->room_id ?? 0) === (int) $roomId;
});

Broadcast::channel('violations.admin', function ($user) {
    return $user->role === \App\Models\User::ROLE_ADMIN;
});
