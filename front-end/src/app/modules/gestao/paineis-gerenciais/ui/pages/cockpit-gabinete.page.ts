import { ChangeDetectionStrategy, Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { BreadcrumbComponent } from 'src/app/v2/components/breadcrumb/breadcrumb.component';
import { NavigateService } from 'src/app/services/navigate.service';
import {
  CockpitConfig,
  CockpitGabineteResponse,
  CockpitUnidadeDetalhe,
  CockpitWidgetItem,
  PainelApiClient,
} from '../../infra/painel-api.client';
import { CockpitConfigModalComponent } from '../components/cockpit-config-modal.component';
import { CockpitSetorModalComponent } from '../components/cockpit-setor-modal.component';

@Component({
  selector: 'cockpit-gabinete-page',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    CommonModule,
    FormsModule,
    BreadcrumbComponent,
    CockpitConfigModalComponent,
    CockpitSetorModalComponent,
  ],
  templateUrl: './cockpit-gabinete.page.html',
  styleUrls: ['./cockpit-gabinete.page.scss'],
})
export class CockpitGabinetePage implements OnInit {
  private readonly api = inject(PainelApiClient);
  private readonly go = inject(NavigateService);

  readonly carregando = signal(true);
  readonly erro = signal<string | null>(null);
  readonly dados = signal<CockpitGabineteResponse | null>(null);

  // Filtros de consulta ao servidor
  readonly filtroStatusPlanos = signal<string>('');
  readonly filtroDataInicio = signal<string>('');
  readonly filtroDataFim = signal<string>('');

  // Filtros locais
  readonly filtroTexto = signal('');
  readonly filtroMacroArea = signal<string>('TODAS');
  readonly filtroFarol = signal<'TODOS' | 'VERDE' | 'AMARELO' | 'VERMELHO'>('TODOS');
  readonly abaEstrategica = signal<'eixos' | 'pilares'>('eixos');

  // Modais
  readonly setorSelecionado = signal<CockpitUnidadeDetalhe | null>(null);
  readonly configuradorAberto = signal(false);

  readonly secoesVisiveis = computed<CockpitWidgetItem[]>(() => {
    const secoes = this.dados()?.config?.widgets?.secoes ?? [];
    return secoes.filter(s => s.visivel);
  });

  readonly kpisVisiveis = computed<CockpitWidgetItem[]>(() => {
    const kpis = this.dados()?.config?.widgets?.kpis ?? [];
    return kpis.filter(k => k.visivel);
  });

  readonly unidadesFiltradas = computed(() => {
    const list = this.dados()?.unidades ?? [];
    const texto = this.filtroTexto().toLowerCase().trim();
    const macro = this.filtroMacroArea();
    const farol = this.filtroFarol();

    return list.filter(u => {
      if (texto) {
        const match =
          u.sigla.toLowerCase().includes(texto) ||
          u.nome.toLowerCase().includes(texto) ||
          (u.gestor && u.gestor.toLowerCase().includes(texto)) ||
          (u.plano_nome && u.plano_nome.toLowerCase().includes(texto));
        if (!match) return false;
      }

      if (macro !== 'TODAS' && u.macro_area !== macro) {
        return false;
      }

      if (farol !== 'TODOS' && u.farol !== farol) {
        return false;
      }

      return true;
    });
  });

  readonly macroAreasDisponiveis = computed(() => {
    return (this.dados()?.macro_areas ?? []).map(m => m.macro_area);
  });

  ngOnInit(): void {
    this.carregarDados(true);
  }

  carregarDados(inicial = false): void {
    this.carregando.set(true);
    this.erro.set(null);

    const status = this.filtroStatusPlanos();
    const dtIni = this.filtroDataInicio();
    const dtFim = this.filtroDataFim();

    this.api
      .getCockpitGabinete({
        status_planos: status || undefined,
        data_inicio: dtIni || undefined,
        data_fim: dtFim || undefined,
      })
      .subscribe({
        next: res => {
          this.dados.set(res);
          if (inicial && res.config?.opcoes) {
            this.abaEstrategica.set(res.config.opcoes.aba_estrategica_padrao);
            this.filtroStatusPlanos.set(res.config.opcoes.status_planos_padrao);
          }
          this.carregando.set(false);
        },
        error: err => {
          this.erro.set(err?.error?.error || 'Não foi possível carregar os dados do Cockpit Executivo.');
          this.carregando.set(false);
        },
      });
  }

  aoSalvarConfiguracao(novaConfig: CockpitConfig): void {
    this.configuradorAberto.set(false);
    this.abaEstrategica.set(novaConfig.opcoes.aba_estrategica_padrao);
    this.filtroStatusPlanos.set(novaConfig.opcoes.status_planos_padrao);
    this.carregarDados(false);
  }

  acessarPlano(unidade: CockpitUnidadeDetalhe): void {
    this.go.navigate({
      route: ['execucao', 'plano-entrega'],
      params: {
        execucao: true,
        filter: {
          unidade_id: unidade.id,
          meus_planos: false,
        },
      },
    });
  }

  selecionarSetor(unidade: CockpitUnidadeDetalhe): void {
    this.setorSelecionado.set(unidade);
  }

  fecharModal(): void {
    this.setorSelecionado.set(null);
  }

  limparFiltrosPeriodo(): void {
    this.filtroDataInicio.set('');
    this.filtroDataFim.set('');
    this.filtroStatusPlanos.set(this.dados()?.config?.opcoes?.status_planos_padrao || 'VALIDOS');
    this.carregarDados(false);
  }

  imprimir(): void {
    window.print();
  }
}
