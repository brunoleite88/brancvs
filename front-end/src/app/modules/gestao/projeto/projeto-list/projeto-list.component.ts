import { Component, Injector, ViewChild } from '@angular/core';
import { AbstractControl, FormGroup } from '@angular/forms';
import { GridComponent } from 'src/app/components/grid/grid.component';
import { QueryOrderBy } from 'src/app/dao/dao-base.service';
import { ProjetoDaoService } from 'src/app/dao/projeto-dao.service';
import { Projeto } from 'src/app/models/projeto.model';
import { PageListBase } from 'src/app/modules/base/page-list-base';
import { LookupItem } from 'src/app/services/lookup.service';

@Component({
  selector: 'app-projeto-list',
  templateUrl: './projeto-list.component.html',
  styleUrls: ['./projeto-list.component.scss'],
  standalone: false
})
export class ProjetoListComponent extends PageListBase<Projeto, ProjetoDaoService> {
  @ViewChild(GridComponent, { static: false }) public grid?: GridComponent;

  public orderBy: QueryOrderBy[] = [['numero', 'asc']];

  public STATUS_LIST: LookupItem[] = [
    { key: 'PLANEJADO', value: 'Planejado' },
    { key: 'INICIADO', value: 'Iniciado' },
    { key: 'CONCLUIDO', value: 'Concluído' },
    { key: 'SUSPENSO', value: 'Suspenso' },
    { key: 'CANCELADO', value: 'Cancelado' },
  ];

  public DATAS_FILTRO: LookupItem[] = [
    { key: 'VIGENTE', value: 'Vigente' },
    { key: 'NAOVIGENTE', value: 'Não vigente' },
    { key: 'INICIAM', value: 'Iniciam' },
    { key: 'FINALIZAM', value: 'Finalizam' },
  ];

  constructor(public injector: Injector, dao: ProjetoDaoService) {
    super(injector, Projeto, ProjetoDaoService);
    this.title = "Projetos Estruturantes";
    this.code = "MOD_PROJ";
    this.filter = this.fh.FormBuilder({
      nome: { default: "" },
      status: { default: null },
      data_filtro: { default: null },
      data_filtro_inicio: { default: new Date() },
      data_filtro_fim: { default: new Date() },
    }, this.cdRef, this.filterValidate);

    this.addOption(this.OPTION_INFORMACOES);
    this.addOption(this.OPTION_EXCLUIR);
    this.addOption(this.OPTION_LOGS, "MOD_AUDIT_LOG");
  }

  public filterValidate = (control: AbstractControl, controlName: string) => {
    let result = null;
    if (controlName === 'data_filtro_inicio' && control.value > this.filter?.controls.data_filtro_fim.value) {
      result = 'Maior que fim';
    } else if (controlName === 'data_filtro_fim' && control.value < this.filter?.controls.data_filtro_inicio.value) {
      result = 'Menor que início';
    }
    return result;
  };

  public filterClear(filter: FormGroup): void {
    if (!filter?.controls) return;
    filter.controls.nome.setValue("");
    filter.controls.status.setValue(null);
    filter.controls.data_filtro.setValue(null);
    filter.controls.data_filtro_inicio.setValue(new Date());
    filter.controls.data_filtro_fim.setValue(new Date());
    super.filterClear(filter);
  }

  public filterWhere = (filter: FormGroup) => {
    const result: any[] = [];
    const form: any = filter.value;

    if (form.nome?.trim()?.length) {
      const term = "%" + form.nome.trim().replace(/\s+/g, "%") + "%";
      result.push(["or", ["nome", "like", term], ["descricao", "like", term], ["finalidade", "like", term]]);
    }

    if (form.status && form.status !== 'TODOS') {
      result.push(["status", "==", form.status]);
    }

    if (form.data_filtro && form.data_filtro_inicio && form.data_filtro_fim) {
      const inicio = form.data_filtro_inicio;
      const fim = form.data_filtro_fim;
      if (form.data_filtro === 'VIGENTE') {
        result.push(["data_inicio", "<=", fim]);
        result.push(["data_fim", ">=", inicio]);
      } else if (form.data_filtro === 'NAOVIGENTE') {
        result.push(["or", ["data_inicio", ">", fim], ["data_fim", "<", inicio]]);
      } else if (form.data_filtro === 'INICIAM') {
        result.push(["data_inicio", ">=", inicio]);
        result.push(["data_inicio", "<=", fim]);
      } else if (form.data_filtro === 'FINALIZAM') {
        result.push(["data_fim", ">=", inicio]);
        result.push(["data_fim", "<=", fim]);
      }
    }

    return result;
  };

  public statusLabel(status: string): string {
    return this.STATUS_LIST.find(s => s.key === status)?.value || status || '—';
  }

  public formatProgresso(value: any): string {
    const num = Number(value || 0);
    return Number.isInteger(num) ? `${num}%` : `${num.toFixed(1).replace('.', ',')}%`;
  }
}
