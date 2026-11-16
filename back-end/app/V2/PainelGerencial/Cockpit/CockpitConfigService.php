<?php

declare(strict_types=1);

namespace App\V2\PainelGerencial\Cockpit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Gerencia a configuração persistida (widgets, macro-áreas e regras de farol) do Cockpit do Gabinete.
 */
class CockpitConfigService
{
    public const CHAVE_PADRAO = 'default';

    private const MACRO_AREAS_PADRAO = [
        [
            'id' => 'contencioso-regionais',
            'nome' => 'Contencioso e Regionais',
            'siglas' => [
                'PCF', 'PT', 'PA', 'PJ', 'PJEFP', 'PPIMA', 'PSAU', 'PSP', 'PEDU',
                'PERJP', 'PDA', 'PRI', 'PRT', 'PB', 'NIRF', 'NC', 'NCIA',
            ],
        ],
        [
            'id' => 'consultoria-juridica',
            'nome' => 'Consultoria Jurídica',
            'siglas' => ['PGAAJ', 'ASJUR', 'CSL'],
        ],
        [
            'id' => 'direcao-governanca',
            'nome' => 'Direção e Governança',
            'siglas' => ['PGE-MA', 'GAB', 'SGA', 'PGA', 'PGAAE', 'PGARI', 'CG', 'CS', 'OUV'],
        ],
        [
            'id' => 'gestao-admin-ti',
            'nome' => 'Gestão Administrativa & TI',
            'siglas' => ['UGAM', 'SGRH', 'SEO', 'USF', 'SCCF', 'SCC', 'SI', 'SDD', 'SMP', 'SGT', 'ASCOM'],
        ],
        [
            'id' => 'desenvolvimento-escola',
            'nome' => 'Desenvolvimento & Escola',
            'siglas' => ['ESAP', 'CEQUIDADE'],
        ],
    ];

    public function getDefaultConfig(): array
    {
        return [
            'titulo' => 'Cockpit Executivo do Gabinete',
            'subtitulo' => 'Monitoramento Estratégico Integrado dos Planos, Entregas e Indicadores da PGE-MA (Ciclo 2026–2030)',
            'widgets' => [
                'kpis' => [
                    ['id' => 'planos_setoriais', 'label' => 'Planos Setoriais', 'visivel' => true],
                    ['id' => 'total_entregas', 'label' => 'Entregas Estratégicas', 'visivel' => true],
                    ['id' => 'prazos_criticos', 'label' => 'Prazos Iniciais & Críticos', 'visivel' => true],
                    ['id' => 'projetos_estruturantes', 'label' => 'Projetos Estruturantes', 'visivel' => true],
                    ['id' => 'planos_individuais', 'label' => 'Planos Individuais', 'visivel' => true],
                    ['id' => 'repactuacoes', 'label' => 'Repactuações', 'visivel' => true],
                    ['id' => 'corregedoria_mes', 'label' => 'Relatórios Corregedoria', 'visivel' => true],
                    ['id' => 'ocorrencias_ativas', 'label' => 'Ocorrências Vigentes', 'visivel' => false],
                ],
                'secoes' => [
                    ['id' => 'kpis_globais', 'label' => 'Indicadores Executivos (KPIs)', 'visivel' => true],
                    ['id' => 'pilares_eixos', 'label' => 'Distribuição Estratégica (9 Eixos & Pilares 3 Ps)', 'visivel' => true],
                    ['id' => 'macro_areas', 'label' => 'Consolidação por Macro-Área', 'visivel' => true],
                    ['id' => 'tabela_unidades', 'label' => 'Painel Executivo por Setor', 'visivel' => true],
                ],
                'colunas_tabela' => [
                    'gestor' => true,
                    'progresso' => true,
                    'planos_individuais' => true,
                    'repactuacoes' => true,
                    'corregedoria' => true,
                ],
            ],
            'macro_areas' => self::MACRO_AREAS_PADRAO,
            'regras_farol' => [
                'progresso_verde_min' => 0,
                'progresso_amarelo_min' => 0,
                'dias_curto_prazo' => 90,
                'dias_medio_prazo' => 180,
                'dias_alerta_vencimento' => 15,
                'limite_vermelho_atrasos' => 1,
                'repactuacao_pendente_alerta' => true,
                'incluir_planos_nao_ativos' => true,
                'contabilizar_etiquetas_sgpe_prazo' => true,
                'unidades_consolidadoras' => ['PGE-MA'],
            ],
            'opcoes' => [
                'aba_estrategica_padrao' => 'eixos',
                'status_planos_padrao' => 'VALIDOS',
            ],
        ];
    }

