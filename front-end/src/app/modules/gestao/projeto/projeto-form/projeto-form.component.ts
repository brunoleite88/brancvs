import { Component, Injector, ViewChild } from '@angular/core';
import { AbstractControl, FormGroup } from '@angular/forms';
import { EditableFormComponent } from 'src/app/components/editable-form/editable-form.component';
import { ProjetoDaoService } from 'src/app/dao/projeto-dao.service';
import { IIndexable } from 'src/app/models/base.model';
import { Projeto } from 'src/app/models/projeto.model';
import { PageFormBase } from 'src/app/modules/base/page-form-base';
import { LookupItem } from 'src/app/services/lookup.service';

@Component({
  selector: 'app-projeto-form',
  templateUrl: './projeto-form.component.html',
  styleUrls: ['./projeto-form.component.scss'],
  standalone: false
})
export class ProjetoFormComponent extends PageFormBase<Projeto, ProjetoDaoService> {
  @ViewChild(EditableFormComponent, { static: false }) public editableForm?: EditableFormComponent;

  public statusList: LookupItem[] = [
    { key: 'PLANEJADO', value: 'Planejado' },
    { key: 'INICIADO', value: 'Iniciado' },
    { key: 'CONCLUIDO', value: 'Concluído' },
    { key: 'SUSPENSO', value: 'Suspenso' },
    { key: 'CANCELADO', value: 'Cancelado' }
  ];

  constructor(public injector: Injector) {
    super(injector, Projeto, ProjetoDaoService);
    this.form = this.fh.FormBuilder({
      nome: { default: "" },
      finalidade: { default: "" },
      descricao: { default: "" },
      status: { default: "PLANEJADO" },
      data_inicio: { default: new Date() },
      data_fim: { default: new Date() },
      progresso: { default: 0 },
      duracao: { default: 0 },
      custo: { default: 0 }
    }, this.cdRef, this.validate);
  }

  public validate = (control: AbstractControl, controlName: string) => {
    let result = null;
    if (['nome'].indexOf(controlName) >= 0 && !control.value?.length) {
      result = "Obrigatório";
    }
    return result;
  }

  public async loadData(entity: Projeto, form: FormGroup) {
    let formValue = Object.assign({}, form.value);
    form.patchValue(this.util.fillForm(formValue, entity));
  }

  public initializeData(form: FormGroup) {
    this.entity = new Projeto();
    this.loadData(this.entity, form);
  }

  public async saveData(form: IIndexable): Promise<Projeto> {
    return new Promise<Projeto>((resolve, reject) => {
      let projeto = this.util.fill(new Projeto(), this.entity!);
      projeto = this.util.fillForm(projeto, this.form!.value);
      resolve(projeto);
    });
  }

  public titleEdit = (entity: Projeto): string => {
    return "Editando Projeto Estruturante: " + (entity?.nome || "");
  }
}
