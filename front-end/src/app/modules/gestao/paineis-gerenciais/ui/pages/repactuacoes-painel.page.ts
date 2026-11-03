import { ChangeDetectionStrategy, Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BreadcrumbComponent } from 'src/app/v2/components/breadcrumb/breadcrumb.component';
import {
  PainelApiClient,
  RepactuacoesResponse,
  RepactuacaoItem,
} from '../../infra/painel-api.client';

@Component({
  selector: 'repactuacoes-painel-page',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, FormsModule, BreadcrumbComponent],
  templateUrl: './repactuacoes-painel.page.html',
  styleUrls: ['./repactuacoes-painel.page.scss'],
})
export class RepactuacoesPainelPage implements OnInit {
  private readonly api = inject(PainelApiClient);

  readonly carregando = signal(true);
  readonly salvando = signal(false);
  readonly dados = signal<RepactuacoesResponse | null>(null);

  readonly cicloSelecionado = signal<string>('TODOS');
  readonly statusSelecionado = signal<string>('TODOS');
  readonly unidadeSelecionada = signal<string>('TODAS');
  readonly filtroTexto = signal('');

  readonly abaAtiva = signal<'FILA_GABINETE' | 'QUADRO_GERAL' | 'MOTIVOS'>('FILA_GABINETE');

  // Modais de Auditoria e Ação
  readonly repactuacaoSelecionada = signal<RepactuacaoItem | null>(null);
  readonly modalNovaAberta = signal(false);
  readonly modalParecerAberta = signal(false);
  readonly modalTrilhaAberta = signal(false);

  readonly itemParaDeliberacao = signal<RepactuacaoItem | null>(null);
  readonly parecerAcao = signal<'HOMOLOGAR' | 'REJEITAR'>('HOMOLOGAR');
  parecerTexto = '';

  // Form de Nova Repactuação
  novaUnidadeId = '';
  novaEntregaId = '';
  novoCiclo = '2026-T1';
  novoTipo: 'PRAZO' | 'META' | 'ESCOPO' | 'OUTRO' = 'PRAZO';
  novoMotivo = 'IMPEDIMENTO_ORCAMENTARIO';
  novaDescricaoAjuste = '';
  novaJustificativa = '';
  novaDataFim = '';
  novaMeta = '';

  readonly entregasUnidade = signal<any[]>([]);
  readonly carregandoEntregas = signal(false);

  readonly pendentesGabinete = computed(() => {
    return (this.dados()?.repactuacoes ?? []).filter(r => r.status === 'PENDENTE');
  });

  readonly repactuacoesFiltradas = computed(() => {
    const list = this.dados()?.repactuacoes ?? [];
    const texto = this.filtroTexto().toLowerCase().trim();

    if (!texto) {
      return list;
    }

    return list.filter(r =>
      r.unidade_sigla.toLowerCase().includes(texto) ||
      r.unidade_nome.toLowerCase().includes(texto) ||
      (r.descricao_entrega && r.descricao_entrega.toLowerCase().includes(texto)) ||
      r.descricao_ajuste.toLowerCase().includes(texto) ||
      r.justificativa.toLowerCase().includes(texto) ||
      (r.parecer_analise && r.parecer_analise.toLowerCase().includes(texto))
    );
  });

  ngOnInit(): void {
    this.carregarDados();
  }

  carregarDados(): void {
    this.carregando.set(true);
    const c = this.cicloSelecionado();
    const s = this.statusSelecionado();
    const u = this.unidadeSelecionada();

    this.api.getRepactuacoes(c, s, u).subscribe({
      next: (resp) => {
        this.dados.set(resp);
        this.carregando.set(false);
      },
      error: (err) => {
        console.error('Erro ao carregar dados de repactuação:', err);
        this.carregando.set(false);
      },
    });
  }

  onFiltroChange(): void {
    this.carregarDados();
  }

  abrirModalParecer(item: RepactuacaoItem, acao: 'HOMOLOGAR' | 'REJEITAR'): void {
    this.itemParaDeliberacao.set(item);
    this.parecerAcao.set(acao);
    this.parecerTexto = acao === 'HOMOLOGAR'
      ? 'Aprovado pelo Gabinete / PGAAE conforme justificativa apresentada pelo gestor da unidade.'
      : 'Devolvido ao setor para complementação de esclarecimentos e adequação de cronograma.';
    this.modalParecerAberta.set(true);
  }

