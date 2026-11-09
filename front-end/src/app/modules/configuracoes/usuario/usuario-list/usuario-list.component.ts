import { Component, Injector, ViewChild, TemplateRef } from '@angular/core';
import { AbstractControl, FormGroup } from '@angular/forms';
import { GridComponent } from 'src/app/components/grid/grid.component';
import { InputSearchComponent } from 'src/app/components/input/input-search/input-search.component';
import { ToolbarButton } from 'src/app/components/toolbar/toolbar-types';
import { PerfilDaoService } from 'src/app/dao/perfil-dao.service';
import { UnidadeDaoService } from 'src/app/dao/unidade-dao.service';
import { UsuarioDaoService } from 'src/app/dao/usuario-dao.service';
import { Perfil } from 'src/app/models/perfil.model';
import { Usuario } from 'src/app/models/usuario.model';
import { PageListBase } from 'src/app/modules/base/page-list-base';
import { ExcelService } from 'src/app/services/excel.service';
import { DossieAgenteService } from 'src/app/services/dossie-agente.service';
import { QueryOptions } from 'src/app/dao/query-options';
import { Observable } from 'rxjs';

@Component({
    selector: 'app-usuario-list',
    templateUrl: './usuario-list.component.html',
    styleUrls: ['./usuario-list.component.scss'],
    standalone: false
})
export class UsuarioListComponent extends PageListBase<Usuario, UsuarioDaoService> {
  @ViewChild(GridComponent, { static: false }) public grid?: GridComponent;
  @ViewChild("unidade", { static: false }) public unidade?: InputSearchComponent;
  @ViewChild("justificativaDialog", { static: false }) public justificativaDialog?: TemplateRef<any>;
  public justificativaForm: FormGroup;
  
  public unidadeDao: UnidadeDaoService;
  public perfilDao: PerfilDaoService;
  public usuarioDao: UsuarioDaoService;
  public xlsx: ExcelService;
  public dossieService: DossieAgenteService;

  public BOTAO_PEDAGIO: ToolbarButton;
  public BOTAO_REMOVE_PEDAGIO: ToolbarButton;

  public onDeleteMessage: string = "Este usuário deixará de ter acesso ao sistema. Deseja confirmar a exclusão?";

  constructor(public injector: Injector) {

    super(injector, Usuario, UsuarioDaoService);
    this.unidadeDao = injector.get<UnidadeDaoService>(UnidadeDaoService);
    this.perfilDao = injector.get<PerfilDaoService>(PerfilDaoService);
    this.usuarioDao = injector.get<UsuarioDaoService>(UsuarioDaoService);
    this.xlsx = injector.get<ExcelService>(ExcelService);
    this.dossieService = injector.get<DossieAgenteService>(DossieAgenteService);

    /* Inicializações */
    this.title = this.lex.translate("Usuários");
    this.code = "MOD_CFG_USER";
    this.join = ["perfil:id,nome", "dispensaPlanoTrabalho"];
    
    this.filter = this.fh.FormBuilder({
      usuario: { default: "" },
      cpf: { default: "" },
      unidade_id: { default: "" },
      perfil_id: { default: null },
      situacao_funcional: { default: null },
      atribuicoes: { default: null },
      situacao: { default: null },
      dispensa_pt: { default: null }
    });
    this.justificativaForm = this.fh.FormBuilder({
      justificativa: { default: ""},
      usuario_id: { default: null }
    }, this.cdRef, this.validateJustificativa);

    this.addOption(this.OPTION_INFORMACOES, "MOD_USER");
    
     this.BOTAO_PEDAGIO = { label: "Tornar teletrabalho indisponível", icon: "bi bi-ban", color: "btn-outline-danger", onClick: (usuario: Usuario) => {
      this.go.navigate(
        { 
          route: ['gestao', 'programa', 'pedagio', usuario.id] }, 
        {
          metadata: {'usuario': usuario},
          modalClose: async (modalResult) => {
            if (modalResult) {
              this.refresh(modalResult.id);
              this.cdRef.detectChanges();
            }
          }
        }
      ); 
    }};

    this.BOTAO_REMOVE_PEDAGIO = { label: "Tornar teletrabalho disponível novamente", icon: "bi bi-check2-circle", color: "btn-outline-primary", onClick: this.removePedagio.bind(this)};

    this.addOption(this.OPTION_EXCLUIR, "MOD_USER_EXCL");

    if (!this.auth.hasPermissionTo("MOD_USER_LIST_ALL")){
      this.filter.controls.unidade_id.setValue(this.auth.usuario?.areas_trabalho?.map(ui => ui.unidade_id));
    }

  }

