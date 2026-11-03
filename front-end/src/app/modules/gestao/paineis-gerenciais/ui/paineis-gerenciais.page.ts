import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { BreadcrumbComponent } from 'src/app/v2/components/breadcrumb/breadcrumb.component';

interface PainelItem {
  titulo: string;
  rota: string;
  icon: string;
}

@Component({
  selector: 'paineis-gerenciais-page',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, BreadcrumbComponent],
  templateUrl: './paineis-gerenciais.page.html',
  styleUrls: ['./paineis-gerenciais.page.scss'],
})
export class PaineisGerenciaisPage {
  private readonly router = inject(Router);

  readonly paineis: PainelItem[] = [
    { titulo: 'Cockpit do Gabinete', rota: 'cockpit-gabinete', icon: 'bi bi-speedometer2' },
    { titulo: 'Metas e Entregas', rota: 'metas-entregas', icon: 'bi bi-graph-up-arrow' },
    { titulo: 'Conformidade', rota: 'conformidade', icon: 'bi bi-clipboard2-check' },
    { titulo: 'Projetos', rota: 'projetos', icon: 'bi bi-kanban' },
    { titulo: 'Monitoramento & Repactuação', rota: 'repactuacoes', icon: 'bi bi-clock-history' },
  ];

  navegarPara(rota: string): void {
    this.router.navigate(['gestao', 'paineis-gerenciais', rota]);
  }
}
