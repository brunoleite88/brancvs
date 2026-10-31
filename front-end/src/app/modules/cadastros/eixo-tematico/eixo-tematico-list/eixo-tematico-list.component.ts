import { Component, Injector, ViewChild } from '@angular/core';
import { FormGroup } from '@angular/forms';
import { GridComponent } from 'src/app/components/grid/grid.component';
import { EixoTematicoDaoService } from 'src/app/dao/eixo-tematico-dao.service';
import { PlanejamentoDaoService } from 'src/app/dao/planejamento-dao.service';
import { EixoTematico } from 'src/app/models/eixo-tematico.model';
import { PageListBase } from 'src/app/modules/base/page-list-base';

@Component({
    selector: 'app-eixo-tematico-list',
    templateUrl: './eixo-tematico-list.component.html',
    styleUrls: ['./eixo-tematico-list.component.scss'],
    standalone: false
})
export class EixoTematicoListComponent extends PageListBase<EixoTematico, EixoTematicoDaoService> {
  @ViewChild(GridComponent, { static: false }) public grid?: GridComponent;
  public planejamentoDao: PlanejamentoDaoService;

  constructor(public injector: Injector) {
    super(injector, EixoTematico, EixoTematicoDaoService);
    this.planejamentoDao = injector.get<PlanejamentoDaoService>(PlanejamentoDaoService);
    /* Inicializações */
    this.title = this.lex.translate('Eixos Temáticos');
    this.orderBy = [['nome', 'asc']];
    this.filter = this.fh.FormBuilder({
      nome: {default: ""},
      planejamento_id: {default: null}
     });
    // Testa se o usuário possui permissão para consultar eixos temáticos
    if (this.auth.hasPermissionTo("MOD_EXTM")) {
      this.options.push({
        icon: "bi bi-info-circle",
        label: "Informações",
        onClick: this.consult.bind(this)
      });
    }
    // Testa se o usuário possui permissão para excluir eixos temáticos
    if (this.auth.hasPermissionTo("MOD_EXTM_EXCL")) {
      this.options.push({
        icon: "bi bi-trash",
        label: "Excluir",
        onClick: this.delete.bind(this)
      });
    }
    this.addOption(this.OPTION_LOGS, "MOD_AUDIT_LOG");
  }
  

  public filterClear(filter: FormGroup) {
    filter.controls.nome.setValue("");
    filter.controls.planejamento_id.setValue(null);
    super.filterClear(filter);
  }

  public filterWhere = (filter: FormGroup) => {
    let result: any[] = [];
    let form: any = filter.value;

    if(form.nome?.length) {
      result.push(["nome", "like", "%" + form.nome.trim().replace(" ", "%") + "%"]);
    }
    if(form.planejamento_id?.length) {
      result.push(["planejamento_id", "==", form.planejamento_id]);
    }

    return result;
  }

}
