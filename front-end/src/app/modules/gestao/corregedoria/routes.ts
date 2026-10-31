import { Routes } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { authTenantVersionInterceptor, errorInterceptor } from 'src/app/v2/infra/http/interceptors';
import { BreadcrumbService } from 'src/app/v2/components/breadcrumb/breadcrumb.service';
import { CorregedoriaApiClient } from './infra/corregedoria-api.client';

export const routes: Routes = [
  {
    path: '',
    data: { breadcrumb: 'Corregedoria' },
    providers: [
      provideHttpClient(withInterceptors([authTenantVersionInterceptor, errorInterceptor])),
      BreadcrumbService,
      CorregedoriaApiClient,
    ],
    children: [
      {
        path: 'meu-relatorio',
        data: { breadcrumb: 'Meu Relatório Mensal' },
        loadComponent: () =>
          import('./ui/corregedoria-meu-relatorio.component').then(m => m.CorregedoriaMeuRelatorioComponent),
      },
      {
        path: 'painel',
        data: { breadcrumb: 'Painel da Corregedoria' },
        loadComponent: () =>
          import('./ui/corregedoria-painel.component').then(m => m.CorregedoriaPainelComponent),
      },
      {
        path: '',
        redirectTo: 'meu-relatorio',
        pathMatch: 'full',
      },
    ],
  },
];
