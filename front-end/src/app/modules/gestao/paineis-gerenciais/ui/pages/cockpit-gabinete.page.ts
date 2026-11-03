import { ChangeDetectionStrategy, Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { BreadcrumbComponent } from 'src/app/v2/components/breadcrumb/breadcrumb.component';
import {
  CockpitGabineteResponse,
  CockpitUnidadeDetalhe,
  PainelApiClient,
} from '../../infra/painel-api.client';

@Component({
  selector: 'cockpit-gabinete-page',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, FormsModule, BreadcrumbComponent],
  templateUrl: './cockpit-gabinete.page.html',
  styleUrls: ['./cockpit-gabinete.page.scss'],
})
export class CockpitGabinetePage implements OnInit {
  private readonly api = inject(PainelApiClient);
  private readonly router = inject(Router);

  readonly carregando = signal(true);
  readonly dados = signal<CockpitGabineteResponse | null>(null);

  readonly filtroTexto = signal('');
  readonly filtroMacroArea = signal<string>('TODAS');
  readonly filtroFarol = signal<'TODOS' | 'VERDE' | 'AMARELO' | 'VERMELHO'>('TODOS');
  readonly setorSelecionado = signal<CockpitUnidadeDetalhe | null>(null);

  readonly unidadesFiltradas = computed(() => {
    const list = this.dados()?.unidades ?? [];
    const texto = this.filtroTexto().toLowerCase().trim();
    const macro = this.filtroMacroArea();
    const farol = this.filtroFarol();

    return list.filter(u => {
      // Filtro de texto por sigla ou nome
      if (texto) {
        const match =
          u.sigla.toLowerCase().includes(texto) ||
          u.nome.toLowerCase().includes(texto) ||
          (u.plano_nome && u.plano_nome.toLowerCase().includes(texto));
        if (!match) return false;
      }

      // Filtro de macro área
      if (macro !== 'TODAS' && u.macro_area !== macro) {
        return false;
      }

      // Filtro de farol
      if (farol !== 'TODOS' && u.farol !== farol) {
        return false;
      }

      return true;
    });
  });

  readonly macroAreasDisponiveis = computed(() => {
    const macros = this.dados()?.macro_areas ?? [];
    return macros.map(m => m.macro_area);
  });

  ngOnInit(): void {
    this.carregarDados();
  }

  carregarDados(): void {
    this.carregando.set(true);
    this.api.getCockpitGabinete().subscribe({
      next: res => {
        this.dados.set(res);
        this.carregando.set(false);
      },
      error: () => {
        this.carregando.set(false);
      },
    });
  }

  acessarPlano(unidade: CockpitUnidadeDetalhe): void {
    if (unidade.plano_id) {
      this.router.navigate(['execucao', 'plano-entrega'], {
        queryParams: { id: unidade.plano_id },
      });
    } else {
      this.router.navigate(['execucao', 'plano-entrega']);
    }
  }

  selecionarSetor(unidade: CockpitUnidadeDetalhe): void {
    this.setorSelecionado.set(unidade);
  }

  fecharModal(): void {
    this.setorSelecionado.set(null);
  }

  imprimir(): void {
    window.print();
  }
}