  public validateJustificativa = (control: AbstractControl, controlName: string) => {
    let result = null;   
    if(controlName == 'justificativa' && !control.value.length){
      result = "Obrigatório";
    }
    return result;
  }

  public dynamicButtons(row: any): ToolbarButton[] { return []; }
  private _unusedDynamicButtons(row: any): ToolbarButton[] {
    let result: ToolbarButton[] = [];
    if (!row.usuario_externo) {
      if (this.auth.hasPermissionTo('MOD_PART_PEDAGIO') && !row.pedagio) result.push(this.BOTAO_PEDAGIO);
      if (this.auth.hasPermissionTo('MOD_PART_PEDAGIO') && row.pedagio) result.push(this.BOTAO_REMOVE_PEDAGIO);
    }
    result.push({
      label: "Dossiê do Agente",
      icon: "bi bi-file-earmark-person",
      color: "btn-outline-info",
      onClick: (usuario: Usuario) => { this.imprimirDossie(usuario); }
    });

    if (this.auth.hasPermissionTo('MOD_USER_EDT')) {
      result.push({
        label: "Redefinir Senha",
        icon: "bi bi-key",
        color: "btn-outline-warning",
        onClick: (usuario: Usuario) => { this.redefinirSenha(usuario); }
      });
    }

    return result;
  }


  public dynamicOptions(row: any): ToolbarButton[] {
    let result: ToolbarButton[] = [];
    if (row.situacao_siape == 'INATIVO' && this.auth.hasPermissionTo("MOD_USER_REATIVAR")){
      result.push({ label: "Ativar temporariamente", icon: "bi bi-check2",  onClick: (usuario: Usuario) => { this.abrirFormAtivar(usuario); }});
    }

    if (this.podeAbrirDispensaPt(row)) {
      result.push({
        label: this.possuiDispensaPt(row) ? "Alterar dispensa de Plano de Trabalho" : "Dispensar de Plano de Trabalho",
        icon: "bi bi-file-earmark-minus",
        onClick: (usuario: Usuario) => this.abrirDispensaPt(usuario)
      });
    }

    if (false && row.perfil.nivel === Perfil.NIVEL.COLABORADOR && !!row.usuario_externo ) {
      result.push(this.OPTION_EXCLUIR)
    }

    result.push({
      label: "Dossiê do Agente",
      icon: "bi bi-file-earmark-person",
      color: "btn-outline-info",
      onClick: (usuario: Usuario) => { this.imprimirDossie(usuario); }
    });

    if (this.auth.hasPermissionTo('MOD_USER_EDT')) {
      result.push({
        label: "Redefinir Senha",
        icon: "bi bi-key",
        color: "btn-outline-warning",
        onClick: (usuario: Usuario) => { this.redefinirSenha(usuario); }
      });
    }

    return result;
  }

  public podeAbrirDispensaPt(row: any): boolean {
    const nivel = this.auth.usuario?.perfil?.nivel ?? -1;
    const perfilAutorizado = nivel === Perfil.NIVEL.DESENVOLVEDOR
      || nivel === Perfil.NIVEL.ADM_MASTER
      || nivel === Perfil.NIVEL.ADM_NEGOCIAL;
    if (!perfilAutorizado) {
      return false;
    }
    // RN05/RN15: só elegível (chefia executora) ou quem já possui dispensa formalizada.
    return !!row?.dispensa_pt_elegivel || this.possuiDispensaPt(row);
  }