    public function getConfig(): array
    {
        $default = $this->getDefaultConfig();

        if (!Schema::connection('tenant')->hasTable('cockpit_gabinete_configs')) {
            return $default;
        }

        $row = DB::connection('tenant')->table('cockpit_gabinete_configs')
            ->where('chave', self::CHAVE_PADRAO)
            ->whereNull('deleted_at')
            ->first();

        if (!$row) {
            return $default;
        }

        $widgets = $this->decodificarJson($row->widgets);
        $macroAreas = $this->decodificarJson($row->macro_areas);
        $regras = $this->decodificarJson($row->regras_farol);
        $opcoes = $this->decodificarJson($row->opcoes);

        return $this->normalizarConfig([
            'titulo' => $row->titulo ?: $default['titulo'],
            'subtitulo' => $row->subtitulo ?: $default['subtitulo'],
            'widgets' => $widgets ?? $default['widgets'],
            'macro_areas' => $macroAreas ?? $default['macro_areas'],
            'regras_farol' => $regras ?? $default['regras_farol'],
            'opcoes' => $opcoes ?? $default['opcoes'],
        ]);
    }

    public function saveConfig(array $input, ?string $usuarioId = null): array
    {
        $config = $this->normalizarConfig($input);
        $now = now();

        if (!Schema::connection('tenant')->hasTable('cockpit_gabinete_configs')) {
            return $config;
        }

        $existente = DB::connection('tenant')->table('cockpit_gabinete_configs')
            ->where('chave', self::CHAVE_PADRAO)
            ->first();

        $payload = [
            'titulo' => mb_substr(trim((string) $config['titulo']), 0, 180),
            'subtitulo' => mb_substr(trim((string) $config['subtitulo']), 0, 300),
            'widgets' => json_encode($config['widgets'], JSON_UNESCAPED_UNICODE),
            'macro_areas' => json_encode($config['macro_areas'], JSON_UNESCAPED_UNICODE),
            'regras_farol' => json_encode($config['regras_farol'], JSON_UNESCAPED_UNICODE),
            'opcoes' => json_encode($config['opcoes'], JSON_UNESCAPED_UNICODE),
            'atualizado_por_id' => $usuarioId,
            'updated_at' => $now,
            'deleted_at' => null,
        ];

        if ($existente) {
            DB::connection('tenant')->table('cockpit_gabinete_configs')
                ->where('id', $existente->id)
                ->update($payload);
        } else {
            DB::connection('tenant')->table('cockpit_gabinete_configs')->insert(array_merge($payload, [
                'id' => (string) Str::uuid(),
                'chave' => self::CHAVE_PADRAO,
                'created_at' => $now,
            ]));
        }

        return $this->getConfig();
    }

    public function resetConfig(?string $usuarioId = null): array
    {
        return $this->saveConfig($this->getDefaultConfig(), $usuarioId);
    }

