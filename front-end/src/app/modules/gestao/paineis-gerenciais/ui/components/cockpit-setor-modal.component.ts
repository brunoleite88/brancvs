import { ChangeDetectionStrategy, Component, EventEmitter, Input, OnInit, Output, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  CockpitUnidadeDetalhe,
  CockpitUnidadeDrilldown,
  PainelApiClient,
} from '../../infra/painel-api.client';

@Component({
  selector: 'cockpit-setor-modal',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, FormsModule],
  templateUrl: './cockpit-setor-modal.component.html',
})
export class CockpitSetorModalComponent implements OnInit {
  private readonly api = inject(PainelApiClient);

  @Input({ required: true }) unidadeResumo!: CockpitUnidadeDetalhe;
  @Output() fechar = new EventEmitter<void>();
  @Output() abrirPlano = new EventEmitter<CockpitUnidadeDetalhe>();

  readonly carregando = signal(true);
  readonly detalhe = signal<CockpitUnidadeDrilldown | null>(null);
  readonly aba = signal<'entregas' | 'planos_ind' | 'repactuacoes' | 'corregedoria'>('entregas');
  readonly filtroEntrega = signal('');

  readonly entregasFiltradas = computed(() => {
    const lista = this.detalhe()?.entregas ?? [];
    const q = this.filtroEntrega().toLowerCase().trim();
    if (!q) return lista;
    return lista.filter(
      e =>
        e.titulo.toLowerCase().includes(q) ||
        e.detalhamento.toLowerCase().includes(q) ||
        (e.pilar && e.pilar.toLowerCase().includes(q)) ||
        e.objetivos.some(o => o.objetivo.toLowerCase().includes(q))
    );
  });

  ngOnInit(): void {
    this.api.getCockpitUnidadeDetalhe(this.unidadeResumo.id).subscribe({
      next: res => {
        this.detalhe.set(res);
        this.carregando.set(false);
      },
      error: () => {
        this.carregando.set(false);
      },
    });
  }
}
