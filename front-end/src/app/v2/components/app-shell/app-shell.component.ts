import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  computed,
  ElementRef,
  HostListener,
  inject,
  OnInit,
  signal,
  TemplateRef,
  ViewChild,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { SafeUrl } from '@angular/platform-browser';
import { NavigationEnd, Router } from '@angular/router';
import { filter, map, distinctUntilChanged, debounceTime } from 'rxjs';
import { WebcomponentsAngularModule } from '@govbr-ds/webcomponents-angular';
import { AuthService, UnidadeVinculada } from 'src/app/services/auth.service';
import { DialogService } from 'src/app/services/dialog.service';
import { GlobalsService } from 'src/app/services/globals.service';
import { NavigateService } from 'src/app/services/navigate.service';
import { AlertaPendenteMural, MuralAvisoTenantService } from 'src/app/services/mural-aviso-tenant.service';
import { MessageService } from 'src/app/v2/services/message.service';
import { NotificacaoService } from 'src/app/modules/uteis/notificacoes/notificacao.service';
import { UtilService } from 'src/app/services/util.service';
import { AppComponent } from 'src/app/app.component';
import { MuralAvisoCardComponent } from '../mural-aviso-card/mural-aviso-card.component';

export interface MuralAvisoExibicao {
  id: string;
  titulo: string;
  conteudo: string;
  remetente: string;
  data_publicacao: string;
  dataFormatada: string;
  horaFormatada: string;
  lido: boolean;
}

@Component({
  selector: 'app-shell-v2',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, WebcomponentsAngularModule, MuralAvisoCardComponent],
  templateUrl: './app-shell.component.html',
  styleUrls: ['./app-shell.component.scss']
})
export class AppShellV2Component implements OnInit {
  readonly auth = inject(AuthService);
  readonly gb = inject(GlobalsService);
  readonly go = inject(NavigateService);
  readonly notificacao = inject(NotificacaoService);
  readonly utils = inject(UtilService);
  readonly cdRef = inject(ChangeDetectorRef);
  private readonly router = inject(Router);
  private readonly dialog = inject(DialogService);
  private readonly muralService = inject(MuralAvisoTenantService);
  private readonly message = inject(MessageService);

  @ViewChild('menuTrigger') menuTriggerRef?: ElementRef<HTMLButtonElement>;
  @ViewChild('menuClose')   menuCloseRef?:   ElementRef<HTMLButtonElement>;
  @ViewChild('muralTemplate') muralTemplate!: TemplateRef<any>;

  readonly unidadeAberta = signal(false);
  readonly perfilAberto  = signal(false);
  readonly menuAberto    = signal(false);

  readonly abaMuralAtiva = signal<'ALERTAS' | 'COMUNICADOS'>('ALERTAS');
  readonly avisosLista = signal<MuralAvisoExibicao[]>([]);
  readonly avisosNaoLidosCount = signal<number>(0);
  readonly alertasPendentesLista = signal<AlertaPendenteMural[]>([]);
  readonly alertasHabilitados = signal<boolean>(true);
  readonly totalIndicadorMural = computed(() => this.avisosNaoLidosCount() + this.alertasPendentesLista().length);

  readonly exibirFormNovoAviso = signal(false);
  readonly novoAvisoTitulo = signal('');
  readonly novoAvisoConteudo = signal('');
  readonly novoAvisoExpiracao = signal('');
  readonly salvandoAviso = signal(false);

  private activeMuralDialog?: any;
  private readonly pastaMenuAberta = signal<string | null>(null);

  isFolderAberto(name: string): boolean {
    return this.pastaMenuAberta() === name;
  }

  toggleFolder(name: string): void {
    this.pastaMenuAberta.update((atual) => (atual === name ? null : name));
  }

  ngOnInit(): void {
    if (!this.gb.contexto) {
      if (this.auth.hasPermissionTo('CTXT_GEST')) {
        this.gb.setContexto('GESTAO', false);
      } else if (this.auth.hasPermissionTo('CTXT_EXEC')) {
        this.gb.setContexto('EXECUCAO', false);
      }
    }
    this.auth.usuarioChanged$.subscribe(() => this.cdRef.markForCheck());
    this.verificarMural();
    this.atualizarContadorMural();
  }

  get podeGerenciarMural(): boolean {
    return this.auth.isAdmin() || this.auth.hasPermissionTo('MOD_CFG') || this.auth.hasPermissionTo('CTXT_DEV');
  }

  abrirMuralAvisos(): void {
    this.exibirMural(true, true);
  }

  toggleFormNovoAviso(): void {
    this.exibirFormNovoAviso.set(!this.exibirFormNovoAviso());
  }

  acessarRotaMural(rota: string[]): void {
    if (this.activeMuralDialog) {
      this.activeMuralDialog.close();
      this.activeMuralDialog = undefined;
    }
    this.router.navigate(rota);
  }