  public possuiDispensaPt(row: any): boolean {
    return !!(row?.dispensa_plano_trabalho || row?.dispensa_pt_vigente);
  }

  public abrirDispensaPt(usuario: Usuario): void {
    this.go.navigate(
      { route: ['configuracoes', 'usuario', usuario.id, 'dispensa-plano-trabalho'] },
      {
        modalClose: async (modalResult) => {
          if (modalResult) {
            this.refresh();
            this.cdRef.detectChanges();
          }
        }
      }
    );
  }

  public filterWhere = (filter: FormGroup) => {
    let result: any[] = [];
    if (filter?.controls.usuario?.value?.length) {
      result.push(["nome", "like", "%" + filter?.controls.usuario?.value.trim().replace(" ", "%") + "%"]);
    }
    if (filter?.controls.unidade_id?.value?.length) {    
      const operador = this.auth.hasPermissionTo("MOD_USER_LIST_ALL") ? "==" : "in";
      result.push(["lotacao", operador, filter?.controls.unidade_id.value]);
    }
    if (filter?.controls.perfil_id?.value?.length) {
      result.push(["perfil_id", "==", filter?.controls.perfil_id?.value]);
    }
    if (filter?.controls.situacao_funcional?.value?.length) {
      result.push(["situacao_funcional", "==", filter?.controls.situacao_funcional?.value]);
    }
    if (filter?.controls.atribuicoes?.value?.length) {
      result.push(["atribuicoes", "==", filter?.controls.atribuicoes?.value]);
    }
    if (filter?.controls.cpf?.value?.length) {
      result.push(["cpf", "==", filter?.controls.cpf?.value]);
    }
    if (filter?.controls.situacao?.value?.length) {
      result.push(["situacao", "==", filter?.controls.situacao?.value]);
    }
    if (filter?.controls.dispensa_pt?.value?.length) {
      result.push(["dispensa_pt", "==", filter?.controls.dispensa_pt?.value]);
    }

    return result;
  }

  public onCancel() {
    this.justificativaForm.reset();
  }
  
  public abrirFormAtivar(usuario: Usuario) {
    this.justificativaForm.controls.usuario_id.setValue(usuario.id);
    if (this.justificativaDialog) {
      this.dialog.template({title: "Ativar temporariamente"}, this.justificativaDialog, [], null);
    }
  }


  public onSubmit() {
    if (this.justificativaForm.valid) {
      this.dialog.confirm("Confirmação", "Ao confirmar, o usuário poderá realizar alterações no sistema durante 30 dias. Deseja continuar?").then((confirm: boolean) => {
        if (confirm) {
          const ativo = this.usuarioDao.ativarTemporariamente(this.justificativaForm.controls.usuario_id.value, this.justificativaForm.controls.justificativa.value)
          ativo.then(() => {
            this.dialog.alert("Sucesso", "Usuário ativado temporariamente.");
            this.justificativaForm.reset();
            this.cdRef.detectChanges();
          });   
          ativo.finally(() => {
            this.dialog.closeAll();
            this.refresh()

          });
        }
      });
    }
  }

  public async removePedagio(row: any) {
    this.dialog.confirm("Remover teletrabalho indisponível ?", "Deseja tornar a modalidade teletrabalho disponível para o participante " + (row.nome as string).toUpperCase() + " ?").then(async confirm => {
      if (confirm) {
        await this.usuarioDao!.removePedagio(row.id).then(resposta => {
          (this.grid?.query || this.query!).refreshId(row.id);
          this.cdRef.detectChanges();
        }, error => {
          this.dialog.alert("Erro", error);
        });
      }
    });
  }


