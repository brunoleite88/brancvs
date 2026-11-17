<?php

namespace App\Http\Controllers;

use App\Models\EixoTematico;
use App\Services\EixoTematicoService;
use Illuminate\Http\Request;
use App\Http\Controllers\ControllerBase;
use App\Exceptions\ServerException;

class EixoTematicoController extends ControllerBase {
    public function checkPermissions($action, $request, $service, $unidade, $usuario) {
        switch ($action) {
            case 'STORE':
                $entityId = $request->input('entity.id');
                $isEdit = !empty($entityId) && EixoTematico::where('id', $entityId)->exists();
                $permissao = $isEdit ? 'MOD_EXTM_EDT' : 'MOD_EXTM_INCL';
                $mensagem = $isEdit ? 'Edição não realizada' : 'Inserção não realizada';
                if (!$usuario->hasPermissionTo($permissao)) throw new ServerException("CapacidadeStore", $mensagem);
                break;
            case 'EDIT':
                if (!$usuario->hasPermissionTo('MOD_EXTM_EDT')) throw new ServerException("CapacidadeStore", "Edição não realizada");
                break;
            case 'DESTROY':
                if (!$usuario->hasPermissionTo('MOD_EXTM_EXCL')) throw new ServerException("CapacidadeStore", "Exclusão não realizada");
                break;
            case 'QUERY':
                if (!$usuario->hasPermissionTo('MOD_EXTM')) throw new ServerException("CapacidadeStore", "Consulta não realizada");
                break;
        }
    }
}
