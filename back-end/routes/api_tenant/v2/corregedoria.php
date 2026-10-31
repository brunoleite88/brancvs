<?php

use App\V2\Corregedoria\CorregedoriaController;
use Illuminate\Support\Facades\Route;

Route::get('corregedoria/meu-relatorio/{mes}/{ano}', [CorregedoriaController::class, 'meuRelatorio']);
Route::post('corregedoria/salvar-rascunho', [CorregedoriaController::class, 'salvarRascunho']);
Route::post('corregedoria/relatorios/{id}/assinar', [CorregedoriaController::class, 'assinar']);
Route::get('corregedoria/unidades', [CorregedoriaController::class, 'unidades']);
Route::get('corregedoria/relatorios', [CorregedoriaController::class, 'index']);
Route::patch('corregedoria/relatorios/{id}/analise', [CorregedoriaController::class, 'analiseCorregedoria']);