  public exportExcel = (form: any, queryOptions: QueryOptions): Observable<any> => {
    return new Observable((subscriber) => {
      const rows = this.grid?.items || [];
      if (rows && rows.length > 0) {
        const dadosExcel = rows.map((u: any) => ({
          'Matrícula': u.matricula || '',
          'CPF': u.cpf || '',
          'Nome': u.nome_exibicao || u.nome || '',
          'Nome Social': u.nome_social || '',
          'E-mail': u.email || '',
          'Telefone': u.telefone || '',
          'Perfil': u.perfil?.nome || '',
          'Vínculo Funcional': this.lookup.getValue(this.lookup.USUARIO_SITUACAO_FUNCIONAL, u.situacao_funcional) || u.situacao_funcional || '',
          'Situação Cadastral': this.lookup.getValue(this.lookup.USUARIO_SITUACAO_SIAPE, u.situacao_siape) || u.situacao_siape || '',
          'Teletrabalho / Programa': (u.participa_pgd || 'não').toUpperCase() === 'SIM' ? 'Sim' : 'Não',
          'Modalidade': u.modalidade_pgd_label || u.modalidade_pgd || '',
          'Tipo de Vínculo': u.usuario_externo ? 'Externo' : 'Interno'
        }));
        this.xlsx.exportJSON('agentes_publicos_pgema', dadosExcel);
        subscriber.next({ success: true });
        subscriber.complete();
      } else {
        const options = Object.assign({}, queryOptions, { limit: 5000 });
        this.dao?.query(options).asPromise().then((queryRows: any) => {
          const dados = (queryRows || []).map((u: any) => ({
            'Matrícula': u.matricula || '',
            'CPF': u.cpf || '',
            'Nome': u.nome_exibicao || u.nome || '',
            'Nome Social': u.nome_social || '',
            'E-mail': u.email || '',
            'Telefone': u.telefone || '',
            'Perfil': u.perfil?.nome || '',
            'Vínculo Funcional': this.lookup.getValue(this.lookup.USUARIO_SITUACAO_FUNCIONAL, u.situacao_funcional) || u.situacao_funcional || '',
            'Situação Cadastral': this.lookup.getValue(this.lookup.USUARIO_SITUACAO_SIAPE, u.situacao_siape) || u.situacao_siape || '',
            'Teletrabalho / Programa': (u.participa_pgd || 'não').toUpperCase() === 'SIM' ? 'Sim' : 'Não',
            'Modalidade': u.modalidade_pgd_label || u.modalidade_pgd || '',
            'Tipo de Vínculo': u.usuario_externo ? 'Externo' : 'Interno'
          }));
          this.xlsx.exportJSON('agentes_publicos_pgema', dados);
          subscriber.next({ success: true });
          subscriber.complete();
        }).catch((err: any) => {
          subscriber.error(err);
        });
      }
    });
  };

  public async imprimirDossie(usuario: Usuario) {
    try {
      const dossieData = await (this.dao as any).getDossie(usuario.id);
      this.dossieService.abrirDossieEmNovaAba(dossieData || usuario);
    } catch (e) {
      this.dossieService.abrirDossieEmNovaAba(usuario);
    }
  }

  public async redefinirSenha(usuario: Usuario) {
    const novaSenha = await this.dialog.prompt('Redefinir Senha', `Digite a nova senha para o usuário ${usuario.nome}:`, '');
    if (novaSenha && novaSenha.trim().length >= 4) {
      try {
        await (this.dao as any).resetPassword(usuario.id, novaSenha.trim());
        this.dialog.alert('Sucesso', 'Senha redefinida com sucesso!');
      } catch (err: any) {
        this.dialog.alert('Erro', err?.error || 'Erro ao redefinir senha.');
      }
    } else if (novaSenha !== null && novaSenha !== undefined) {
      this.dialog.alert('Aviso', 'A senha deve conter ao menos 4 caracteres.');
    }
  }

}
