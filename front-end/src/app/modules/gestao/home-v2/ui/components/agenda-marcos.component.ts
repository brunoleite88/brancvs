import { ChangeDetectionStrategy, Component, computed, effect, inject, input, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { AgendaItem, AgendaMarcosResponse, HomeApiClient } from '../../infra/home-api.client';
import { HOME_ERRO_RECUPERAR_DADOS } from '../../home.constants';

@Component({
  selector: 'home-agenda-marcos',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule],
  styleUrls: ['../home.styles.scss'],
  templateUrl: './agenda-marcos.component.html',
})
export class AgendaMarcosComponent {
  private readonly router = inject(Router);
  private readonly homeApi = inject(HomeApiClient);

  readonly unidadeId = input.required<string>();
  readonly subordinadas = input.required<boolean>();

  readonly data = signal<AgendaMarcosResponse | null>(null);
  readonly loading = signal(false);
  readonly erro = signal<string | null>(null);
  readonly filtroSelecionado = signal<'TODOS' | 'ATENCAO' | 'PROXIMOS'>('TODOS');

  readonly itensExibidos = computed(() => {
    const d = this.data();
    if (!d) return [];
    const filtro = this.filtroSelecionado();
    if (filtro === 'ATENCAO') {
      return d.itens.filter(i => i.categoria === 'VENCIDO' || i.categoria === 'VENCENDO');
    }
    if (filtro === 'PROXIMOS') {
      return d.itens.filter(i => i.categoria === 'PROXIMO');
    }
    return d.itens;
  });

  constructor() {
    effect(() => {
      const unidadeId = this.unidadeId();
      const subordinadas = this.subordinadas();
      if (unidadeId) this.fetch(unidadeId, subordinadas);
    });
  }

  setFiltro(filtro: 'TODOS' | 'ATENCAO' | 'PROXIMOS'): void {
    this.filtroSelecionado.set(filtro);
  }

  navegar(item: AgendaItem): void {
    if (item.rota && item.rota.length) {
      this.router.navigate(item.rota);
    }
  }

  private fetch(unidadeId: string, subordinadas: boolean): void {
    this.loading.set(true);
    this.erro.set(null);
    this.homeApi.getAgendaMarcos(unidadeId, subordinadas).subscribe({
      next: (res) => {
        this.data.set(res);
        this.loading.set(false);
      },
      error: () => {
        this.data.set(null);
        this.erro.set(HOME_ERRO_RECUPERAR_DADOS);
        this.loading.set(false);
      },
    });
  }
}