    private function normalizarConfig(array $input): array
    {
        $default = $this->getDefaultConfig();

        $titulo = trim((string) ($input['titulo'] ?? '')) ?: $default['titulo'];
        $subtitulo = trim((string) ($input['subtitulo'] ?? '')) ?: $default['subtitulo'];

        $widgetsIn = is_array($input['widgets'] ?? null) ? $input['widgets'] : [];
        $kpis = $this->mesclarListaWidgets($widgetsIn['kpis'] ?? null, $default['widgets']['kpis']);
        $secoes = $this->mesclarListaWidgets($widgetsIn['secoes'] ?? null, $default['widgets']['secoes']);
        $colunas = array_merge(
            $default['widgets']['colunas_tabela'],
            array_map('boolval', is_array($widgetsIn['colunas_tabela'] ?? null) ? $widgetsIn['colunas_tabela'] : [])
        );

        $macroAreas = $this->normalizarMacroAreas($input['macro_areas'] ?? null, $default['macro_areas']);
        $regrasIn = is_array($input['regras_farol'] ?? null) ? $input['regras_farol'] : [];
        $regras = [
            'progresso_verde_min' => $this->clampInt($regrasIn['progresso_verde_min'] ?? $default['regras_farol']['progresso_verde_min'], 0, 100),
            'progresso_amarelo_min' => $this->clampInt($regrasIn['progresso_amarelo_min'] ?? $default['regras_farol']['progresso_amarelo_min'], 0, 100),
            'dias_curto_prazo' => $this->clampInt($regrasIn['dias_curto_prazo'] ?? $default['regras_farol']['dias_curto_prazo'], 1, 730),
            'dias_medio_prazo' => $this->clampInt($regrasIn['dias_medio_prazo'] ?? $default['regras_farol']['dias_medio_prazo'], 1, 1460),
            'dias_alerta_vencimento' => $this->clampInt($regrasIn['dias_alerta_vencimento'] ?? $default['regras_farol']['dias_alerta_vencimento'], 1, 180),
            'limite_vermelho_atrasos' => $this->clampInt($regrasIn['limite_vermelho_atrasos'] ?? $default['regras_farol']['limite_vermelho_atrasos'], 1, 999),
            'repactuacao_pendente_alerta' => (bool) ($regrasIn['repactuacao_pendente_alerta'] ?? $default['regras_farol']['repactuacao_pendente_alerta']),
            'incluir_planos_nao_ativos' => (bool) ($regrasIn['incluir_planos_nao_ativos'] ?? $default['regras_farol']['incluir_planos_nao_ativos']),
            'contabilizar_etiquetas_sgpe_prazo' => (bool) ($regrasIn['contabilizar_etiquetas_sgpe_prazo'] ?? $default['regras_farol']['contabilizar_etiquetas_sgpe_prazo']),
            'unidades_consolidadoras' => $this->normalizarSiglas($regrasIn['unidades_consolidadoras'] ?? $default['regras_farol']['unidades_consolidadoras']),
        ];

        $opcoesIn = is_array($input['opcoes'] ?? null) ? $input['opcoes'] : [];
        $opcoes = [
            'aba_estrategica_padrao' => in_array($opcoesIn['aba_estrategica_padrao'] ?? '', ['eixos', 'pilares'], true)
                ? $opcoesIn['aba_estrategica_padrao']
                : $default['opcoes']['aba_estrategica_padrao'],
            'status_planos_padrao' => in_array($opcoesIn['status_planos_padrao'] ?? '', ['VALIDOS', 'ATIVO', 'HOMOLOGANDO', 'CONCLUIDO'], true)
                ? $opcoesIn['status_planos_padrao']
                : $default['opcoes']['status_planos_padrao'],
        ];

        return [
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'widgets' => [
                'kpis' => $kpis,
                'secoes' => $secoes,
                'colunas_tabela' => $colunas,
            ],
            'macro_areas' => $macroAreas,
            'regras_farol' => $regras,
            'opcoes' => $opcoes,
        ];
    }

    /**
     * Preserva a ordem personalizada dos widgets garantindo que novos widgets adicionados no código apareçam ao final.
     */
    private function mesclarListaWidgets(?array $recebida, array $padrao): array
    {
        if (empty($recebida)) {
            return $padrao;
        }

        $padraoById = [];
        foreach ($padrao as $item) {
            $padraoById[$item['id']] = $item;
        }

        $resultado = [];
        $vistos = [];

        foreach ($recebida as $item) {
            $id = (string) ($item['id'] ?? '');
            if (!$id || !isset($padraoById[$id]) || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $label = trim((string) ($item['label'] ?? ''));
            $resultado[] = [
                'id' => $id,
                'label' => $label !== '' ? mb_substr($label, 0, 80) : $padraoById[$id]['label'],
                'visivel' => array_key_exists('visivel', $item) ? (bool) $item['visivel'] : $padraoById[$id]['visivel'],
            ];
        }

        foreach ($padrao as $item) {
            if (!isset($vistos[$item['id']])) {
                $resultado[] = $item;
            }
        }

        return $resultado;
    }

    private function normalizarMacroAreas(?array $recebida, array $padrao): array
    {
        if (!is_array($recebida) || empty($recebida)) {
            return $padrao;
        }

        $resultado = [];
        foreach ($recebida as $idx => $area) {
            $nome = trim((string) ($area['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $id = trim((string) ($area['id'] ?? '')) ?: Str::slug($nome) . '-' . ($idx + 1);
            $resultado[] = [
                'id' => $id,
                'nome' => mb_substr($nome, 0, 120),
                'siglas' => $this->normalizarSiglas($area['siglas'] ?? []),
            ];
        }

        return !empty($resultado) ? $resultado : $padrao;
    }

    private function normalizarSiglas(mixed $siglas): array
    {
        if (!is_array($siglas)) {
            return [];
        }
        $norm = array_values(array_unique(array_filter(array_map(
            static fn($s) => strtoupper(trim((string) $s)),
            $siglas
        ))));
        return $norm;
    }

    private function clampInt(mixed $val, int $min, int $max): int
    {
        return max($min, min($max, (int) $val));
    }

    private function decodificarJson(mixed $raw): mixed
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }
}
