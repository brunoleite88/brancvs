<?php

declare(strict_types=1);

namespace App\V2\Usuario\DTOs;

class UsuarioStoreDTO
{
    /**
     * @param array<int, array{unidade_id: string, atribuicoes?: string[]}> $atribuicoes
     */
    public function __construct(
        public readonly string $cpf,
        public readonly string $email,
        public readonly string $nome,
        public readonly string $perfilId,
        public readonly array $atribuicoes,
        public readonly ?string $apelido = null,
        public readonly ?string $telefone = null,
        public readonly ?string $dataNascimento = null,
        public readonly ?string $uf = null,
        public readonly ?string $sexo = null,
        public readonly ?string $matricula = null,
        public readonly ?string $password = null,
        public readonly ?string $situacaoFuncional = null,
        public readonly ?string $situacaoSiape = null,
        public readonly ?string $participaPgd = null,
        public readonly ?string $modalidadePgd = null,
        public readonly ?string $textoComplementarPlano = null,
        public readonly ?bool $usuarioExterno = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            cpf: $data['cpf'],
            email: $data['email'],
            nome: $data['nome'],
            perfilId: $data['perfil_id'],
            atribuicoes: $data['atribuicoes'],
            apelido: $data['apelido'] ?? null,
            telefone: $data['telefone'] ?? null,
            dataNascimento: $data['data_nascimento'] ?? null,
            uf: $data['uf'] ?? null,
            sexo: $data['sexo'] ?? null,
            matricula: $data['matricula'] ?? null,
            password: $data['password'] ?? null,
            situacaoFuncional: $data['situacao_funcional'] ?? null,
            situacaoSiape: $data['situacao_siape'] ?? null,
            participaPgd: $data['participa_pgd'] ?? null,
            modalidadePgd: $data['modalidade_pgd'] ?? null,
            textoComplementarPlano: $data['texto_complementar_plano'] ?? null,
            usuarioExterno: isset($data['usuario_externo']) ? (bool) $data['usuario_externo'] : true,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $arr = [
            'nome' => $this->nome,
            'email' => $this->email,
            'cpf' => $this->cpf,
            'apelido' => $this->apelido ?? $this->nome,
            'telefone' => $this->telefone,
            'data_nascimento' => $this->dataNascimento,
            'uf' => $this->uf,
            'sexo' => $this->sexo,
            'matricula' => $this->matricula,
            'perfil_id' => $this->perfilId,
            'usuario_externo' => $this->usuarioExterno ?? true,
            'situacao_funcional' => $this->situacaoFuncional ?? 'ESTATUTARIO_SERVIDOR',
            'situacao_siape' => $this->situacaoSiape ?? 'ATIVO',
            'participa_pgd' => $this->participaPgd ?? 'não',
            'modalidade_pgd' => $this->modalidadePgd,
            'texto_complementar_plano' => $this->textoComplementarPlano,
        ];
        if (!empty($this->password)) {
            $arr['password'] = \Illuminate\Support\Facades\Hash::make($this->password);
        }
        return $arr;
    }
}