  fecharModalParecer(): void {
    this.modalParecerAberta.set(false);
    this.itemParaDeliberacao.set(null);
  }

  salvarDeliberacao(): void {
    const item = this.itemParaDeliberacao();
    if (!item) return;

    this.salvando.set(true);
    const acao = this.parecerAcao();

    const obs = acao === 'HOMOLOGAR'
      ? this.api.homologarRepactuacao(item.id, this.parecerTexto)
      : this.api.rejeitarRepactuacao(item.id, this.parecerTexto);

    obs.subscribe({
      next: () => {
        this.salvando.set(false);
        this.fecharModalParecer();
        this.carregarDados();
      },
      error: (err) => {
        console.error('Erro ao processar deliberação:', err);
        this.salvando.set(false);
        alert('Erro ao registrar parecer de repactuação.');
      },
    });
  }

  abrirTrilhaAuditoria(item: RepactuacaoItem): void {
    this.repactuacaoSelecionada.set(item);
    this.modalTrilhaAberta.set(true);
  }

  fecharTrilhaAuditoria(): void {
    this.modalTrilhaAberta.set(false);
    this.repactuacaoSelecionada.set(null);
  }

  abrirModalNova(): void {
    this.novaUnidadeId = '';
    this.novaEntregaId = '';
    this.novoCiclo = '2026-T1';
    this.novoTipo = 'PRAZO';
    this.novoMotivo = 'IMPEDIMENTO_ORCAMENTARIO';
    this.novaDescricaoAjuste = '';
    this.novaJustificativa = '';
    this.novaDataFim = '';
    this.novaMeta = '';
    this.entregasUnidade.set([]);
    this.modalNovaAberta.set(true);
  }

  fecharModalNova(): void {
    this.modalNovaAberta.set(false);
  }

  onUnidadeNovaChange(): void {
    if (!this.novaUnidadeId) {
      this.entregasUnidade.set([]);
      return;
    }
    this.carregandoEntregas.set(true);
    this.api.getEntregasPorUnidade(this.novaUnidadeId).subscribe({
      next: (entregas) => {
        this.entregasUnidade.set(entregas);
        this.carregandoEntregas.set(false);
      },
      error: (err) => {
        console.error('Erro ao buscar entregas:', err);
        this.carregandoEntregas.set(false);
      }
    });
  }

  submeterNovaRepactuacao(): void {
    if (!this.novaUnidadeId || !this.novaEntregaId || !this.novaDescricaoAjuste || !this.novaJustificativa) {
      alert('Por favor, preencha todos os campos obrigatórios da solicitação de repactuação.');
      return;
    }

    this.salvando.set(true);
    const payload = {
      unidade_id: this.novaUnidadeId,
      plano_entrega_entrega_id: this.novaEntregaId,
      ciclo: this.novoCiclo,
      tipo: this.novoTipo,
      motivo: this.novoMotivo,
      descricao_ajuste: this.novaDescricaoAjuste,
      justificativa: this.novaJustificativa,
      data_nova: this.novaDataFim ? `${this.novaDataFim} 23:59:59` : null,
      meta_nova: this.novaMeta || null,
    };

    this.api.criarRepactuacao(payload).subscribe({
      next: () => {
        this.salvando.set(false);
        this.fecharModalNova();
        this.carregarDados();
      },
      error: (err) => {
        console.error('Erro ao submeter repactuação:', err);
        this.salvando.set(false);
        alert('Erro ao enviar solicitação de repactuação.');
      }
    });
  }

  imprimir(): void {
    window.print();
  }

  getMotivoLabel(motivo: string): string {
    const map: Record<string, string> = {
      IMPEDIMENTO_ORCAMENTARIO: 'Impedimento Orçamentário / BID',
      ATRASO_EXTERNO: 'Atraso Externo / Tribunais / Órgãos',
      REORGANIZACAO_INTERNA: 'Reorganização Interna de Equipe',
      EXPANSAO_DEMANDA: 'Expansão de Demanda Institucional',
      ADEQUACAO_TECNICA: 'Adequação Técnica / Ferramental',
      OUTRO: 'Outros Motivos Justificados',
    };
    return map[motivo] ?? motivo;
  }
}
