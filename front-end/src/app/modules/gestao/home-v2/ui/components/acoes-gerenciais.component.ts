import { ChangeDetectionStrategy, Component, effect, inject, input, signal } from '@angular/core';
import { Router } from '@angular/router';
import { AtalhoCardComponent } from './atalho-card.component';
import { HomeApiClient, MeusPlanosVigentesResponse } from '../../infra/home-api.client';
import { MessageService } from 'src/app/v2/services/message.service';

@Component({
  selector: 'home-acoes-gerenciais',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [AtalhoCardComponent],
  styleUrls: ['../home.styles.scss'],
  templateUrl: './acoes-gerenciais.component.html',
})
export class AcoesGerenciaisComponent {
  private readonly router = inject(Router);
  private readonly homeApi = inject(HomeApiClient);
  private readonly message = inject(MessageService);

  readonly unidadeId = input.required<string>();

  readonly data = signal<MeusPlanosVigentesResponse | null>(null);
  readonly loading = signal(false);

  constructor() {
    effect(() => {
      const unidadeId = this.unidadeId();
      if (unidadeId) this.fetch(unidadeId);
    });
  }

  irParaMeuRelatorio(): void {
    this.router.navigate(['gestao', 'corregedoria', 'meu-relatorio']);
  }

  irParaPainelCorregedoria(): void {
    this.router.navigate(['gestao', 'corregedoria', 'painel']);
  }

  irParaPainelGerencial(): void {
    this.router.navigate(['gestao', 'paineis-gerenciais']);
  }

  irParaPlanoEntregas(): void {
    this.router.navigate(['gestao', 'planejamento']);
  }

  irParaPlanoTrabalho(): void {
    this.router.navigate(['gestao', 'corregedoria', 'meu-relatorio']);
  }

  private fetch(unidadeId: string): void {
    this.loading.set(true);
    this.homeApi.getMeusPlanosVigentes(unidadeId).subscribe({
      next: (response) => { this.data.set(response); this.loading.set(false); },
      error: () => { this.data.set(null); this.loading.set(false); },
    });
  }
}
