import { Component, Injector, ViewChild } from '@angular/core';
import { GridComponent } from 'src/app/components/grid/grid.component';
import { ProjetoDaoService } from 'src/app/dao/projeto-dao.service';
import { Projeto } from 'src/app/models/projeto.model';
import { PageListBase } from 'src/app/modules/base/page-list-base';

@Component({
  selector: 'app-projeto-list',
  templateUrl: './projeto-list.component.html',
  styleUrls: ['./projeto-list.component.scss'],
  standalone: false
})
export class ProjetoListComponent extends PageListBase<Projeto, ProjetoDaoService> {
  @ViewChild(GridComponent, { static: false }) public grid?: GridComponent;

  constructor(public injector: Injector, dao: ProjetoDaoService) {
    super(injector, Projeto, ProjetoDaoService);
    this.title = "Projetos Estruturantes da PGE-MA";
    this.code = "MOD_PROJ";
    this.filter = this.fh.FormBuilder({
      nome: { default: "" },
    });

    this.addOption(this.OPTION_INFORMACOES);
    this.addOption(this.OPTION_EXCLUIR);
    this.addOption(this.OPTION_LOGS, "MOD_AUDIT_LOG");
  }

  public getBadgeStatus(status: string): string {
    switch (status) {
      case 'CONCLUIDO': return 'bg-success';
      case 'INICIADO': return 'bg-primary';
      case 'PLANEJADO': return 'bg-warning text-dark';
      case 'SUSPENSO': return 'bg-secondary';
      case 'CANCELADO': return 'bg-danger';
      default: return 'bg-info';
    }
  }
}
