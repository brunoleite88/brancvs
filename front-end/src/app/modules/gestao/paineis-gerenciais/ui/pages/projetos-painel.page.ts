import { ChangeDetectionStrategy, Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { BreadcrumbComponent } from 'src/app/v2/components/breadcrumb/breadcrumb.component';
import { PainelApiClient, ProjetoResumoItem, ProjetosPainelResponse } from '../../infra/painel-api.client';
import { ORIGEM_DADOS } from '../../infra/painel.constants';

@Component({
  selector: 'projetos-painel-page',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, FormsModule, BreadcrumbComponent],
  templateUrl: './projetos-painel.page.html',
  styleUrls: ['./projetos-painel.page.scss'],
})
export class ProjetosPainelPage implements OnInit {
  private readonly api = inject(PainelApiClient);
  private readonly router = inject(Router);

  readonly origemDados = ORIGEM_DADOS;

  readonly carregando = signal(true);
  readonly dados = signal<ProjetosPainelResponse | null>(null);

  readonly filtroTexto = signal('');
  readonly filtroStatus = signal<'TODOS' | 'EXECUCAO' | 'IMPLANTACAO' | 'ELABORACAO'>('TODOS');

  readonly projetosFiltrados = computed(() => {
    const list = this.dados()?.projetos ?? [];
    const texto = this.filtroTexto().toLowerCase().trim();
    const stFiltro = this.filtroStatus();

    return list.filter(p => {
      // Filtro texto
      if (texto) {
        const match =
          p.codigo.toLowerCase().includes(texto) ||
          p.titulo.toLowerCase().includes(texto) ||
          p.lider.toLowerCase().includes(texto) ||
          p.unidades_participantes.toLowerCase().includes(texto) ||
          p.krs_vinculados.toLowerCase().includes(texto);
        if (!match) return false;
      }

      // Filtro status
      if (stFiltro === 'EXECUCAO') {
        return p.status.toLowerCase().includes('execu');
      }
      if (stFiltro === 'IMPLANTACAO') {
        return (
          p.status.toLowerCase().includes('implanta') ||
          p.status.toLowerCase().includes('valida') ||
          p.status.toLowerCase().includes('instala')
        );
      }
      if (stFiltro === 'ELABORACAO') {
        return (
          !p.status.toLowerCase().includes('execu') &&
          !p.status.toLowerCase().includes('implanta') &&
          !p.status.toLowerCase().includes('valida') &&
          !p.status.toLowerCase().includes('instala')
        );
      }

      return true;
    });
  });

  ngOnInit(): void {
    this.carregarDados();
  }

  carregarDados(): void {
    this.carregando.set(true);
    this.api.getProjetosPainel().subscribe({
      next: res => {
        this.dados.set(res);
        this.carregando.set(false);
      },
      error: () => {
        this.carregando.set(false);
      },
    });
  }

  getStatusClass(status: string): string {
    const s = status.toLowerCase();
    if (s.includes('execu')) return 'badge-execucao';
    if (s.includes('implanta') || s.includes('valida') || s.includes('instala')) return 'badge-implantacao';
    return 'badge-elaboracao';
  }

  acessarProjeto(projeto: ProjetoResumoItem): void {
    // Redireciona para o módulo de projetos
    this.router.navigate(['gestao', 'projeto'], {
      queryParams: { search: projeto.codigo },
    });
  }
}
