import { ChangeDetectionStrategy, Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { WebcomponentsAngularModule } from '@govbr-ds/webcomponents-angular';
import { BreadcrumbComponent } from 'src/app/v2/components/breadcrumb/breadcrumb.component';
import { AuthService } from 'src/app/services/auth.service';
import { UtilService } from 'src/app/services/util.service';
import { UnidadeService } from 'src/app/v2/services/unidade.service';
import { NavigateService } from 'src/app/services/navigate.service';
import { MuralAvisoTenantService } from 'src/app/services/mural-aviso-tenant.service';
import { HomeApiClient } from '../infra/home-api.client';
import { PendenciasUsuarioComponent } from './components/pendencias-usuario.component';
import { PlanosVigentesComponent } from './components/planos-vigentes.component';
import { ResumoEquipeComponent } from './components/resumo-equipe.component';
import { AgendaMarcosComponent } from './components/agenda-marcos.component';
import { AtalhoCardComponent } from './components/atalho-card.component';

export interface SelectOption {
  value: string;
  label: string;
  selected?: boolean;
}

@Component({
  selector: 'home-v2-page',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    CommonModule,
    FormsModule,
    WebcomponentsAngularModule,
    BreadcrumbComponent,
    PendenciasUsuarioComponent,
    PlanosVigentesComponent,
    ResumoEquipeComponent,
    AgendaMarcosComponent,
    AtalhoCardComponent,
  ],
  templateUrl: './home.page.html',
  styleUrls: ['./home.styles.scss'],
})
export class HomeV2Page implements OnInit {
  private readonly auth = inject(AuthService);
  private readonly utils = inject(UtilService);
  private readonly unidadeService = inject(UnidadeService);
  private readonly router = inject(Router);
  private readonly go = inject(NavigateService);
  private readonly muralService = inject(MuralAvisoTenantService);
  private readonly homeApi = inject(HomeApiClient);

  readonly unidadeOptions = signal<SelectOption[]>([]);
  readonly selectedUnidadeId = signal<string>('');
  readonly subordinadas = signal(true);
  readonly exibeToggleSubordinadas = computed(() => {
    return !this.auth.isUsuarioParticipante() && !this.auth.isUsuarioConsulta();
  });

  readonly saudacao = computed(() => {
    const hora = new Date().getHours();
    if (hora < 12) return 'Bom dia';
    if (hora < 18) return 'Boa tarde';
    return 'Boa noite';
  });

  readonly nomeUsuario = computed(() => {
    const nome = this.utils.apelidoOuNome(this.auth.usuario) || '';
    return nome.split(' ')[0];
  });

  ngOnInit(): void {
    this.carregarUnidades();
    this.auth.usuarioChanged$.subscribe(() => this.carregarUnidades());
  }

  onUnidadeChange(event: any): void {
    const value = event?.detail ?? event?.target?.value;
    if (!value || typeof value !== 'string') return;
    this.selectedUnidadeId.set(value);
  }

  onSubordinadasChange(value: boolean): void {
    this.subordinadas.set(value);
  }

  irParaCockpitGabinete(): void {
    this.router.navigate(['gestao', 'paineis-gerenciais', 'cockpit-gabinete']);
  }

  irParaPaineisGerenciais(): void {
    this.router.navigate(['gestao', 'paineis-gerenciais']);
  }

  irParaProjetosEstruturantes(): void {
    this.router.navigate(['gestao', 'projeto']);
  }

  irParaMuralAvisos(): void {
    this.muralService.solicitarExibicao();
  }

  irParaRelatorioAgentes(): void {
    this.go.navigate(
      { route: ['relatorios', 'agentes'] },
      { metadata: { unidade_id: this.selectedUnidadeId() } },
    );
  }

  irParaRelatorioUnidades(): void {
    this.go.navigate({
      route: ['relatorios', 'unidades'],
      params: {
        filter: {
          unidade_id: this.selectedUnidadeId(),
          incluir_unidades_subordinadas: this.subordinadas(),
        },
      },
    });
  }

  irParaPlanosEntregasVigentes(): void {
    this.router.navigate(['gestao', 'planejamento']);
  }

  irParaLacunasPE(): void {
    this.router.navigate(['relatorios', 'planos-entrega', 'lacunas']);
  }

  irParaLacunasPT(): void {
    this.router.navigate(['relatorios', 'planos-trabalho', 'lacunas']);
  }

  private carregarUnidades(): void {
    const areas = (this.auth.usuario?.areas_trabalho ?? []).filter(a => a.unidade);
    const unidadesVinculadas = [
      ...new Map(
        areas.map(a => [
          a.unidade_id,
          {
            id: a.unidade_id,
            sigla: a.unidade!.sigla,
            nome: a.unidade!.nome,
            path: a.unidade!.path,
            isLotado: a.atribuicoes?.some(attr => attr.atribuicao === 'LOTADO') ?? false,
          },
        ]),
      ).values(),
    ];

    const unidadeAtiva = this.auth.unidade;
    const defaultId =
      unidadeAtiva?.id ??
      unidadesVinculadas.find(u => u.isLotado)?.id ??
      this.unidadeMaisAlta(unidadesVinculadas)?.id ??
      unidadesVinculadas[0]?.id ??
      '';

    // Quando uma unidade específica está selecionada no ambiente (ex.: PGAAE),
    // restringe os Destaques da Unidade apenas à unidade selecionada no ambiente.
    if (unidadeAtiva && unidadeAtiva.sigla !== 'PGE-MA') {
      const selecionada = unidadesVinculadas.find(u => u.id === unidadeAtiva.id) ?? {
        id: unidadeAtiva.id,
        sigla: unidadeAtiva.sigla,
        nome: unidadeAtiva.nome,
      };
      this.aplicarOpcoes([selecionada], selecionada.id);
      return;
    }

    // No ambiente geral (PGE-MA), lista apenas as unidades vinculadas do usuário
    this.aplicarOpcoes(unidadesVinculadas, defaultId);
  }

  private unidadeMaisAlta<T extends { path?: string }>(unidades: T[]): T | undefined {
    return [...unidades].sort((a, b) => this.profundidade(a.path) - this.profundidade(b.path))[0];
  }

  private profundidade(path?: string): number {
    if (path === undefined || path === null) return Number.MAX_SAFE_INTEGER;
    return path.split('/').filter(Boolean).length;
  }

  private aplicarOpcoes(
    unidades: { id: string; sigla: string; nome: string }[],
    defaultId: string,
  ): void {
    const effectiveDefault =
      unidades.some(u => u.id === defaultId) ? defaultId : (unidades[0]?.id ?? '');

    const ordenadas = [...unidades].sort((a, b) => {
      if (a.id === effectiveDefault) return -1;
      if (b.id === effectiveDefault) return 1;
      return 0;
    });

    this.unidadeOptions.set(
      ordenadas.map(u => ({
        value: u.id,
        label: `${u.sigla} - ${u.nome}`,
        selected: u.id === effectiveDefault,
      }))
    );

    if (effectiveDefault) {
      this.selectedUnidadeId.set(effectiveDefault);
    }
  }
}
