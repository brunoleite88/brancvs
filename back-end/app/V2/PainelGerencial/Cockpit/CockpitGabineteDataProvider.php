<?php

declare(strict_types=1);

namespace App\V2\PainelGerencial\Cockpit;

use Illuminate\Support\Facades\DB;

class CockpitGabineteDataProvider
{
    private const MACRO_AREAS = [
        'Contencioso e Regionais' => [
            'PCF', 'PT', 'PA', 'PJ', 'PJEFP', 'PPIMA', 'PSAU', 'PSP', 'PEDU',
            'PERJP', 'PDA', 'PRI', 'PRT', 'PB', 'NIRF', 'NC', 'NCIA'
        ],
        'Consultoria Jurídica' => [
            'PGAAJ', 'ASJUR', 'CSL'
        ],
        'Direção e Governança' => [
            'PGE-MA', 'GAB', 'SGA', 'PGA', 'PGAAE', 'PGARI', 'CG', 'CS', 'OUV'
        ],
        'Gestão Administrativa & TI' => [
            'UGAM', 'SGRH', 'SEO', 'USF', 'SCCF', 'SCC', 'SI', 'SDD', 'SMP', 'SGT', 'ASCOM'
        ],
        'Desenvolvimento & Escola' => [
            'ESAP', 'CEQUIDADE'
        ],
    ];

