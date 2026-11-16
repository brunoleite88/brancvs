import { ChangeDetectionStrategy, Component, EventEmitter, Input, OnInit, Output, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  CockpitConfig,
  CockpitMacroAreaConfig,
  CockpitUnidadeDetalhe,
  CockpitWidgetItem,
  PainelApiClient,
} from '../../infra/painel-api.client';

@Component({
  selector: 'cockpit-config-modal',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, FormsModule],
  templateUrl: './cockpit-config-modal.component.html',
})
export class CockpitConfigModalComponent implements OnInit {
  private readonly api = inject(PainelApiClient);

  @Input({ required: true }) configInicial!: CockpitConfig;
  @Input({ required: true }) unidadesDisponiveis: CockpitUnidadeDetalhe[] = [];

  @Output() fechar = new EventEmitter<void>();
  @Output() salvo = new EventEmitter<CockpitConfig>();

  readonly abaAtiva = signal<'painel' | 'macros' | 'regras'>('painel');
  readonly draft = signal<CockpitConfig | null>(null);
  readonly salvando = signal(false);
  readonly erro = signal<string | null>(null);

  readonly novaMacroNome = signal('');
  readonly macroSelecionadaIdx = signal(0);
  readonly buscaUnidadeMacro = signal('');

  readonly unidadesFiltradasMacro = computed(() => {
    const q = this.buscaUnidadeMacro().toLowerCase().trim();
    if (!q) return this.unidadesDisponiveis;
    return this.unidadesDisponiveis.filter(
      u => u.sigla.toLowerCase().includes(q) || u.nome.toLowerCase().includes(q)
    );
  });

  ngOnInit(): void {
    this.draft.set(structuredClone(this.configInicial));
  }

  atualizarCampoRaiz(campo: 'titulo' | 'subtitulo', valor: string): void {
    this.draft.update(cfg => (cfg ? { ...cfg, [campo]: valor } : cfg));
  }

  atualizarOpcao<K extends keyof CockpitConfig['opcoes']>(campo: K, valor: CockpitConfig['opcoes'][K]): void {
    this.draft.update(cfg => (cfg ? { ...cfg, opcoes: { ...cfg.opcoes, [campo]: valor } } : cfg));
  }

  atualizarColunaTabela(campo: keyof CockpitConfig['widgets']['colunas_tabela'], valor: boolean): void {
    this.draft.update(cfg =>
      cfg
        ? {
            ...cfg,
            widgets: {
              ...cfg.widgets,
              colunas_tabela: { ...cfg.widgets.colunas_tabela, [campo]: valor },
            },
          }
        : cfg
    );
  }

  toggleWidgetVisivel(grupo: 'kpis' | 'secoes', index: number, visivel: boolean): void {
    this.draft.update(cfg => {
      if (!cfg) return cfg;
      const lista = [...cfg.widgets[grupo]];
      lista[index] = { ...lista[index], visivel };
      return { ...cfg, widgets: { ...cfg.widgets, [grupo]: lista } };
    });
  }

  atualizarWidgetLabel(grupo: 'kpis' | 'secoes', index: number, label: string): void {
    this.draft.update(cfg => {
      if (!cfg) return cfg;
      const lista = [...cfg.widgets[grupo]];
      lista[index] = { ...lista[index], label };
      return { ...cfg, widgets: { ...cfg.widgets, [grupo]: lista } };
    });
  }

  moverWidget(grupo: 'kpis' | 'secoes', index: number, delta: -1 | 1): void {
    this.draft.update(cfg => {
      if (!cfg) return cfg;
      const lista: CockpitWidgetItem[] = [...cfg.widgets[grupo]];
      const alvo = index + delta;
      if (alvo < 0 || alvo >= lista.length) return cfg;
      [lista[index], lista[alvo]] = [lista[alvo], lista[index]];
      return { ...cfg, widgets: { ...cfg.widgets, [grupo]: lista } };
    });
  }

