import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from 'src/app/guards/auth.guard';
import { ConfigResolver } from 'src/app/resolvies/config.resolver';
import { ProjetoListComponent } from './projeto-list/projeto-list.component';
import { ProjetoFormComponent } from './projeto-form/projeto-form.component';

const routes: Routes = [
  { path: '', component: ProjetoListComponent, canActivate: [AuthGuard], resolve: { config: ConfigResolver }, runGuardsAndResolvers: 'always', data: { title: "Projetos Estruturantes", breadcrumb: "Projetos Estruturantes" } },
  { path: 'new', component: ProjetoFormComponent, canActivate: [AuthGuard], resolve: { config: ConfigResolver }, runGuardsAndResolvers: 'always', data: { title: "Inclusão de Projeto Estruturante", modal: true } },
  { path: ':id/edit', component: ProjetoFormComponent, canActivate: [AuthGuard], resolve: { config: ConfigResolver }, runGuardsAndResolvers: 'always', data: { title: "Edição de Projeto Estruturante", modal: true } },
  { path: ':id/consult', component: ProjetoFormComponent, canActivate: [AuthGuard], resolve: { config: ConfigResolver }, runGuardsAndResolvers: 'always', data: { title: "Consulta a Projeto Estruturante", modal: true } }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ProjetoRoutingModule { }
