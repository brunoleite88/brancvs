<?php

declare(strict_types=1);

namespace App\V2\PainelGerencial\Projetos;

use App\Models\Unidade;
use App\Repository\UnidadeRepository;
use App\V2\PainelGerencial\DTOs\DistribuicaoUnidadeDTO;
use App\V2\PainelGerencial\DTOs\FiltrosPainelDTO;
use App\V2\PainelGerencial\DTOs\IndicadorDTO;
use App\V2\PainelGerencial\Traits\ResolveHierarquiaPainel;
use Illuminate\Support\Facades\DB;

class ProjetosDataProvider
{
    use ResolveHierarquiaPainel;

    private const SEGMENTOS = [
        'Em Execução Prioritária',
        'Em Implantação / Validação',
        'Em Elaboração / Regulamentação',
    ];

    public function __construct(
        private readonly UnidadeRepository $unidadeRepository,
    ) {}

    protected function getUnidadeRepository(): UnidadeRepository
    {
        return $this->unidadeRepository;
    }

    public function getResumo(): array
    {
        $sgpeProjetos = DB::connection('tenant')->table('sgpe_projetos')->get();
        $projetosGui = DB::connection('tenant')->table('projetos')->select('id', 'nome', 'progresso', 'status')->get()->keyBy(function ($p) {
            // Match [GT-XX] prefix
            if (preg_match('/\[(GT-\d+)\]/i', $p->nome, $m)) {
                return strtoupper($m[1]);
            }
            return $p->nome;
        });

        $lista = [];
        $emExecucao = 0;
        $emImplantacao = 0;
        $emElaboracao = 0;
        $totalProgresso = 0;

        foreach ($sgpeProjetos as $sp) {
            $guid = null;
            $progresso = 0.0;
            $codeUpper = strtoupper($sp->codigo);
            if (isset($projetosGui[$codeUpper])) {
                $p = $projetosGui[$codeUpper];
                $guid = $p->id;
                $progresso = (float) $p->progresso;
            }

            $st = $sp->status;
            if (stripos($st, 'Execução') !== false) {
                $emExecucao++;
            } elseif (stripos($st, 'Implantação') !== false || stripos($st, 'Validação') !== false || stripos($st, 'Instalação') !== false) {
                $emImplantacao++;
            } else {
                $emElaboracao++;
            }

            $totalProgresso += $progresso;

            $lista[] = [
                'id' => $guid ?? (string) $sp->id,
                'codigo' => $sp->codigo,
                'titulo' => $sp->titulo,
                'tipo' => $sp->tipo,
                'lider' => $sp->lider,
                'unidades_participantes' => $sp->unidades_participantes,
                'status' => $sp->status,
                'prazo' => $sp->prazo,
                'objetivo' => $sp->objetivo,
                'krs_vinculados' => $sp->krs_vinculados,
                'progresso' => $progresso,
            ];
        }

        $total = count($lista);

        return [
            'totais' => [
                'total' => $total,
                'em_execucao' => $emExecucao,
                'em_implantacao' => $emImplantacao,
                'em_elaboracao' => $emElaboracao,
                'media_progresso' => $total > 0 ? round($totalProgresso / $total, 1) : 0,
            ],
            'projetos' => $lista,
        ];
    }

    public function getIndicadorPorUnidade(FiltrosPainelDTO $filtros): IndicadorDTO
    {
        $hierarquia = $this->resolverHierarquia($filtros->unidadeId);
        /** @var Unidade $unidade */
        $unidade = $hierarquia['unidade'];
        $filhas = $hierarquia['filhas'];

        $sgpeProjetos = DB::connection('tenant')->table('sgpe_projetos')->get();

        $distribuicoes = [];
        $distribuicoes[] = $this->calcularDistribuicaoUnidade($unidade, $sgpeProjetos);

        foreach ($filhas as $filha) {
            $distribuicoes[] = $this->calcularDistribuicaoUnidade($filha, $sgpeProjetos);
        }

        return (new IndicadorDTO(
            segmentos: self::SEGMENTOS,
            distribuicoes: $distribuicoes,
        ))->ordenarSubordinadasPorTotal();
    }

    private function calcularDistribuicaoUnidade(Unidade $unidade, $sgpeProjetos): DistribuicaoUnidadeDTO
    {
        $sigla = strtoupper(trim($unidade->sigla));
        $execucao = 0;
        $implantacao = 0;
        $elaboracao = 0;

        foreach ($sgpeProjetos as $sp) {
            $lider = strtoupper($sp->lider ?? '');
            $part = strtoupper($sp->unidades_participantes ?? '');

            // Check if unit is leader or participant or if root (PGE-MA) includes all
            $envolvido = ($sigla === 'PGE-MA') ||
                         str_contains($lider, $sigla) ||
                         str_contains($part, $sigla);

            if ($envolvido) {
                $st = $sp->status;
                if (stripos($st, 'Execução') !== false) {
                    $execucao++;
                } elseif (stripos($st, 'Implantação') !== false || stripos($st, 'Validação') !== false || stripos($st, 'Instalação') !== false) {
                    $implantacao++;
                } else {
                    $elaboracao++;
                }
            }
        }

        $valores = [$execucao, $implantacao, $elaboracao];
        $total = array_sum($valores);

        return new DistribuicaoUnidadeDTO(
            unidadeId: (string) $unidade->id,
            unidadeSigla: $unidade->sigla,
            valores: $valores,
            total: $total,
        );
    }
}
