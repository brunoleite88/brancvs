<?php

declare(strict_types=1);

namespace App\V2\PainelGerencial\Sei;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SeiDocumentoService
{
    public function gerarPdfRepactuacao(string $id): array
    {
        $r = DB::connection('tenant')->table('planos_entregas_repactuacoes as r')
            ->leftJoin('unidades as u', 'u.id', '=', 'r.unidade_id')
            ->leftJoin('planos_entregas_entregas as pee', 'pee.id', '=', 'r.plano_entrega_entrega_id')
            ->leftJoin('usuarios as usol', 'usol.id', '=', 'r.solicitante_id')
            ->leftJoin('usuarios as uana', 'uana.id', '=', 'r.analisador_id')
            ->where('r.id', $id)
            ->whereNull('r.deleted_at')
            ->select([
                'r.*',
                'u.sigla as unidade_sigla',
                'u.nome as unidade_nome',
                'usol.nome as solicitante_nome',
                'uana.nome as analisador_nome',
                'pee.descricao_entrega',
            ])
            ->first();

        if (!$r) {
            throw new \InvalidArgumentException('Repactuação não encontrada.');
        }

        $numeroSei = $r->numero_processo_sei ?? ('PGE-PRO-' . date('Y') . '/' . str_pad((string) rand(100, 999), 5, '0', STR_PAD_LEFT));
        $dataGeracao = Carbon::now()->format('d/m/Y H:i');
        $hashValidacao = strtoupper(substr(hash('sha256', $r->id . ($r->data_homologacao ?? $dataGeracao)), 0, 16));

        $motivoLabels = [
            'IMPEDIMENTO_ORCAMENTARIO' => 'Impedimento Orçamentário / BID (PROFISCO III)',
            'ATRASO_EXTERNO' => 'Atraso Externo / Tribunais / Órgãos Públicos',
            'REORGANIZACAO_INTERNA' => 'Reorganização Interna de Equipe e Carga de Trabalho',
            'EXPANSAO_DEMANDA' => 'Expansão de Demanda Institucional',
            'ADEQUACAO_TECNICA' => 'Adequação Técnica / Ferramental Tecnológico',
            'OUTRO' => 'Outros Motivos Justificados',
        ];
        $motivoTexto = $motivoLabels[$r->motivo] ?? $r->motivo;

        $statusColor = match ($r->status) {
            'HOMOLOGADO' => '#198754',
            'PENDENTE' => '#d97706',
            'REJEITADO' => '#dc3545',
            default => '#6c757d',
        };

        $statusLabel = match ($r->status) {
            'HOMOLOGADO' => 'HOMOLOGADO PELO GABINETE',
            'PENDENTE' => 'PENDENTE DE DELIBERAÇÃO',
            'REJEITADO' => 'DEVOLVIDO COM RESSALVAS',
            default => $r->status,
        };

        $dataAnteriorFmt = $r->data_anterior ? Carbon::parse($r->data_anterior)->format('d/m/Y') : 'Não estipulado';
        $dataNovaFmt = $r->data_nova ? Carbon::parse($r->data_nova)->format('d/m/Y') : 'Inalterado (Ajuste de Meta/Escopo)';
        $dataAberturaFmt = Carbon::parse($r->created_at)->format('d/m/Y H:i');
        $dataHomologacaoFmt = $r->data_homologacao ? Carbon::parse($r->data_homologacao)->format('d/m/Y H:i') : 'Pendente';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Termo de Deliberação SEI-MA - {$numeroSei}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #212529;
            margin: 25px 35px;
            font-size: 12px;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #003366;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h3 {
            margin: 0;
            color: #003366;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header h4 {
            margin: 4px 0 0 0;
            color: #495057;
            font-size: 13px;
            font-weight: 600;
        }
        .header p {
            margin: 2px 0 0 0;
            color: #6c757d;
            font-size: 11px;
        }
        .doc-title {
            text-align: center;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 8px;
            margin-bottom: 18px;
            font-weight: bold;
            font-size: 13px;
            color: #0f172a;
            text-transform: uppercase;
        }
        .sei-badge {
            background-color: #e0f2fe;
            border: 1px solid #0284c7;
            color: #0369a1;
            padding: 4px 8px;
            font-weight: bold;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 15px;
        }
        .section {
            margin-bottom: 16px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #003366;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        table.meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.meta-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            font-size: 11px;
            vertical-align: top;
        }
        table.meta-table td.label {
            background-color: #f8fafc;
            font-weight: bold;
            color: #475569;
            width: 28%;
        }
        .justificativa-box {
            background-color: #f8fafc;
            border-left: 3px solid #0284c7;
            padding: 10px;
            font-style: italic;
            color: #334155;
            margin-bottom: 14px;
        }
        .despacho-box {
            background-color: #f0fdf4;
            border-left: 3px solid #16a34a;
            padding: 12px;
            color: #14532d;
            margin-bottom: 14px;
        }
        .footer-seal {
            margin-top: 30px;
            border-top: 1px dashed #94a3b8;
            padding-top: 12px;
            font-size: 10px;
            color: #64748b;
        }
        .qr-placeholder {
            border: 1px solid #cbd5e1;
            padding: 6px;
            font-family: monospace;
            background-color: #f8fafc;
            display: inline-block;
        }
    </style>
</head>
<body>

