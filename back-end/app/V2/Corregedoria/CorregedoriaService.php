<?php

declare(strict_types=1);

namespace App\V2\Corregedoria;

use App\Models\Afastamento;
use App\Models\RelatorioCorregedoria;
use App\Models\RelatorioCorregedoriaAto;
use App\Models\Unidade;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CorregedoriaService
{
    public function buscarOuCriarMeuRelatorio(string $usuarioId, int $mes, int $ano, ?string $unidadeId = null): RelatorioCorregedoria
    {
        if (empty($unidadeId)) { $u = Usuario::with(['lotacao.unidade', 'areasTrabalho.unidade'])->find($usuarioId); $unidadeId = ($u?->config['unidade_id'] ?? null) ?: ($u?->lotacao?->unidade_id ?? $u?->areasTrabalho?->first()?->unidade_id ?? '4f705d83-5808-4240-8b92-39ca88139076'); }
        $relatorio = RelatorioCorregedoria::with(['usuario:id,nome,email,matricula', 'unidade:id,nome,sigla', 'atos'])->where('unidade_id', $unidadeId)
            ->where('usuario_id', $usuarioId)
            ->where('mes', $mes)
            ->where('ano', $ano)
            ->first();

        if ($relatorio) {
            return $relatorio;
        }

        // Determina unidade se não informada
        if (!$unidadeId) {
            $usuario = Usuario::with('lotacao.unidade')->find($usuarioId);
            $unidadeId = $usuario?->lotacao?->unidade_id ?? '52d78c7d-e0c1-422b-b094-2ca5958d5ac1';
        }

        // Busca afastamentos no mês/ano para preencher o cabeçalho automaticamente
        $dtInicioMes = Carbon::create($ano, $mes, 1, 0, 0, 0);
        $dtFimMes = $dtInicioMes->copy()->endOfMonth();

        $afastamentos = Afastamento::with('tipoMotivoAfastamento')
            ->where('usuario_id', $usuarioId)
            ->where(function ($q) use ($dtInicioMes, $dtFimMes) {
                $q->whereBetween('data_inicio', [$dtInicioMes, $dtFimMes])
                  ->orWhereBetween('data_fim', [$dtInicioMes, $dtFimMes])
                  ->orWhere(function ($sub) use ($dtInicioMes, $dtFimMes) {
                      $sub->where('data_inicio', '<=', $dtInicioMes)
                          ->where('data_fim', '>=', $dtFimMes);
                  });
            })
            ->get();

        $afastamentosTexto = 'N/A';
        if ($afastamentos->isNotEmpty()) {
            $partes = [];
            foreach ($afastamentos as $af) {
                $nomeMotivo = $af->tipoMotivoAfastamento?->nome ?? 'Afastamento';
                $ini = Carbon::parse($af->data_inicio)->format('d/m/Y');
                $fim = Carbon::parse($af->data_fim)->format('d/m/Y');
                $partes[] = "{$nomeMotivo} ({$ini} a {$fim})";
            }
            $afastamentosTexto = implode('; ', $partes);
        }

        $novo = new RelatorioCorregedoria();
        $novo->id = (string) Str::uuid();
        $novo->usuario_id = $usuarioId;
        $novo->unidade_id = $unidadeId;
        $novo->mes = $mes;
        $novo->ano = $ano;
        $novo->afastamentos_texto = $afastamentosTexto;
        $novo->status = 'RASCUNHO';
        $novo->save();

        return $novo->load(['usuario:id,nome,email,matricula', 'unidade:id,nome,sigla', 'atos']);
    }

    public function salvarRascunho(string $usuarioId, array $data): RelatorioCorregedoria
    {
        $relatorioId = $data['id'] ?? null;
        $relatorio = null;
        if ($relatorioId) {
            $relatorio = RelatorioCorregedoria::find($relatorioId);
        }

        if (!$relatorio) {
            $mes = (int) ($data['mes'] ?? now()->month);
            $ano = (int) ($data['ano'] ?? now()->year);
            $relatorio = $this->buscarOuCriarMeuRelatorio($usuarioId, $mes, $ano, $data['unidade_id'] ?? null);
        }

        if ($relatorio->status === 'CONCLUIDO_ASSINADO' || $relatorio->status === 'HOMOLOGADO') {
            throw new \Exception('Relatório já assinado e consolidado não pode ser modificado como rascunho.');
        }

        if (isset($data['afastamentos_texto'])) {
            $relatorio->afastamentos_texto = $data['afastamentos_texto'];
        }
        if (array_key_exists('cabecalho', $data)) {
            $relatorio->cabecalho = is_array($data['cabecalho']) ? $data['cabecalho'] : json_decode((string) $data['cabecalho'], true);
            if (is_array($relatorio->cabecalho)) { $cab = $relatorio->cabecalho; $cab['subtitulo'] = Unidade::where('id', $relatorio->unidade_id)->value('nome') ?? ($cab['subtitulo'] ?? 'PROCURADORIA GERAL DO ESTADO'); $relatorio->cabecalho = $cab; }
        }
        $relatorio->save();

        // Atualiza a lista de atos
        if (isset($data['atos']) && is_array($data['atos'])) {
            DB::transaction(function () use ($relatorio, $data) {
                RelatorioCorregedoriaAto::where('relatorio_corregedoria_id', $relatorio->id)->delete();
                $ordem = 1;
                foreach ($data['atos'] as $atoItem) {
                    if (empty($atoItem['ato'])) continue;
                    $ato = new RelatorioCorregedoriaAto();
                    $ato->id = (string) Str::uuid();
                    $ato->relatorio_corregedoria_id = $relatorio->id;
                    $ato->numero_ordem = $ordem++;
                    $ato->processo = $atoItem['processo'] ?? null;
                    $ato->ato = $atoItem['ato'];
                    $ato->data_ato = !empty($atoItem['data_ato']) ? $atoItem['data_ato'] : now()->format('Y-m-d');
                    $ato->observacao = $atoItem['observacao'] ?? null;
                    $ato->vinculo_tipo = $atoItem['vinculo_tipo'] ?? 'NENHUM';
                    $ato->vinculo_codigo = $atoItem['vinculo_codigo'] ?? null;
                    $ato->vinculo_descricao = $atoItem['vinculo_descricao'] ?? null;
                    $ato->save();
                }
            });
        }

        return $relatorio->fresh(['usuario:id,nome,email,matricula', 'unidade:id,nome,sigla', 'atos']);
    }

    public function assinarDigitalmente(string $usuarioId, string $relatorioId): RelatorioCorregedoria
    {
        $relatorio = RelatorioCorregedoria::with('atos')->where('id', $relatorioId)->where('usuario_id', $usuarioId)->firstOrFail();

        if ($relatorio->atos->isEmpty()) {
            throw new \Exception('O relatório precisa conter pelo menos um ato registrado para ser assinado.');
        }

        $now = now();
        $payloadToHash = json_encode([
            'relatorio_id' => $relatorio->id,
            'usuario_id' => $usuarioId,
            'mes' => $relatorio->mes,
            'ano' => $relatorio->ano,
            'total_atos' => $relatorio->atos->count(),
            'data_assinatura' => $now->toIso8601String(),
            'salt' => config('app.key'),
        ]);

        $relatorio->assinatura_hash = hash('sha256', $payloadToHash);
        $relatorio->data_assinatura = $now;
        $relatorio->status = 'CONCLUIDO_ASSINADO';
        $relatorio->save();

        return $relatorio->load(['usuario:id,nome,email,matricula', 'unidade:id,nome,sigla', 'atos']);
    }

    public function listarRelatorios(array $filtros): LengthAwarePaginator
    {
        $query = RelatorioCorregedoria::with([
            'usuario:id,nome,email,matricula',
            'unidade:id,nome,sigla',
            'atos',
        ])->orderBy('ano', 'desc')->orderBy('mes', 'desc')->orderBy('updated_at', 'desc');

        if (!empty($filtros['mes'])) {
            $query->where('mes', (int) $filtros['mes']);
        }
        if (!empty($filtros['ano'])) {
            $query->where('ano', (int) $filtros['ano']);
        }
        if (!empty($filtros['unidade_id'])) {
            $query->where('unidade_id', $filtros['unidade_id']);
        }
        if (!empty($filtros['usuario_id'])) {
            $query->where('usuario_id', $filtros['usuario_id']);
        }
        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        $perPage = (int) ($filtros['size'] ?? 15);
        $page = (int) ($filtros['page'] ?? 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function listarUnidades(): array
    {
        return Unidade::select('id', 'nome', 'sigla')
            ->orderBy('nome', 'asc')
            ->get()
            ->toArray();
    }

    public function analiseCorregedoria(string $corregedorId, string $relatorioId, string $status, ?string $observacoes): RelatorioCorregedoria
    {
        $relatorio = RelatorioCorregedoria::findOrFail($relatorioId);
        $relatorio->status = $status;
        $relatorio->observacoes_corregedoria = $observacoes;
        $relatorio->save();

        return $relatorio->load(['usuario:id,nome,email,matricula', 'unidade:id,nome,sigla', 'atos']);
    }

    public function obterOpcoesVinculo(?string $unidadeSiglaOrId, ?string $usuarioId = null): array
    {
        $sigla = $unidadeSiglaOrId ?? '';
        if (!empty($unidadeSiglaOrId) && Str::isUuid($unidadeSiglaOrId)) {
            $unidade = Unidade::find($unidadeSiglaOrId);
            $sigla = $unidade?->sigla ?? '';
        }

        // 1. Iniciativas da Unidade
        $iniciativasQuery = DB::table('sgpe_iniciativas');
        if (!empty($sigla) && !in_array($sigla, ['PGE-MA', 'PGE', 'SEDE'])) {
            $iniciativasQuery->where(function ($q) use ($sigla) {
                $q->where('sigla_unidade', $sigla)
                  ->orWhere('unidades_parceiras', 'LIKE', "%{$sigla}%");
            });
        }
        $iniciativas = $iniciativasQuery->select([
            'id',
            'kr_codigo as codigo',
            'acao as titulo',
            'subacao as descricao',
            'eixo',
            'objetivo',
            'prazo',
            'pilar_3ps',
            'sigla_unidade'
        ])->orderBy('ordem')->get();

        // 2. Projetos Estruturantes (10 GTs)
        $projetosQuery = DB::table('sgpe_projetos');
        if (!empty($sigla) && !in_array($sigla, ['PGE-MA', 'PGE', 'SEDE'])) {
            $projetosQuery->where(function ($q) use ($sigla) {
                $q->where('unidades_participantes', 'LIKE', "%{$sigla}%")
                  ->orWhere('lider', 'LIKE', "%{$sigla}%")
                  ->orWhere('unidades_participantes', 'LIKE', '%41 Unidades%');
            });
        }
        $projetos = $projetosQuery->select([
            'id',
            'codigo',
            'titulo',
            'tipo',
            'lider',
            'prazo',
            'objetivo'
        ])->orderBy('codigo')->get();

        // 3. Entregas de Planos de Ação Individuais e Atividades Concluídas do Agente
        $entregasIndividuais = [];
        if ($usuarioId) {
            $pteRows = DB::table('planos_trabalhos_entregas as pte')
                ->join('planos_trabalhos as pt', 'pt.id', '=', 'pte.plano_trabalho_id')
                ->leftJoin('planos_entregas_entregas as pee', 'pee.id', '=', 'pte.plano_entrega_entrega_id')
                ->where('pt.usuario_id', $usuarioId)
                ->whereNull('pte.deleted_at')
                ->whereNull('pt.deleted_at')
                ->select([
                    'pte.id',
                    'pt.numero as plano_numero',
                    'pte.descricao as trabalho_planejado',
                    'pte.forca_trabalho',
                    'pee.descricao as entrega_setorial',
                ])
                ->orderBy('pt.numero', 'desc')
                ->get();

            foreach ($pteRows as $idx => $row) {
                $cod = 'PAI-' . ($row->plano_numero ?: ($idx + 1));
                $titulo = $row->entrega_setorial ?: ($row->trabalho_planejado ?: 'Entrega Individual');
                $entregasIndividuais[] = [
                    'id' => $row->id,
                    'codigo' => $cod,
                    'titulo' => Str::limit($titulo, 100),
                    'descricao' => $row->trabalho_planejado,
                    'tipo' => 'PLANO_INDIVIDUAL',
                ];
            }

            $atvRows = DB::table('atividades')
                ->where('usuario_id', $usuarioId)
                ->whereNull('deleted_at')
                ->orderBy('numero', 'desc')
                ->limit(30)
                ->get(['id', 'numero', 'descricao', 'status', 'data_entrega']);

            foreach ($atvRows as $atv) {
                $cod = 'ATV-' . $atv->numero;
                $entregasIndividuais[] = [
                    'id' => $atv->id,
                    'codigo' => $cod,
                    'titulo' => Str::limit((string) $atv->descricao, 100) . " ({$atv->status})",
                    'descricao' => $atv->descricao,
                    'tipo' => 'ATIVIDADE',
                ];
            }
        }

        return [
            'unidade_sigla' => $sigla,
            'projetos' => $projetos,
            'iniciativas' => $iniciativas,
            'entregas_individuais' => $entregasIndividuais,
        ];
    }

    /**
     * Importa atividades diárias concluídas e registros de execução do Plano de Ação Individual
     * do agente para o rascunho do Relatório Mensal da Corregedoria.
     */
    public function importarDoPlanoEAtividades(string $usuarioId, int $mes, int $ano, ?string $unidadeId = null): array
    {
        $relatorio = $this->buscarOuCriarMeuRelatorio($usuarioId, $mes, $ano, $unidadeId);

        if ($relatorio->status === 'CONCLUIDO_ASSINADO' || $relatorio->status === 'HOMOLOGADO') {
            throw new \Exception('Este relatório mensal já foi consolidado e assinado, não permitindo novas importações.');
        }

        $dtInicioMes = Carbon::create($ano, $mes, 1, 0, 0, 0);
        $dtFimMes = $dtInicioMes->copy()->endOfMonth();

        // Remove linha em branco inicial caso exista
        RelatorioCorregedoriaAto::where('relatorio_corregedoria_id', $relatorio->id)
            ->where(function ($q) {
                $q->whereNull('ato')->orWhere('ato', '');
            })
            ->where(function ($q) {
                $q->whereNull('observacao')->orWhere('observacao', '');
            })
            ->delete();

        $atosExistentes = RelatorioCorregedoriaAto::where('relatorio_corregedoria_id', $relatorio->id)->get();
        $codigosExistentes = $atosExistentes->pluck('vinculo_codigo')->filter()->toArray();
        $obsExistentes = $atosExistentes->pluck('observacao')->filter()->toArray();
        $proximaOrdem = ((int) $atosExistentes->max('numero_ordem')) + 1;
        $adicionados = 0;

        // 1. Buscar Atividades Diárias concluídas (ou iniciadas/entregues no mês)
        $atividades = DB::table('atividades as a')
            ->leftJoin('tipos_atividades as ta', 'ta.id', '=', 'a.tipo_atividade_id')
            ->where('a.usuario_id', $usuarioId)
            ->whereNull('a.deleted_at')
            ->where(function ($q) use ($dtInicioMes, $dtFimMes) {
                $q->whereBetween('a.data_entrega', [$dtInicioMes, $dtFimMes])
                  ->orWhereBetween('a.data_inicio', [$dtInicioMes, $dtFimMes])
                  ->orWhereBetween('a.updated_at', [$dtInicioMes, $dtFimMes])
                  ->orWhere('a.status', 'CONCLUIDO');
            })
            ->select([
                'a.id',
                'a.numero',
                'a.descricao',
                'a.status',
                'a.data_entrega',
                'a.data_inicio',
                'a.updated_at',
                'ta.nome as tipo_atividade_nome',
            ])
            ->orderBy('a.data_entrega', 'asc')
            ->get();

        foreach ($atividades as $atv) {
            $codigoVinculo = 'ATV-' . $atv->numero;
            $textoObs = "[{$codigoVinculo}] " . trim((string) $atv->descricao);
            if (in_array($codigoVinculo, $codigosExistentes, true) || in_array($textoObs, $obsExistentes, true)) {
                continue;
            }

            // Buscar comentário técnico de conclusão se houver
            $comentarioTecnico = DB::table('comentarios')
                ->where('atividade_id', $atv->id)
                ->where('tipo', 'TECNICO')
                ->whereNull('deleted_at')
                ->value('texto');

            if ($comentarioTecnico) {
                $textoObs .= ' — Conclusão: ' . trim((string) $comentarioTecnico);
            }

            // Extrair eventual número de processo da descrição
            $processoExtraido = null;
            if (preg_match('/(\d{4,7}[-\.]\d{2}[\.\d\/\-]+)/', (string) $atv->descricao, $matches)) {
                $processoExtraido = $matches[1];
            }

            $dtAto = $atv->data_entrega ?? $atv->data_inicio ?? $atv->updated_at ?? now();

            $novoAto = new RelatorioCorregedoriaAto();
            $novoAto->id = (string) Str::uuid();
            $novoAto->relatorio_corregedoria_id = $relatorio->id;
            $novoAto->numero_ordem = $proximaOrdem++;
            $novoAto->processo = $processoExtraido;
            $novoAto->ato = $atv->tipo_atividade_nome ?: 'Atividade Concluída (Agenda de Tarefas)';
            $novoAto->data_ato = Carbon::parse($dtAto)->format('Y-m-d');
            $novoAto->observacao = $textoObs;
            $novoAto->vinculo_tipo = 'PLANO_INDIVIDUAL';
            $novoAto->vinculo_codigo = $codigoVinculo;
            $novoAto->vinculo_descricao = Str::limit((string) $atv->descricao, 200);
            $novoAto->save();

            $codigosExistentes[] = $codigoVinculo;
            $obsExistentes[] = $textoObs;
            $adicionados++;
        }

        // 2. Buscar Registros de Execução (Contribuições) do Plano de Ação Individual V2
        $contribuicoes = DB::table('planos_trabalhos_consolidacoes_contribuicoes as c')
            ->join('planos_trabalhos_consolidacoes as ptc', 'ptc.id', '=', 'c.plano_trabalho_consolidacao_id')
            ->join('planos_trabalhos as pt', 'pt.id', '=', 'ptc.plano_trabalho_id')
            ->leftJoin('planos_trabalhos_entregas as pte', 'pte.id', '=', 'c.plano_trabalho_entrega_id')
            ->leftJoin('planos_entregas_entregas as pee', 'pee.id', '=', 'c.plano_entrega_entrega_id')
            ->where('pt.usuario_id', $usuarioId)
            ->whereNull('c.deleted_at')
            ->whereNull('ptc.deleted_at')
            ->whereNull('pt.deleted_at')
            ->whereNotNull('c.descricao')
            ->where('c.descricao', '!=', '')
            ->select([
                'c.id',
                'c.descricao as execucao_descricao',
                'c.esforco_executado',
                'c.registrado_at',
                'ptc.data_inicio as periodo_inicio',
                'ptc.data_fim as periodo_fim',
                'pt.numero as plano_numero',
                'pte.descricao as trabalho_planejado',
                'pee.descricao as entrega_setorial',
            ])
            ->orderBy('c.registrado_at', 'asc')
            ->get();

        foreach ($contribuicoes as $contrib) {
            $codigoVinculo = 'PAI-' . $contrib->plano_numero;
            $nomeEntrega = $contrib->entrega_setorial ?: ($contrib->trabalho_planejado ?: 'Entrega do Plano Individual');
            $textoObs = "[{$codigoVinculo}] Entrega: {$nomeEntrega} ({$contrib->esforco_executado}% CHD) — Execução: " . trim((string) $contrib->execucao_descricao);

            if (in_array($textoObs, $obsExistentes, true)) {
                continue;
            }

            $dtAto = $contrib->registrado_at ?? $contrib->periodo_fim ?? now();

            $novoAto = new RelatorioCorregedoriaAto();
            $novoAto->id = (string) Str::uuid();
            $novoAto->relatorio_corregedoria_id = $relatorio->id;
            $novoAto->numero_ordem = $proximaOrdem++;
            $novoAto->processo = null;
            $novoAto->ato = 'Objetivos e Planos de Metas';
            $novoAto->data_ato = Carbon::parse($dtAto)->format('Y-m-d');
            $novoAto->observacao = $textoObs;
            $novoAto->vinculo_tipo = 'PLANO_INDIVIDUAL';
            $novoAto->vinculo_codigo = $codigoVinculo;
            $novoAto->vinculo_descricao = Str::limit($nomeEntrega, 200);
            $novoAto->save();

            $obsExistentes[] = $textoObs;
            $adicionados++;
        }

        // 3. Caso não haja atividades nem contribuições, mas o usuário possua Entregas pactuadas no Plano de Ação Individual
        if ($adicionados === 0) {
            $entregasPactuadas = DB::table('planos_trabalhos_entregas as pte')
                ->join('planos_trabalhos as pt', 'pt.id', '=', 'pte.plano_trabalho_id')
                ->leftJoin('planos_entregas_entregas as pee', 'pee.id', '=', 'pte.plano_entrega_entrega_id')
                ->where('pt.usuario_id', $usuarioId)
                ->whereNull('pte.deleted_at')
                ->whereNull('pt.deleted_at')
                ->select([
                    'pte.id',
                    'pte.descricao as trabalho_planejado',
                    'pte.forca_trabalho',
                    'pt.numero as plano_numero',
                    'pt.data_inicio',
                    'pee.descricao as entrega_setorial',
                ])
                ->get();

            foreach ($entregasPactuadas as $ep) {
                $codigoVinculo = 'PAI-' . $ep->plano_numero;
                $nomeEntrega = $ep->entrega_setorial ?: ($ep->trabalho_planejado ?: 'Meta Individual');
                $textoObs = "[{$codigoVinculo}] Meta Pactuada ({$ep->forca_trabalho}% CHD): {$nomeEntrega}" . ($ep->trabalho_planejado ? " — {$ep->trabalho_planejado}" : '');

                if (in_array($textoObs, $obsExistentes, true)) {
                    continue;
                }

                $novoAto = new RelatorioCorregedoriaAto();
                $novoAto->id = (string) Str::uuid();
                $novoAto->relatorio_corregedoria_id = $relatorio->id;
                $novoAto->numero_ordem = $proximaOrdem++;
                $novoAto->processo = null;
                $novoAto->ato = 'Objetivos e Planos de Metas';
                $novoAto->data_ato = Carbon::create($ano, $mes, 1)->format('Y-m-d');
                $novoAto->observacao = $textoObs;
                $novoAto->vinculo_tipo = 'PLANO_INDIVIDUAL';
                $novoAto->vinculo_codigo = $codigoVinculo;
                $novoAto->vinculo_descricao = Str::limit($nomeEntrega, 200);
                $novoAto->save();

                $obsExistentes[] = $textoObs;
                $adicionados++;
            }
        }

        return [
            'relatorio' => $relatorio->fresh(['usuario:id,nome,email,matricula', 'unidade:id,nome,sigla', 'atos']),
            'importados' => $adicionados,
        ];
    }

    /**
     * Sincroniza automaticamente uma atividade concluída no dia com o rascunho do Relatório Mensal da Corregedoria.
     */
    public function sincronizarAtividadeConcluida(string $atividadeId, ?string $descricaoTecnica = null): void
    {
        $atv = DB::table('atividades as a')
            ->leftJoin('tipos_atividades as ta', 'ta.id', '=', 'a.tipo_atividade_id')
            ->where('a.id', $atividadeId)
            ->select([
                'a.id',
                'a.numero',
                'a.descricao',
                'a.usuario_id',
                'a.unidade_id',
                'a.data_entrega',
                'ta.nome as tipo_atividade_nome',
            ])
            ->first();

        if (!$atv || empty($atv->usuario_id)) {
            return;
        }

        $dtReferencia = $atv->data_entrega ? Carbon::parse($atv->data_entrega) : now();
        $mes = (int) $dtReferencia->month;
        $ano = (int) $dtReferencia->year;

        $relatorio = $this->buscarOuCriarMeuRelatorio($atv->usuario_id, $mes, $ano, $atv->unidade_id);
        if ($relatorio->status === 'CONCLUIDO_ASSINADO' || $relatorio->status === 'HOMOLOGADO') {
            return;
        }

        $codigoVinculo = 'ATV-' . $atv->numero;
        $jaExiste = RelatorioCorregedoriaAto::where('relatorio_corregedoria_id', $relatorio->id)
            ->where('vinculo_codigo', $codigoVinculo)
            ->exists();

        if ($jaExiste) {
            return;
        }

        $proximaOrdem = ((int) RelatorioCorregedoriaAto::where('relatorio_corregedoria_id', $relatorio->id)->max('numero_ordem')) + 1;
        $textoObs = "[{$codigoVinculo}] " . trim((string) $atv->descricao);
        if (!empty($descricaoTecnica)) {
            $textoObs .= ' — Conclusão: ' . trim($descricaoTecnica);
        }

        $novoAto = new RelatorioCorregedoriaAto();
        $novoAto->id = (string) Str::uuid();
        $novoAto->relatorio_corregedoria_id = $relatorio->id;
        $novoAto->numero_ordem = $proximaOrdem;
        $novoAto->processo = null;
        $novoAto->ato = $atv->tipo_atividade_nome ?: 'Atividade Concluída (Agenda de Tarefas)';
        $novoAto->data_ato = $dtReferencia->format('Y-m-d');
        $novoAto->observacao = $textoObs;
        $novoAto->vinculo_tipo = 'PLANO_INDIVIDUAL';
        $novoAto->vinculo_codigo = $codigoVinculo;
        $novoAto->vinculo_descricao = Str::limit((string) $atv->descricao, 200);
        $novoAto->save();
    }
}
