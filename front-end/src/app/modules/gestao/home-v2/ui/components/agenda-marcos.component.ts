import { ChangeDetectionStrategy, Component, computed, effect, inject, input, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { AgendaItem, AgendaMarcosResponse, HomeApiClient } from '../../infra/home-api.client';
import { HOME_ERRO_RECUPERAR_DADOS } from '../../home.constants';

export interface CalendarioDia {
  dataIso: string | null;
  numero: number | null;
  isHoje: boolean;
  isSelecionado: boolean;
  eventos: AgendaItem[];
  temAtencao: boolean;
  tooltip: string;
}

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

  private readonly hoje = new Date();
  readonly mesCalendario = signal<number>(this.hoje.getMonth());
  readonly anoCalendario = signal<number>(this.hoje.getFullYear());
  readonly dataSelecionada = signal<string | null>(null);

  readonly diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
  private readonly nomesMeses = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
  ];

  readonly tituloMesAno = computed(() => {
    return `${this.nomesMeses[this.mesCalendario()]} ${this.anoCalendario()}`;
  });

  readonly diasDoMes = computed<CalendarioDia[]>(() => {
    const ano = this.anoCalendario();
    const mes = this.mesCalendario();
    const sel = this.dataSelecionada();
    const itens = this.data()?.itens ?? [];

    const primeiroDiaSemana = new Date(ano, mes, 1).getDay();
    const totalDiasMes = new Date(ano, mes + 1, 0).getDate();

    const hojeIso = this.formatarIso(
      this.hoje.getFullYear(),
      this.hoje.getMonth() + 1,
      this.hoje.getDate()
    );

    const grade: CalendarioDia[] = [];

    for (let i = 0; i < primeiroDiaSemana; i++) {
      grade.push({
        dataIso: null,
        numero: null,
        isHoje: false,
        isSelecionado: false,
        eventos: [],
        temAtencao: false,
        tooltip: '',
      });
    }

    for (let dia = 1; dia <= totalDiasMes; dia++) {
      const dataIso = this.formatarIso(ano, mes + 1, dia);
      const eventosDia = itens.filter(it => it.data_evento === dataIso);
      const temAtencao = eventosDia.some(
        it => it.categoria === 'VENCIDO' || it.categoria === 'VENCENDO'
      );
      const tooltip = eventosDia.length
        ? eventosDia.map(e => `• ${e.titulo} (${e.unidade})`).join('\n')
        : '';

      grade.push({
        dataIso,
        numero: dia,
        isHoje: dataIso === hojeIso,
        isSelecionado: sel === dataIso,
        eventos: eventosDia,
        temAtencao,
        tooltip,
      });
    }

    return grade;
  });

  readonly totalEventosMes = computed(() => {
    return this.diasDoMes().reduce((acc, d) => acc + d.eventos.length, 0);
  });

  readonly dataSelecionadaFormatada = computed(() => {
    const iso = this.dataSelecionada();
    if (!iso) return null;
    const partes = iso.split('-');
    return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : iso;
  });

  readonly itensExibidos = computed(() => {
    const d = this.data();
    if (!d) return [];
    let lista = d.itens;

    const filtro = this.filtroSelecionado();
    if (filtro === 'ATENCAO') {
      lista = lista.filter(i => i.categoria === 'VENCIDO' || i.categoria === 'VENCENDO');
    } else if (filtro === 'PROXIMOS') {
      lista = lista.filter(i => i.categoria === 'PROXIMO');
    }

    const dataSel = this.dataSelecionada();
    if (dataSel) {
      lista = lista.filter(i => i.data_evento === dataSel);
    }

    return lista;
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

  mesAnterior(): void {
    const m = this.mesCalendario();
    if (m === 0) {
      this.mesCalendario.set(11);
      this.anoCalendario.update(a => a - 1);
    } else {
      this.mesCalendario.set(m - 1);
    }
    this.dataSelecionada.set(null);
  }

  proximoMes(): void {
    const m = this.mesCalendario();
    if (m === 11) {
      this.mesCalendario.set(0);
      this.anoCalendario.update(a => a + 1);
    } else {
      this.mesCalendario.set(m + 1);
    }
    this.dataSelecionada.set(null);
  }

  irParaHoje(): void {
    this.mesCalendario.set(this.hoje.getMonth());
    this.anoCalendario.set(this.hoje.getFullYear());
    this.dataSelecionada.set(null);
  }

  selecionarDia(dia: CalendarioDia): void {
    if (!dia.dataIso || dia.eventos.length === 0) return;
    if (this.dataSelecionada() === dia.dataIso) {
      this.dataSelecionada.set(null);
    } else {
      this.dataSelecionada.set(dia.dataIso);
    }
  }

  limparDiaSelecionado(): void {
    this.dataSelecionada.set(null);
  }

  navegar(item: AgendaItem): void {
    if (item.rota && item.rota.length) {
      this.router.navigate(item.rota);
    }
  }

  private formatarIso(ano: number, mes: number, dia: number): string {
    const mm = String(mes).padStart(2, '0');
    const dd = String(dia).padStart(2, '0');
    return `${ano}-${mm}-${dd}`;
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
