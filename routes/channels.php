<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('alarmas.piscigranja.{piscigranjaId}', function ($user) {
    return $user->can('alarmasmodelo') || $user->can('modelosml');
});

Broadcast::channel('alarmas.modelos', function ($user) {
    return $user->can('alarmasmodelo') || $user->can('modelosml');
});