    public function getDados(): array
    {
        $unidades = DB::connection('tenant')->table('unidades')
            ->whereNull('data_inativacao')
            ->orderBy('sigla')
            ->get(['id', 'sigla', 'nome']);

        $planos = DB::connection('tenant')->table('planos_entregas')
            ->whereNull('deleted_at')
            ->where('status', 'ATIVO')
            ->get(['id', 'numero', 'nome', 'status', 'unidade_id', 'data_inicio', 'data_fim'])
            ->keyBy('unidade_id');

        $entregas = DB::connection('tenant')->table('planos_entregas_entregas')
            ->whereNull('deleted_at')
            ->get([
                'id',
                'plano_entrega_id',
                'unidade_id',
                'descricao_entrega',
                'progresso_esperado',
                'progresso_realizado',
                'data_inicio',
                'data_fim',
                'etiquetas',
            ]);

        $entregasPorPlano = [];
        $pilaresGlobais = [
            '1º P: Prazo' => 0,
            '2º P: Presença nos Tribunais' => 0,
            '3º P: Padronização e Prevenção' => 0,
            'Outros Eixos / Governança' => 0,
        ];
        $faixasPrazoGlobais = [
            'curto_prazo' => 0,   // até 90 dias
            'medio_prazo' => 0,   // 120 a 180 dias
            'estruturante' => 0,  // ciclo anual ou plurianual
        ];

        foreach ($entregas as $e) {
            $entregasPorPlano[$e->plano_entrega_id][] = $e;

            $tags = json_decode($e->etiquetas ?? '[]', true);
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    $k = $tag['key'] ?? '';
                    $v = $tag['value'] ?? '';

                    if ($k === 'pilar') {
                        if (isset($pilaresGlobais[$v])) {
                            $pilaresGlobais[$v]++;
                        } else {
                            $pilaresGlobais['Outros Eixos / Governança']++;
                        }
                    }

                    if ($k === 'prazo') {
                        if (in_array($v, ['30 dias', '45 dias', '60 dias', '90 dias'], true)) {
                            $faixasPrazoGlobais['curto_prazo']++;
                        } elseif (in_array($v, ['120 dias', '150 dias', '180 dias', 'Semestral'], true)) {
                            $faixasPrazoGlobais['medio_prazo']++;
                        } else {
                            $faixasPrazoGlobais['estruturante']++;
                        }
                    }
                }
            }
        }

        $resumoMacro = [];
        $unidadesDetalhes = [];
        $farolVerdeQtd = 0;
        $farolAmareloQtd = 0;
        $farolVermelhoQtd = 0;

        foreach ($unidades as $u) {
            $macro = $this->determinarMacroArea($u->sigla);
            $plano = $planos[$u->id] ?? null;
            $listaEntregas = $plano ? ($entregasPorPlano[$plano->id] ?? []) : [];
            $totalEntregas = count($listaEntregas);

            $progressoTotal = 0.0;
            $pilaresSetor = [];

            foreach ($listaEntregas as $e) {
                $progressoTotal += (float) $e->progresso_realizado;
                $tags = json_decode($e->etiquetas ?? '[]', true);
                if (is_array($tags)) {
                    foreach ($tags as $tag) {
                        if (($tag['key'] ?? '') === 'pilar') {
                            $pNome = $tag['value'];
                            $pilaresSetor[$pNome] = ($pilaresSetor[$pNome] ?? 0) + 1;
                        }
                    }
                }
            }

            $progressoMedio = $totalEntregas > 0 ? round($progressoTotal / $totalEntregas, 1) : 0.0;

            // Farol
            if (!$plano) {
                // Sede PGE-MA consolida as demais
                if ($u->sigla === 'PGE-MA') {
                    $farol = 'VERDE';
                    $situacao = 'Unidade Sede (Consolidada)';
                } else {
                    $farol = 'VERMELHO';
                    $situacao = 'Sem Plano Setorial Ativo';
                }
            } elseif ($totalEntregas === 0) {
                $farol = 'AMARELO';
                $situacao = 'Plano Ativo sem Entregas';
            } else {
                $farol = 'VERDE';
                $situacao = 'Plano Homologado / No Prazo';
            }

            if ($farol === 'VERDE') {
                $farolVerdeQtd++;
            } elseif ($farol === 'AMARELO') {
                $farolAmareloQtd++;
            } else {
                $farolVermelhoQtd++;
            }

            arsort($pilaresSetor);
            $pilarPredominante = !empty($pilaresSetor) ? array_key_first($pilaresSetor) : 'Governança';

            if (!isset($resumoMacro[$macro])) {
                $resumoMacro[$macro] = [
                    'macro_area' => $macro,
                    'total_unidades' => 0,
                    'total_entregas' => 0,
                    'verde' => 0,
                    'amarelo' => 0,
                    'vermelho' => 0,
                    'progresso_medio' => 0.0,
                ];
            }

            $resumoMacro[$macro]['total_unidades']++;
            $resumoMacro[$macro]['total_entregas'] += $totalEntregas;
            if ($farol === 'VERDE') $resumoMacro[$macro]['verde']++;
            elseif ($farol === 'AMARELO') $resumoMacro[$macro]['amarelo']++;
            else $resumoMacro[$macro]['vermelho']++;

            $unidadesDetalhes[] = [
                'id' => $u->id,
                'sigla' => $u->sigla,
                'nome' => $u->nome,
                'macro_area' => $macro,
                'plano_id' => $plano->id ?? null,
                'plano_numero' => $plano->numero ?? null,
                'plano_nome' => $plano->nome ?? null,
                'total_entregas' => $totalEntregas,
                'progresso_medio' => $progressoMedio,
                'farol' => $farol,
                'situacao' => $situacao,
                'pilar_predominante' => $pilarPredominante,
            ];
        }

        // Total de Projetos Estruturantes (GTs)
        $totalProjetosGT = DB::connection('tenant')->table('sgpe_projetos')->count();

        $totalUnidades = count($unidades);
        $totalPlanosAtivos = count($planos);
        $cobertura = $totalUnidades > 0 ? round(($totalPlanosAtivos / ($totalUnidades - 1)) * 100, 1) : 0.0;
        if ($cobertura > 100.0) $cobertura = 100.0;

        return [
            'kpis' => [
                'total_unidades' => $totalUnidades,
                'total_planos_ativos' => $totalPlanosAtivos,
                'cobertura_percentual' => $cobertura,
                'total_entregas' => count($entregas),
                'projetos_gt_total' => $totalProjetosGT > 0 ? $totalProjetosGT : 10,
                'farol' => [
                    'verde' => $farolVerdeQtd,
                    'amarelo' => $farolAmareloQtd,
                    'vermelho' => $farolVermelhoQtd,
                ],
                'faixas_prazo' => $faixasPrazoGlobais,
                'pilares' => $pilaresGlobais,
            ],
            'macro_areas' => array_values($resumoMacro),
            'unidades' => $unidadesDetalhes,
        ];
    }

    private function determinarMacroArea(string $sigla): string
    {
        $siglaUpper = strtoupper(trim($sigla));
        foreach (self::MACRO_AREAS as $area => $siglas) {
            if (in_array($siglaUpper, $siglas, true)) {
                return $area;
            }
        }
        return 'Outros Setores';
    }
}
