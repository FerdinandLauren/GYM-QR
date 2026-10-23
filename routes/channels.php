<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('reception', fn (User $user): bool => in_array($user->role, ['receptionist', 'admin'], true));

Broadcast::channel('member.{userId}', fn (User $user, int $userId): bool => $user->role === 'member'
    && $user->user_id === $userId);