  adicionarMacroArea(): void {
    const nome = this.novaMacroNome().trim();
    if (!nome) return;
    this.draft.update(cfg => {
      if (!cfg) return cfg;
      const id = nome.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-' + Date.now();
      const novas: CockpitMacroAreaConfig[] = [...cfg.macro_areas, { id, nome, siglas: [] }];
      this.macroSelecionadaIdx.set(novas.length - 1);
      return { ...cfg, macro_areas: novas };
    });
    this.novaMacroNome.set('');
  }

  renomearMacroArea(idx: number, nome: string): void {
    this.draft.update(cfg => {
      if (!cfg) return cfg;
      const lista = [...cfg.macro_areas];
      lista[idx] = { ...lista[idx], nome };
      return { ...cfg, macro_areas: lista };
    });
  }

  removerMacroArea(idx: number): void {
    this.draft.update(cfg => {
      if (!cfg || cfg.macro_areas.length <= 1) return cfg;
      const lista = cfg.macro_areas.filter((_, i) => i !== idx);
      if (this.macroSelecionadaIdx() >= lista.length) {
        this.macroSelecionadaIdx.set(lista.length - 1);
      }
      return { ...cfg, macro_areas: lista };
    });
  }

  siglaPertenceAMacro(sigla: string, idx: number): boolean {
    const area = this.draft()?.macro_areas[idx];
    if (!area) return false;
    return area.siglas.includes(sigla.toUpperCase());
  }

  macroDaSigla(sigla: string): string {
    const upper = sigla.toUpperCase();
    const encontrada = this.draft()?.macro_areas.find(a => a.siglas.includes(upper));
    return encontrada ? encontrada.nome : 'Outros Setores';
  }

  toggleSiglaNaMacro(sigla: string, idx: number, incluir: boolean): void {
    const upper = sigla.toUpperCase();
    this.draft.update(cfg => {
      if (!cfg) return cfg;
      const lista = cfg.macro_areas.map((area, i) => {
        const semSigla = area.siglas.filter(s => s !== upper);
        if (i === idx && incluir) {
          return { ...area, siglas: [...semSigla, upper] };
        }
        return { ...area, siglas: semSigla };
      });
      return { ...cfg, macro_areas: lista };
    });
  }

  ehConsolidadora(sigla: string): boolean {
    const lista = this.draft()?.regras_farol.unidades_consolidadoras ?? [];
    return lista.includes(sigla.toUpperCase());
  }

  toggleConsolidadora(sigla: string, marcada: boolean): void {
    const upper = sigla.toUpperCase();
    this.draft.update(cfg => {
      if (!cfg) return cfg;
      const atual = cfg.regras_farol.unidades_consolidadoras.filter(s => s !== upper);
      const nova = marcada ? [...atual, upper] : atual;
      return { ...cfg, regras_farol: { ...cfg.regras_farol, unidades_consolidadoras: nova } };
    });
  }

  atualizarRegra<K extends keyof CockpitConfig['regras_farol']>(campo: K, valor: CockpitConfig['regras_farol'][K]): void {
    this.draft.update(cfg =>
      cfg ? { ...cfg, regras_farol: { ...cfg.regras_farol, [campo]: valor } } : cfg
    );
  }

  salvar(): void {
    const cfg = this.draft();
    if (!cfg || this.salvando()) return;
    this.salvando.set(true);
    this.erro.set(null);

    this.api.saveCockpitConfig(cfg).subscribe({
      next: salva => {
        this.salvando.set(false);
        this.salvo.emit(salva);
      },
      error: err => {
        this.salvando.set(false);
        this.erro.set(err?.error?.error || 'Não foi possível salvar a configuração.');
      },
    });
  }

  restaurarPadrao(): void {
    if (this.salvando() || !confirm('Restaurar o layout, macro-áreas e regras do Cockpit para o padrão institucional?')) {
      return;
    }
    this.salvando.set(true);
    this.erro.set(null);

    this.api.resetCockpitConfig().subscribe({
      next: restaurada => {
        this.salvando.set(false);
        this.draft.set(structuredClone(restaurada));
        this.salvo.emit(restaurada);
      },
      error: err => {
        this.salvando.set(false);
        this.erro.set(err?.error?.error || 'Erro ao restaurar padrão.');
      },
    });
  }
}
