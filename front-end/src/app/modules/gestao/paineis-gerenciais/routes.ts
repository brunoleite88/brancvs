import { Routes } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { authTenantVersionInterceptor, errorInterceptor } from 'src/app/v2/infra/http/interceptors';
import { BreadcrumbService } from 'src/app/v2/components/breadcrumb/breadcrumb.service';
import { UnidadeService } from 'src/app/v2/services/unidade.service';
import { PainelApiClient } from './infra/painel-api.client';

export const routes: Routes = [
  {
    path: '',
    data: { breadcrumb: 'Painéis Gerenciais' },
    providers: [
      provideHttpClient(withInterceptors([authTenantVersionInterceptor, errorInterceptor])),
      BreadcrumbService,
      UnidadeService,
      PainelApiClient,
    ],
    children: [
      {
        path: '',
        loadComponent: () =>
          import('./ui/paineis-gerenciais.page').then(m => m.PaineisGerenciaisPage),
      },
      {
        path: 'cockpit-gabinete',
        data: { breadcrumb: 'Cockpit do Gabinete' },
        loadComponent: () =>
          import('./ui/pages/cockpit-gabinete.page').then(m => m.CockpitGabinetePage),
      },
      {
        path: 'metas-entregas',
        data: { breadcrumb: 'Metas e Entregas' },
        loadComponent: () =>
          import('./ui/pages/alinhamento-desempenho.page').then(m => m.AlinhamentoDesempenhoPage),
      },
      {
        path: 'alinhamento-desempenho',
        redirectTo: 'metas-entregas',
        pathMatch: 'full',
      },
      {
        path: 'conformidade',
        data: { breadcrumb: 'Conformidade' },
        loadComponent: () =>
          import('./ui/pages/conformidade.page').then(m => m.ConformidadePage),
      },
      {
        path: 'projetos',
        data: { breadcrumb: 'Projetos' },
        loadComponent: () =>
          import('./ui/pages/projetos-painel.page').then(m => m.ProjetosPainelPage),
      },
      {
        path: 'modalidades',
        redirectTo: 'projetos',
        pathMatch: 'full',
      },
      // {
      //   path: 'gestao-pgd',
      //   data: { breadcrumb: 'Abrangência do PGD' },
      //   loadComponent: () =>
      //     import('./ui/pages/gestao-pgd.page').then(m => m.GestaoPgdPage),
      // },
    ],
  },
];
