import { ChangeDetectorRef, Component, Injector, Input, ViewChild } from '@angular/core';
import { GridComponent } from 'src/app/components/grid/grid.component';
import { FormGroup } from "@angular/forms";
import { IIndexable } from 'src/app/models/base.model';
import { PageFrameBase } from 'src/app/modules/base/page-frame-base';
import { TemplateService } from '../../templates/template.service';
import { HasNotificacao, NotificacoesConfig } from 'src/app/models/notificacao.model';
import { Template } from 'src/app/models/template.model';

export class Notificar {
  public codigo: string = "";
  public notifica: boolean = true;
  public descricao: string = "";
  public constructor(data?: any) { Object.assign(this, data || {}); }
}

@Component({
    selector: 'notificacoes-config',
    templateUrl: './notificacoes-config.component.html',
    styleUrls: ['./notificacoes-config.component.scss'],
    standalone: false
})
export class NotificacoesConfigComponent extends PageFrameBase {
  @ViewChild(GridComponent, { static: false }) public grid?: GridComponent;
  @Input() cdRef: ChangeDetectorRef;
  @Input() set entity(value: HasNotificacao) { super.entity = value; } get entity(): HasNotificacao { return super.entity; }
  @Input() entidadeId?: string;
  @Input() unidadeId?: string;
  @Input() disabled: boolean = false;

  public form: FormGroup;
  public notificar: Notificar[] = [];
  public source: Template[] = [];
  public loadingNotificacoes: boolean = false;
  public templateService: TemplateService;

  constructor(public injector: Injector) {
    super(injector);
    this.cdRef = injector.get<ChangeDetectorRef>(ChangeDetectorRef);
    this.templateService = injector.get<TemplateService>(TemplateService);
    this.code = "MOD_NOTF_CONF";
    this.title = "Notificações";
    this.form = this.fh.FormBuilder({
      enviar_petrvs: {default: true},
      enviar_email: {default: true},
      enviar_whatsapp: {default: true}
    });
  }

  ngOnInit(): void {
    super.ngOnInit();
    this.entidadeId = this.entidadeId || this.queryParams?.entidadeId;
    this.unidadeId = this.unidadeId || this.queryParams?.unidadeId;
  }
 
  ngAfterViewInit() {
    super.ngAfterViewInit();
    this.loadNotificacoes(this.entity);
  }

  public loadNotificacoes(entity: HasNotificacao) {
    (async () => {
      this.loadingNotificacoes = true;
      this.cdRef.detectChanges();
      try {
        this.source = await this.templateService.loadNotificacoes(this.entidadeId, this.unidadeId);
        if (!this.entity.notificacoes) {
          this.entity.notificacoes = new NotificacoesConfig();
        }
        this.notificar = this.templateService.buildNotificar(this.entity.notificacoes);
        let formValue = Object.assign({}, this.form.value);
        this.form.patchValue(this.util.fillForm(formValue, entity.notificacoes));
      } finally {
        this.loadingNotificacoes = false;
        this.cdRef.detectChanges();
      }
    })();
  }

  public onToggleNotificarItem(item: Notificar, checked: boolean) {
    item.notifica = checked;
    this.updateNotificacoes();
  }

  public async saveData(form?: IIndexable) {
    this.entity!.notificacoes_templates = this.entity!.notificacoes_templates?.filter(x => !!x._status);
    if (!this.entity!.notificacoes) {
      this.entity!.notificacoes = new NotificacoesConfig();
    }
    this.util.fillForm(this.entity!.notificacoes, this.form!.value);
    this.entity!.notificacoes!.nao_notificar = this.notificar.filter(x => !x.notifica).map(x => x.codigo);
    return this.entity as HasNotificacao;
  }

  public updateNotificacoes() {
    if (!this.entity!.notificacoes) {
      this.entity!.notificacoes = new NotificacoesConfig();
    }
    this.entity!.notificacoes!.enviar_petrvs = this.form.controls.enviar_petrvs.value;
    this.entity!.notificacoes!.enviar_email = this.form.controls.enviar_email.value;
    this.entity!.notificacoes!.enviar_whatsapp = this.form.controls.enviar_whatsapp.value;
    this.entity!.notificacoes!.nao_notificar = this.notificar.filter(x => !x.notifica).map(x => x.codigo);
  }

}
