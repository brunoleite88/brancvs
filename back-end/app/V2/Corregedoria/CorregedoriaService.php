<?php

declare(strict_types=1);

namespace App\V2\Corregedoria;

use App\Models\Afastamento;
use App\Models\RelatorioCorregedoria;
use App\Models\RelatorioCorregedoriaAto;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CorregedoriaService
{
    public function buscarOuCriarMeuRelatorio(string $usuarioId, int $mes, int $ano, ?string $unidadeId = null): RelatorioCorregedoria
    {
        $relatorio = RelatorioCorregedoria::with(['usuario:id,nome,email,matricula', 'unidade:id,nome,sigla', 'atos'])
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

    public function analiseCorregedoria(string $corregedorId, string $relatorioId, string $status, ?string $observacoes): RelatorioCorregedoria
    {
        $relatorio = RelatorioCorregedoria::findOrFail($relatorioId);
        $relatorio->status = $status;
        $relatorio->observacoes_corregedoria = $observacoes;
        $relatorio->save();

        return $relatorio->load(['usuario:id,nome,email,matricula', 'unidade:id,nome,sigla', 'atos']);
    }
}
