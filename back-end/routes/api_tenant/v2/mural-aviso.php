<?php

use App\V2\MuralAviso\MuralAvisoController as MuralAvisoV2;
use Illuminate\Support\Facades\Route;

Route::get('mural-aviso/pendentes', [MuralAvisoV2::class, 'pendentes']);
Route::get('mural-aviso/alertas-pendentes', [MuralAvisoV2::class, 'alertasPendentes']);
Route::post('mural-aviso/confirmar', [MuralAvisoV2::class, 'confirmar']);
Route::post('mural-aviso/publicar', [MuralAvisoV2::class, 'publicarNoTenant']);
Route::delete('mural-aviso/{id}', [MuralAvisoV2::class, 'excluirNoTenant']);
