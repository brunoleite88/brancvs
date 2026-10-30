import { ChangeDetectionStrategy, Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Observable } from 'rxjs';
import { WebcomponentsAngularModule } from '@govbr-ds/webcomponents-angular';
import { BreadcrumbComponent } from 'src/app/v2/components/breadcrumb/breadcrumb.component';
import { PaginationV2Component } from 'src/app/v2/components/pagination/pagination.component';
import { OcorrenciaApiClient, AgenteOption } from '../infra/ocorrencia-api.client';
import { Ocorrencia, TipoMotivoAfastamento, OcorrenciaProjetoPlano } from '../domain/types';
import { MessageService } from 'src/app/v2/services/message.service';
import { SelectOption, TODOS_SENTINEL } from 'src/app/v2/domain/select-option';
import { SelectTodosSentinelDirective } from 'src/app/v2/domain/select-todos-sentinel.directive';
import { AgentePublicoSearchFn, AgentePublicoSelectComponent } from './components/agente-publico-select.component';
import { Page } from 'src/app/v2/domain/pagination';
import { ProjetoDaoService } from 'src/app/dao/projeto-dao.service';
import { Projeto } from 'src/app/models/projeto.model';

@Component({
  selector: 'app-ocorrencia-v2-list-page',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, ReactiveFormsModule, FormsModule, WebcomponentsAngularModule, BreadcrumbComponent, PaginationV2Component, SelectTodosSentinelDirective, AgentePublicoSelectComponent],
  templateUrl: './list.page.html',
})
export class OcorrenciaV2ListPage implements OnInit {
  private readonly api = inject(OcorrenciaApiClient);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);
  private readonly message = inject(MessageService);
  private readonly projetoDao = inject(ProjetoDaoService);

  readonly abaAtiva = signal<'PROJETOS_PLANOS' | 'PESSOAL'>('PROJETOS_PLANOS');

  // Aba Afastamentos de Pessoal
  readonly ocorrencias = signal<Ocorrencia[]>([]);
  readonly tipos = signal<TipoMotivoAfastamento[]>([]);
  readonly agenteSelecionado = signal<AgenteOption | null>(null);
  readonly carregando = signal(true);
  readonly page = signal(1);
  readonly lastPage = signal(1);
  readonly total = signal(0);

  // Aba Impedimentos de Projetos e Planos
  readonly ocorrenciasProjetosPlanos = signal<OcorrenciaProjetoPlano[]>([]);
  readonly projetos = signal<Projeto[]>([]);
  readonly carregandoProjetos = signal(false);
  readonly modalNovoImpedimentoAberto = signal(false);
  readonly salvandoImpedimento = signal(false);

  readonly filtroTipoProjetoPlano = signal<string>('TODOS');
  readonly filtroStatusProjetoPlano = signal<string>('TODOS');

  readonly formImpedimento = this.fb.group({
    titulo: [''],
    tipo: ['PROJETO'],
    projeto_id: [''],
    motivo: ['Atraso de Marco Crítico'],
    impacto: [''],
    novo_prazo_proposto: [''],
    acao_mitigatoria: [''],
    descricao: [''],
  });

  readonly motivosImpedimento = [
    'Atraso de Marco Crítico',
    'Dependência de Órgão Externo / Terceiros',
    'TI / Infraestrutura Tecnológica',
    'Aguardando Deliberação Superior / Gabinete',
    'Complexidade Jurídica Imprevista',
    'Redirecionamento de Prioridades',
    'Falta de Quórum / Equipe',
    'Outros',
  ];

  tiposOptions(): SelectOption[] {
    const atual = this.filtros.controls.tipo_motivo_afastamento_id.value;
    return [
      { value: TODOS_SENTINEL, label: 'Todos' },
      ...this.tipos().map(t => ({ value: t.id, label: t.nome })),
    ].map(o => ({ ...o, selected: o.value === atual }));
  }

  readonly agentesSearchFn: AgentePublicoSearchFn = (termo, page, size): Observable<Page<AgenteOption>> =>
    this.api.agentes(termo, page, size);

  readonly filtros = this.fb.nonNullable.group({
    usuario_id: [TODOS_SENTINEL],
    tipo_motivo_afastamento_id: [TODOS_SENTINEL],
    data_inicio: [''],
    data_fim: [''],
  });

  ngOnInit(): void {
    this.api.tipos().subscribe(t => this.tipos.set(t));
    this.carregarProjetosSelect();
    this.carregar();
    this.carregarImpedimentos();
  }

  setAba(aba: 'PROJETOS_PLANOS' | 'PESSOAL'): void {
    this.abaAtiva.set(aba);
  }

  carregarProjetosSelect(): void {
    this.projetoDao.query({ where: [] }).asPromise().then(result => {
      this.projetos.set(result || []);
      if (result && result.length > 0) {
        this.formImpedimento.patchValue({ projeto_id: result[0].id });
      }
    }).catch(() => {});
  }

  carregarImpedimentos(): void {
    this.carregandoProjetos.set(true);
    const params: Record<string, any> = {};
    if (this.filtroTipoProjetoPlano() !== 'TODOS') params['tipo'] = this.filtroTipoProjetoPlano();
    if (this.filtroStatusProjetoPlano() !== 'TODOS') params['status'] = this.filtroStatusProjetoPlano();

    this.api.listarProjetosPlanos(params).subscribe({
      next: (res: any) => {
        this.ocorrenciasProjetosPlanos.set(res?.data ?? []);
        this.carregandoProjetos.set(false);
      },
      error: () => {
        this.carregandoProjetos.set(false);
      }
    });
  }

  abrirModalNovoImpedimento(): void {
    this.formImpedimento.reset({
      tipo: 'PROJETO',
      projeto_id: this.projetos().length > 0 ? this.projetos()[0].id : '',
      motivo: 'Atraso de Marco Crítico',
      titulo: '',
      impacto: '',
      novo_prazo_proposto: '',
      acao_mitigatoria: '',
      descricao: '',
    });
    this.modalNovoImpedimentoAberto.set(true);
  }

  fecharModalNovoImpedimento(): void {
    this.modalNovoImpedimentoAberto.set(false);
  }

  salvarImpedimento(): void {
    if (!this.formImpedimento.value.titulo) {
      this.message.error('Por favor, informe o título do impedimento/desvio.');
      return;
    }

    this.salvandoImpedimento.set(true);
    this.api.criarProjetoPlano(this.formImpedimento.value).subscribe({
      next: () => {
        this.salvandoImpedimento.set(false);
        this.modalNovoImpedimentoAberto.set(false);
        this.message.success('Ocorrência de projeto/plano registrada com sucesso!');
        this.carregarImpedimentos();
      },
      error: (e) => {
        this.salvandoImpedimento.set(false);
        this.message.error(e?.error?.error || 'Erro ao registrar ocorrência.');
      }
    });
  }

  atualizarStatusImpedimento(id: string, novoStatus: string): void {
    this.api.atualizarStatusProjetoPlano(id, novoStatus).subscribe({
      next: () => {
        this.message.success('Status atualizado com sucesso!');
        this.carregarImpedimentos();
      },
      error: () => {
        this.message.error('Erro ao atualizar status.');
      }
    });
  }

  excluirImpedimento(id: string): void {
    if (confirm('Deseja realmente remover esta ocorrência?')) {
      this.api.excluirProjetoPlano(id).subscribe({
        next: () => {
          this.message.success('Ocorrência removida com sucesso!');
          this.carregarImpedimentos();
        },
        error: () => {
          this.message.error('Erro ao remover ocorrência.');
        }
      });
    }
  }

  carregar(): void {
    this.carregando.set(true);
    const filtros = this.filtros.getRawValue();
    const params: Record<string, any> = {
      page: this.page(),
      size: 15,
    };

    if (filtros.usuario_id && filtros.usuario_id !== TODOS_SENTINEL) params['usuario_id'] = filtros.usuario_id;
    if (filtros.tipo_motivo_afastamento_id && filtros.tipo_motivo_afastamento_id !== TODOS_SENTINEL) params['tipo_motivo_afastamento_id'] = filtros.tipo_motivo_afastamento_id;
    if (filtros.data_inicio) params['data_inicio'] = filtros.data_inicio;
    if (filtros.data_fim) params['data_fim'] = filtros.data_fim;

    this.api.listar(params).subscribe({
      next: (result: any) => {
        this.ocorrencias.set(result?.data ?? []);
        this.page.set(result?.current_page ?? 1);
        this.lastPage.set(result?.last_page ?? 1);
        this.total.set(result?.total ?? 0);
        this.carregando.set(false);
      },
      error: (e) => {
        this.carregando.set(false);
        this.message.error(e.error?.error || 'Erro ao carregar ocorrências.');
      },
    });
  }

  aplicarFiltros(): void {
    this.page.set(1);
    this.carregar();
  }

  selecionarAgente(agente: AgenteOption): void {
    this.agenteSelecionado.set(agente);
    this.filtros.controls.usuario_id.setValue(agente.id);
    this.aplicarFiltros();
  }

  limparFiltros(): void {
    this.filtros.reset();
    this.agenteSelecionado.set(null);
    this.page.set(1);
    this.carregar();
  }

  onPageChange(page: number): void {
    this.page.set(page);
    this.carregar();
  }

  nova(): void {
    this.router.navigate(['/gestao/ocorrencia-v2/nova']);
  }

  readonly modal = signal<{ titulo: string; mensagem: string } | null>(null);
  private pendingExclusao: Ocorrencia | null = null;

  excluir(ocorrencia: Ocorrencia): void {
    this.api.impactoConsolidacoes({
      usuario_id: ocorrencia.usuario_id,
      data_inicio: ocorrencia.data_inicio.substring(0, 10),
      data_fim: ocorrencia.data_fim.substring(0, 10),
      operacao: 'excluir',
      ocorrencia_id: ocorrencia.id,
    }).subscribe({
      next: (impacto) => {
        if (impacto.operacao_bloqueada) {
          this.message.error('Não é possível excluir esta ocorrência pois foi cadastrada há mais de 1 ano.');
          return;
        }

        if (impacto.remove_dispensa) {
          this.pendingExclusao = ocorrencia;
          const mensagem = 'Com a exclusão desta ocorrência, registros de execução e avaliações de um ou mais períodos avaliativos anteriormente dispensados poderão voltar a ser exigidos. <u><strong>O(s) período(s) e o plano de trabalho voltarão aos status anteriores</strong></u>. Essa ação não poderá ser desfeita. Deseja confirmar?';
          this.modal.set({ titulo: 'Confirmar exclusão', mensagem });
          return;
        }

        this.pendingExclusao = ocorrencia;
        this.modal.set({ titulo: 'Confirmar exclusão', mensagem: 'Deseja excluir esta ocorrência?' });
      },
      error: (e) => {
        this.message.error(e.error?.error || 'Erro ao processar solicitação.');
        this.pendingExclusao = ocorrencia;
        this.modal.set({ titulo: 'Confirmar exclusão', mensagem: 'Deseja excluir esta ocorrência?' });
      },
    });
  }

  confirmarModal(): void {
    this.modal.set(null);
    if (this.pendingExclusao) {
      this.executarExclusao(this.pendingExclusao);
      this.pendingExclusao = null;
    }
  }

  fecharModal(): void {
    this.modal.set(null);
    this.pendingExclusao = null;
  }

  private executarExclusao(ocorrencia: Ocorrencia): void {
    this.api.excluir(ocorrencia.id, ocorrencia.usuario_id).subscribe({
      next: () => this.carregar(),
      error: (e) => this.message.error(e.error?.error || 'Erro ao excluir ocorrência.'),
    });
  }

  formatarData(data: string | undefined): string {
    if (!data) return '-';
    return new Date(data.replace(' ', 'T')).toLocaleDateString('pt-BR');
  }

  podeExcluir(oc: Ocorrencia): boolean {
    if (!oc.created_at) return true;
    const UM_ANO_MS = 365 * 24 * 60 * 60 * 1000;
    const diffMs = Date.now() - new Date(oc.created_at.replace(' ', 'T')).getTime();
    return diffMs < UM_ANO_MS;
  }
}
