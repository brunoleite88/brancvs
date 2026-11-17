import { Component, Injector, ViewChild } from '@angular/core';
import { AbstractControl, FormGroup } from '@angular/forms';
import { EditableFormComponent } from 'src/app/components/editable-form/editable-form.component';
import { EixoTematicoDaoService } from 'src/app/dao/eixo-tematico-dao.service';
import { IIndexable } from 'src/app/models/base.model';
import { EixoTematico } from 'src/app/models/eixo-tematico.model';
import { PageFormBase } from 'src/app/modules/base/page-form-base';

@Component({
    selector: 'app-eixo-tematico-form',
    templateUrl: './eixo-tematico-form.component.html',
    styleUrls: ['./eixo-tematico-form.component.scss'],
    standalone: false
})
export class EixoTematicoFormComponent extends PageFormBase<EixoTematico, EixoTematicoDaoService> {
  @ViewChild(EditableFormComponent, { static: false }) public editableForm?: EditableFormComponent;

  constructor(public injector: Injector) {
    super(injector, EixoTematico, EixoTematicoDaoService);
    this.form = this.fh.FormBuilder({
      nome: {default: ""},
      icone: {default: ""},
      cor: {default: ""},
      descricao: {default: ""},
    }, this.cdRef, this.validate);
  }
  
  public validate = (control: AbstractControl, controlName: string) => {
    let result = null;
    if (['nome', 'descricao', 'icone', 'cor'].includes(controlName) && !String(control.value ?? '').trim().length) {
      result = "Obrigatório";
    }
    return result;
  }

  public loadData(entity: EixoTematico, form: FormGroup) {
    let formValue = Object.assign({}, form.value);
    form.patchValue(this.util.fillForm(formValue, entity));
  }

  public initializeData(form: FormGroup) {
    this.entity = new EixoTematico();
    form.patchValue(this.entity);
  }

  public async saveData(form: IIndexable): Promise<EixoTematico> {
    return new Promise<EixoTematico>((resolve) => {
      const eixo = this.util.fill(new EixoTematico(), this.entity || new EixoTematico());
      const preenchido = this.util.fillForm(eixo, this.form!.value);
      preenchido.nome = (preenchido.nome || '').trim();
      preenchido.descricao = (preenchido.descricao || '').trim();
      resolve(preenchido);
    });
  }

  public titleEdit = (entity: EixoTematico): string => {
    return "Editando " + this.lex.translate("Eixo Temático") + ': ' + (entity?.nome || "");
  }

}
