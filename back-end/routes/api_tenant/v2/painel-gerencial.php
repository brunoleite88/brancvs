<?php

use App\V2\PainelGerencial\Adesao\AdesaoController as AdesaoV2;
use App\V2\PainelGerencial\AlinhamentoDesempenho\AlinhamentoDesempenhoController as AlinhamentoDesempenhoV2;
use App\V2\PainelGerencial\Cockpit\CockpitGabineteController as CockpitGabineteV2;
use App\V2\PainelGerencial\Conformidade\ConformidadeController as ConformidadeV2;
use App\V2\PainelGerencial\Modalidades\ModalidadesController as ModalidadesV2;
use App\V2\PainelGerencial\PainelGerencialController as PainelGerencialV2;
use App\V2\PainelGerencial\Projetos\ProjetosController as ProjetosV2;
use App\V2\PainelGerencial\Repactuacao\RepactuacaoController as RepactuacaoV2;
use Illuminate\Support\Facades\Route;

Route::get('painel-gerencial/unidade-inicial', [PainelGerencialV2::class, 'unidadeInicial']);
Route::get('painel-gerencial/cockpit-gabinete', [CockpitGabineteV2::class, 'index']);
Route::get('painel-gerencial/alinhamento-desempenho/alinhamento-institucional', [AlinhamentoDesempenhoV2::class, 'alinhamentoInstitucional']);
Route::get('painel-gerencial/alinhamento-desempenho/avaliacoes-plano-entrega', [AlinhamentoDesempenhoV2::class, 'avaliacoesPlanoEntrega']);
Route::get('painel-gerencial/alinhamento-desempenho/avaliacoes-plano-trabalho', [AlinhamentoDesempenhoV2::class, 'avaliacoesPlanoTrabalho']);

Route::get('painel-gerencial/modalidades/teletrabalho-substituicao', [ModalidadesV2::class, 'teletrabalhoSubstituicao']);
Route::get('painel-gerencial/modalidades/teletrabalho-discricionario', [ModalidadesV2::class, 'teletrabalhoDiscricionario']);
Route::get('painel-gerencial/modalidades/por-unidade', [ModalidadesV2::class, 'modalidadesPorUnidade']);

Route::get('painel-gerencial/projetos', [ProjetosV2::class, 'index']);
Route::get('painel-gerencial/projetos/indicador', [ProjetosV2::class, 'indicadorPorUnidade']);

Route::get('painel-gerencial/repactuacoes', [RepactuacaoV2::class, 'index']);
Route::get('painel-gerencial/repactuacoes/entregas-unidade/{unidadeId}', [RepactuacaoV2::class, 'entregasPorUnidade']);
Route::post('painel-gerencial/repactuacoes', [RepactuacaoV2::class, 'store']);
Route::post('painel-gerencial/repactuacoes/{id}/homologar', [RepactuacaoV2::class, 'homologar']);
Route::post('painel-gerencial/repactuacoes/{id}/rejeitar', [RepactuacaoV2::class, 'rejeitar']);

Route::get('painel-gerencial/conformidade/registro-execucao-pe', [ConformidadeV2::class, 'registroExecucaoPE']);
Route::get('painel-gerencial/conformidade/avaliacao-pe', [ConformidadeV2::class, 'avaliacaoPE']);
Route::get('painel-gerencial/conformidade/registro-execucao-pt', [ConformidadeV2::class, 'registroExecucaoPT']);
Route::get('painel-gerencial/conformidade/avaliacao-pt', [ConformidadeV2::class, 'avaliacaoPT']);
Route::get('painel-gerencial/conformidade/unidades-executoras-pe', [ConformidadeV2::class, 'unidadesExecutorasPE']);

Route::get('painel-gerencial/adesao/unidades-executoras', [AdesaoV2::class, 'unidadesExecutoras']);
Route::get('painel-gerencial/adesao/evolucao-unidades', [AdesaoV2::class, 'evolucaoUnidades']);
Route::get('painel-gerencial/adesao/participantes-pgd', [AdesaoV2::class, 'participantesPGD']);
Route::get('painel-gerencial/adesao/evolucao-participantes', [AdesaoV2::class, 'evolucaoParticipantes']);
Route::get('painel-gerencial/adesao/periodos-disponiveis', [AdesaoV2::class, 'periodosDisponiveis']);
Route::get('painel-gerencial/adesao/periodos-disponiveis-por-unidade', [AdesaoV2::class, 'periodosDisponiveisPorUnidade']);
Route::get('painel-gerencial/adesao/unidades-historicas', [AdesaoV2::class, 'unidadesHistoricas']);
