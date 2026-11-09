<?php

declare(strict_types=1);

namespace App\V2\Usuario\Validators;

use App\Enums\Atribuicao;
use App\Enums\PerfilEnum;
use App\Exceptions\ValidateException;
use App\Models\Usuario;
use App\Repository\PerfilRepository;
use App\Repository\UnidadeRepository;

class UsuarioUpdateValidator
{
    private const ATRIBUICOES_GESTOR = [
        Atribuicao::GESTOR,
        Atribuicao::GESTOR_SUBSTITUTO,
        Atribuicao::DELEGADO,
    ];

    public function __construct(
        private readonly PerfilRepository $perfilRepository,
        private readonly UnidadeRepository $unidadeRepository,
    ) {}

    public function validarPerfil(string $perfilId, Usuario $alvo): void
    {
        $perfil = $this->perfilRepository->find($perfilId);

        if ($perfil === null) {
            throw new ValidateException('Perfil não encontrado.');
        }

        $isExterno = (bool) $alvo->usuario_externo;

        // Simplificação PGE-MA: qualquer usuário pode receber os perfis disponíveis no sistema
        // if ($isExterno && $perfil->nivel < PerfilEnum::COLABORADOR->value) { ... }
    }

    /**
     * @param array<int, array{unidade_id: string, atribuicoes?: string[]}> $atribuicoes
     */
    public function validarAtribuicoes(array &$atribuicoes, bool $isExterno = true): void
    {
        foreach ($atribuicoes as $integrante) {
            $this->validarUnidadeAtiva($integrante['unidade_id']);
            $this->validarAtribuicoesGestor($integrante);
        }

        if (!$isExterno) {
            $hasLotado = false;
            foreach ($atribuicoes as $integrante) {
                $atribs = $integrante['atribuicoes'] ?? [];
                if (in_array(Atribuicao::LOTADO->value, $atribs, true)) {
                    $hasLotado = true;
                    break;
                }
            }
            if (!$hasLotado && count($atribuicoes) > 0) {
                $primeiraAtrib = $atribuicoes[0]['atribuicoes'] ?? [];
                $primeiraAtrib = array_values(array_diff($primeiraAtrib, [Atribuicao::COLABORADOR->value]));
                array_unshift($primeiraAtrib, Atribuicao::LOTADO->value);
                $atribuicoes[0]['atribuicoes'] = array_values(array_unique($primeiraAtrib));
            } elseif (!$hasLotado) {
                throw new ValidateException('Usuário interno deve possuir ao menos uma lotação.');
            }
        }
    }

    private function validarPossuiLotacao(array $integrantes): void
    {
        foreach ($integrantes as $integrante) {
            $atribuicoes = $integrante['atribuicoes'] ?? [];
            if (in_array(Atribuicao::LOTADO->value, $atribuicoes, true)) {
                return;
            }
        }

        throw new ValidateException('Usuário interno deve possuir ao menos uma lotação.');
    }

    private function validarUnidadeAtiva(string $unidadeId): void
    {
        $unidade = $this->unidadeRepository->findById($unidadeId);

        if ($unidade === null || $unidade->data_inativacao !== null) {
            throw new ValidateException('Unidade não encontrada ou está inativada.');
        }
    }

    /**
     * @param array{unidade_id: string, atribuicoes?: string[]} $integrante
     */
    private function validarAtribuicoesGestor(array $integrante): void
    {
        $atribuicoes = $integrante['atribuicoes'] ?? [];

        $valoresGestor = array_map(fn (Atribuicao $a) => $a->value, self::ATRIBUICOES_GESTOR);
        $atribuicoesGestor = array_intersect($valoresGestor, $atribuicoes);

        if (count($atribuicoesGestor) > 1) {
            throw new ValidateException('Apenas uma atribuição de gestor por unidade.');
        }
    }
}