  abrirPreferenciasDoMural(): void {
    if (this.activeMuralDialog) {
      this.activeMuralDialog.close();
      this.activeMuralDialog = undefined;
    }
    if (this.auth.usuario?.id) {
      this.navigatePerfil(['configuracoes', 'preferencia', 'usuario', this.auth.usuario.id]);
    }
  }

  publicarNovoAviso(): void {
    const titulo = this.novoAvisoTitulo().trim();
    const conteudo = this.novoAvisoConteudo().trim();
    if (!titulo || !conteudo) return;

    this.salvandoAviso.set(true);
    this.muralService.publicarNoTenant(titulo, conteudo, this.novoAvisoExpiracao() || undefined)
      .then(() => {
        this.salvandoAviso.set(false);
        this.novoAvisoTitulo.set('');
        this.novoAvisoConteudo.set('');
        this.novoAvisoExpiracao.set('');
        this.exibirFormNovoAviso.set(false);
        this.message.success('Comunicado publicado no Mural de Avisos.');
        this.recarregarAvisosModal();
      })
      .catch(() => {
        this.salvandoAviso.set(false);
        this.message.error('Não foi possível publicar o comunicado.');
      });
  }

  removerAvisoMural(id: string): void {
    if (!confirm('Deseja remover este comunicado do Mural de Avisos?')) return;
    this.muralService.excluirNoTenant(id)
      .then(() => {
        this.message.success('Comunicado removido.');
        this.recarregarAvisosModal();
      })
      .catch(() => this.message.error('Erro ao remover comunicado.'));
  }

  private atualizarContadorMural(): void {
    Promise.all([
      this.muralService.getPendentes(false),
      this.muralService.getAlertasPendentes(),
    ]).then(([naoLidos, alertasRes]) => {
      this.avisosNaoLidosCount.set(naoLidos.length);
      this.alertasHabilitados.set(alertasRes.habilitado);
      this.alertasPendentesLista.set(alertasRes.itens);
      this.cdRef.markForCheck();
    });
  }

  private recarregarAvisosModal(): void {
    Promise.all([
      this.muralService.getPendentes(true),
      this.muralService.getAlertasPendentes(),
    ]).then(([avisos, alertasRes]) => {
      this.avisosLista.set(this.formatarAvisos(avisos));
      this.avisosNaoLidosCount.set(avisos.filter(a => !a.lido).length);
      this.alertasHabilitados.set(alertasRes.habilitado);
      this.alertasPendentesLista.set(alertasRes.itens);
      this.cdRef.markForCheck();
    });
  }