    <div class="header">
        <h3>Estado do Maranhão</h3>
        <h4>Procuradoria-Geral do Estado do Maranhão</h4>
        <p>Gabinete do Procurador-Geral • Procuradoria-Geral Adjunta para Assuntos Estratégicos (PGAAE)</p>
    </div>

    <div class="doc-title">
        Termo de Deliberação e Repactuação de Metas Setoriais
    </div>

    <div style="text-align: right; margin-bottom: 10px;">
        <span class="sei-badge">
            PROCESSO SEI-MA Nº: {$numeroSei}
        </span>
    </div>

    <div class="section">
        <div class="section-title">1. Identificação Institucional</div>
        <table class="meta-table">
            <tr>
                <td class="label">Unidade / Procuradoria:</td>
                <td><strong>{$r->unidade_sigla}</strong> — {$r->unidade_nome}</td>
            </tr>
            <tr>
                <td class="label">Ciclo de Avaliação:</td>
                <td><strong>{$r->ciclo}</strong> (Plano de Ação 2026–2030)</td>
            </tr>
            <tr>
                <td class="label">Entrega do Plano Setorial:</td>
                <td>{$r->descricao_entrega}</td>
            </tr>
            <tr>
                <td class="label">Tipo de Modificação:</td>
                <td>Ajuste de <strong>{$r->tipo}</strong></td>
            </tr>
            <tr>
                <td class="label">Causa-Raiz / Motivo:</td>
                <td>{$motivoTexto}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">2. Relatório Circunstanciado da Repactuação</div>
        <p style="margin: 0 0 6px 0;"><strong>Ajuste Solicitado:</strong> {$r->descricao_ajuste}</p>
        
        <table class="meta-table">
            <tr>
                <td class="label">Configuração Original:</td>
                <td>
                    Prazo Anterior: <strong style="color: #dc3545;">{$dataAnteriorFmt}</strong><br>
                    Meta Original: {$r->meta_anterior}
                </td>
            </tr>
            <tr>
                <td class="label">Nova Configuração Proposta:</td>
                <td>
                    Novo Prazo: <strong style="color: #198754;">{$dataNovaFmt}</strong><br>
                    Nova Meta Repactuada: {$r->meta_nova}
                </td>
            </tr>
        </table>

        <div style="font-weight: bold; margin-bottom: 4px; color: #475569;">Justificativa Apresentada pelo Gestor da Unidade:</div>
        <div class="justificativa-box">
            "{$r->justificativa}"
        </div>
        <p style="font-size: 11px; color: #64748b; margin-top: -6px;">
            Submetido por: <strong>{$r->solicitante_nome}</strong> em {$dataAberturaFmt}.
        </p>
    </div>

    <div class="section">
        <div class="section-title">3. Parecer e Deliberação da Alta Gestão</div>
        
        <div style="margin-bottom: 8px;">
            Situação da Deliberação: 
            <span style="font-weight: bold; color: {$statusColor}; font-size: 12px; border: 1px solid {$statusColor}; padding: 2px 6px;">
                {$statusLabel}
            </span>
        </div>

        <div class="despacho-box">
            <strong>Despacho do Gabinete / PGAAE:</strong><br>
            {$r->parecer_analise}
            <div style="margin-top: 8px; font-size: 11px; color: #166534;">
                Deliberado por: <strong>{$r->analisador_nome}</strong> em {$dataHomologacaoFmt}.
            </div>
        </div>
    </div>

    <div class="footer-seal">
        <table style="width: 100%;">
            <tr>
                <td style="width: 75%; vertical-align: top;">
                    <strong>AUTENTICIDADE DOCUMENTAL SEI-MA / PGE-MA:</strong><br>
                    Documento emitido eletronicamente pelo Sistema de Gestão Estratégica e Projetos da PGE-MA.<br>
                    Instrução de Tramitação: <em>Anexar este Termo aos autos do processo eletrônico no SEI-MA para fins de prestação de contas, governança interna e auditoria TCE/PGE.</em>
                </td>
                <td style="width: 25%; text-align: right; vertical-align: top;">
                    <div class="qr-placeholder">
                        CÓDIGO DE CONTROLE<br>
                        <strong>{$hashValidacao}</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
HTML;

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'portrait');

        return [
            'pdf' => $pdf->output(),
            'filename' => "Termo_Repactuacao_SEI_{$numeroSei}_{$r->ciclo}.pdf",
            'numero_sei' => $numeroSei
        ];
    }
}