  private verificarMural(): void {
    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd),
      map(() => this.router.parseUrl(this.router.url).queryParams['mural'] === '1'),
      debounceTime(150),
      filter(temParamMural => temParamMural),
      distinctUntilChanged(),
    ).subscribe(() => this.tentarExibirMural());

    this.muralService.exibicaoSolicitada$.subscribe(() => this.exibirMural(true, true));
  }

  private tentarExibirMural(): void {
    const urlTree = this.router.parseUrl(this.router.url);
    if (urlTree.queryParams['mural'] !== '1') return;

    delete urlTree.queryParams['mural'];
    this.router.navigateByUrl(urlTree, { replaceUrl: true });

    this.exibirMural(false, false);
  }

  private exibirMural(aberturaManual: boolean, incluirLidos: boolean): void {
    Promise.all([
      this.muralService.getPendentes(incluirLidos),
      this.muralService.getAlertasPendentes(),
    ]).then(([avisos, alertasRes]) => {
      this.avisosLista.set(this.formatarAvisos(avisos));
      this.avisosNaoLidosCount.set(avisos.filter(a => !a.lido).length);
      this.alertasHabilitados.set(alertasRes.habilitado);
      this.alertasPendentesLista.set(alertasRes.itens);

      if (avisos.length === 0 && alertasRes.itens.length === 0 && !aberturaManual) {
        return;
      }

      if (alertasRes.itens.length > 0) {
        this.abaMuralAtiva.set('ALERTAS');
      } else {
        this.abaMuralAtiva.set('COMUNICADOS');
      }

      this.exibirModalMural();
    });
  }

  private formatarAvisos(avisos: { id: string; titulo: string; conteudo: string; remetente: string; data_publicacao: string; lido: boolean }[]): MuralAvisoExibicao[] {
    return avisos.map(aviso => {
      const date = new Date(aviso.data_publicacao);
      return {
        ...aviso,
        dataFormatada: date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }),
        horaFormatada: date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
      };
    });
  }

  private exibirModalMural(): void {
    this.exibirFormNovoAviso.set(false);
    const result = this.dialog.template(
      { title: 'Mural de Avisos e Prazos', modalWidth: 740 },
      this.muralTemplate,
      [{ label: 'Li e estou ciente', color: 'btn-primary', value: true }],
      {},
    );
    this.activeMuralDialog = result.dialog;
    result.result.then(({ dialog: dlg }) => {
      dlg.close();
      this.activeMuralDialog = undefined;
      this.muralService.confirmarLeitura().then(() => {
        this.avisosNaoLidosCount.set(0);
      });
    });
  }

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.menuAberto())    { this.fecharMenu(); return; }
    if (this.perfilAberto())  { this.perfilAberto.set(false); return; }
    if (this.unidadeAberta()) { this.unidadeAberta.set(false); }
  }

  toggleMenu(): void {
    if (this.menuAberto()) {
      this.fecharMenu();
    } else {
      this.menuAberto.set(true);
      requestAnimationFrame(() => this.menuCloseRef?.nativeElement.focus());
    }
  }

  fecharMenu(): void {
    this.menuAberto.set(false);
    this.pastaMenuAberta.set(null);
    requestAnimationFrame(() => this.menuTriggerRef?.nativeElement.focus());
  }

  toggleUnidade(): void {
    this.unidadeAberta.set(!this.unidadeAberta());
    this.perfilAberto.set(false);
  }

  togglePerfil(): void {
    this.perfilAberto.set(!this.perfilAberto());
    this.unidadeAberta.set(false);
  }

  onKeyActivate(event: KeyboardEvent, fn: () => void): void {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      fn();
    }
  }

  get menuItems(): any[] {
    const app = AppComponent.instance;
    if (!app) return [];
    const hasGestao   = this.auth.hasPermissionTo('CTXT_GEST');
    const hasExecucao = this.auth.hasPermissionTo('CTXT_EXEC');
    let items: any[] = [];
    if (hasGestao)       items = [...(app.moduloGestao   || [])];
    else if (hasExecucao) items = [...(app.moduloExecucao || [])];
    if (this.auth.hasPermissionTo('CTXT_DEV') || this.auth.hasPermissionTo('MOD_DEV')) {
      const devSubmenu: any[] = [];
      (app.moduloDev || []).forEach((entry: any) => {
        if (entry.menu && Array.isArray(entry.menu)) {
          entry.menu.forEach((sub: any) => {
            if (sub && sub !== '-') devSubmenu.push(sub);
          });
        } else if (entry.route) {
          if (!this.isImpersonateMenuItem(entry) || this.auth.isAdmin()) {
            devSubmenu.push(entry);
          }
        }
      });

      if (devSubmenu.length > 0) {
        items.push({
          name: 'Módulo Desenvolvedor',
          icon: 'bi bi-code-slash',
          permition: 'CTXT_DEV',
          menu: devSubmenu,
        });
      }
    }
    return items;
  }

  private isImpersonateMenuItem(item: { route?: string[] }): boolean {
    return item?.route?.[0] === 'impersonate';
  }

  hasPermission(permition: string | undefined): boolean {
    if (!permition) return true;
    if (permition === 'MOD_CORREGEDORIA_PAINEL') {
      return this.auth.isAdmin()
        || this.auth.hasPermissionTo('MOD_CFG')
        || this.unidadesVinculadas.some(u => u.sigla === 'CG' || u.sigla?.startsWith('CG-'));
    }
    return this.auth.hasPermissionTo(permition);
  }

  navigate(item: any): void {
    if (!item?.route) return;
    this.fecharMenu();
    this.go.navigate({ route: item.route, params: item.params }, item.metadata || { root: true });
  }

  goHome(): void {
    this.fecharMenu();
    this.gb.goHome();
  }

  irParaNotificacoes(): void {
    this.go.navigate({ route: ['uteis', 'notificacoes'] });
  }

  async selecionaUnidade(id: string, matricula: string | null): Promise<void> {
    if (!matricula) return;
    this.unidadeAberta.set(false);
    await this.auth.selecionaUnidade(id, matricula, this.cdRef);
    window.location.reload();
  }

  navigatePerfil(route: any[], metadata?: any): void {
    this.perfilAberto.set(false);
    this.go.navigate({ route }, metadata || { root: true, modal: true });
  }

  logout(): void {
    this.perfilAberto.set(false);
    this.auth.logOut();
  }

  get usuarioNome(): string {
    return this.utils.shortName(this.utils.apelidoOuNome(this.auth.usuario) || '');
  }

  get usuarioFoto(): SafeUrl {
    return this.gb.getResourcePath(this.auth.usuario?.url_foto || 'assets/images/profile.png');
  }

  get unidadesVinculadas(): UnidadeVinculada[] {
    return this.auth.unidadesVinculadas || [];
  }

  get temUnidadeAntiga(): boolean {
    return this.unidadesVinculadas.some((unidade) => unidade.unidade_antiga);
  }

  get logoSrc(): string {
    return this.gb.getResourcePath('assets/images/icon_' + this.gb.theme + '.png');
  }

  get naoLidas(): number {
    return this.notificacao.naoLidas;
  }
}
