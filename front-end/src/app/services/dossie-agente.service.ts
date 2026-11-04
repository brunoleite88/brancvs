import { Injectable, Injector } from '@angular/core';
import { LookupService } from './lookup.service';
import { ModalidadePgdService } from './modalidade-pgd.service';

@Injectable({
  providedIn: 'root'
})
export class DossieAgenteService {
  private lookup: LookupService;
  private modalidadePgd: ModalidadePgdService;

  constructor(injector: Injector) {
    this.lookup = injector.get<LookupService>(LookupService);
    this.modalidadePgd = injector.get<ModalidadePgdService>(ModalidadePgdService);
  }

  public gerarHtmlDossie(u: any): string {
    const dataEmissao = new Date().toLocaleString('pt-BR');
    const situacaoFuncionalLabel = this.lookup.getValue(this.lookup.USUARIO_SITUACAO_FUNCIONAL, u.situacao_funcional) || u.situacao_funcional || 'Não informada';
    const situacaoCadastralLabel = this.lookup.getValue(this.lookup.USUARIO_SITUACAO_SIAPE, u.situacao_siape) || u.situacao_siape || 'Ativo';
    const modalidadeLabel = this.modalidadePgd.label(u.modalidade_pgd) || 'Não definida';
    const participaPgdLabel = (u.participa_pgd || 'não').toUpperCase() === 'SIM' ? 'Sim' : 'Não';
    const vinculoTipo = u.usuario_externo ? 'Usuário Externo' : 'Servidor Efetivo / Interno';
    const perfilNome = u.perfil?.nome || u.perfil_nome || 'Não definido';

    // Unidades e atribuições
    let unidadesHtml = '';
    const integrantes = u.unidades_integrantes || u.unidadesIntegrantes || u.unidades_vinculadas || [];
    if (integrantes.length > 0) {
      unidadesHtml = `
        <table>
          <thead>
            <tr>
              <th>Unidade</th>
              <th>Sigla</th>
              <th>Atribuições</th>
            </tr>
          </thead>
          <tbody>
            ${integrantes.map((ui: any) => {
              const sigla = ui.unidade?.sigla || ui.sigla || '-';
              const nomeUnidade = ui.unidade?.nome || ui.nome || '-';
              const atribuicoes = (ui.atribuicoes || []).map((a: any) => typeof a === 'string' ? a : (a.atribuicao || '')).join(', ') || 'Nenhuma';
              return `<tr>
                <td><strong>${nomeUnidade}</strong></td>
                <td>${sigla}</td>
                <td>${atribuicoes}</td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      `;
    } else {
      unidadesHtml = '<p style="color: #64748b; font-style: italic;">Nenhuma unidade vinculada registrada.</p>';
    }

    // Regramentos
    const regramentos = u.regramentos || [];
    const regramentosHtml = regramentos.length > 0
      ? regramentos.map((r: any) => `<span class="badge badge-perfil" style="margin-right: 6px;">${typeof r === 'string' ? r : (r.nome || '')}</span>`).join('')
      : '<span style="color: #64748b;">Nenhum regramento específico</span>';

    // Planos de Trabalho recentes
    let planosHtml = '';
    const planos = u.planos_trabalho || u.planosTrabalho || [];
    if (planos.length > 0) {
      planosHtml = `
        <table>
          <thead>
            <tr>
              <th>Cód / Descrição</th>
              <th>Período</th>
              <th>Status</th>
              <th>Modalidade</th>
            </tr>
          </thead>
          <tbody>
            ${planos.map((p: any) => {
              const inicio = p.data_inicio ? new Date(p.data_inicio).toLocaleDateString('pt-BR') : '-';
              const fim = p.data_fim ? new Date(p.data_fim).toLocaleDateString('pt-BR') : '-';
              return `<tr>
                <td>${p.numero || p.id || '-'}</td>
                <td>${inicio} a ${fim}</td>
                <td><span class="badge ${p.status === 'ATIVO' ? 'badge-ativo' : 'badge-perfil'}">${p.status || '-'}</span></td>
                <td>${this.modalidadePgd.label(p.modalidade_pgd)}</td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      `;
    } else {
      planosHtml = '<p style="color: #64748b; font-style: italic;">Nenhum plano de trabalho registrado.</p>';
    }

    return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Dossiê Funcional - PGE-MA - ${u.nome}</title>
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; color: #0f172a; margin: 30px; font-size: 13px; line-height: 1.5; background: #fff; }
    .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 24px; }
    .header .instituicao { font-size: 16px; font-weight: bold; color: #0f172a; letter-spacing: 0.5px; text-transform: uppercase; }
    .header .orgao { font-size: 14px; font-weight: 600; color: #334155; margin-top: 2px; }
    .header .titulo { font-size: 15px; font-weight: bold; color: #1e40af; margin-top: 6px; text-transform: uppercase; letter-spacing: 1px; }
    .header .subtitulo { font-size: 11px; color: #64748b; margin-top: 4px; }
    
    .section { margin-bottom: 20px; page-break-inside: avoid; }
    .section-title { font-size: 12px; font-weight: bold; background-color: #f1f5f9; padding: 6px 12px; border-left: 4px solid #1e40af; text-transform: uppercase; color: #0f172a; margin-bottom: 10px; }
    
    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px 16px; }
    .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 16px; }
    .field { display: flex; flex-direction: column; }
    .field-label { font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; }
    .field-value { font-size: 13px; color: #0f172a; font-weight: 500; margin-top: 2px; word-break: break-word; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { border: 1px solid #cbd5e1; padding: 6px 10px; text-align: left; font-size: 12px; }
    th { background-color: #f8fafc; font-weight: 600; color: #334155; }
    
    .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
    .badge-ativo { background-color: #dcfce7; color: #166534; }
    .badge-inativo { background-color: #fee2e2; color: #991b1b; }
    .badge-perfil { background-color: #e0f2fe; color: #0369a1; }
    
    .box-texto { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; font-size: 12px; white-space: pre-wrap; color: #334155; }
    
    .footer { margin-top: 40px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    .no-print { margin-bottom: 20px; display: flex; justify-content: flex-end; gap: 10px; }
    .btn-print { background-color: #1e40af; color: white; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; }
    .btn-print:hover { background-color: #1d4ed8; }
    
    @media print {
      .no-print { display: none !important; }
      body { margin: 15mm; }
    }
  </style>
</head>
<body>
  <div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Salvar em PDF</button>
  </div>

  <div class="header">
    <div class="instituicao">ESTADO DO MARANHÃO</div>
    <div class="orgao">PROCURADORIA GERAL DO ESTADO DO MARANHÃO — PGE-MA</div>
    <div class="titulo">Dossiê Funcional do Agente Público</div>
    <div class="subtitulo">Documento gerado em ${dataEmissao} via Sistema PETRVS PGE-MA</div>
  </div>

  <div class="section">
    <div class="section-title">1. Dados Pessoais e Identificação</div>
    <div class="grid">
      <div class="field">
        <span class="field-label">Nome Civil</span>
        <span class="field-value">${u.nome || '-'}</span>
      </div>
      <div class="field">
        <span class="field-label">Nome Social</span>
        <span class="field-value">${u.nome_social || 'Não cadastrado'}</span>
      </div>
      <div class="field">
        <span class="field-label">CPF</span>
        <span class="field-value">${u.cpf || '-'}</span>
      </div>
      <div class="field">
        <span class="field-label">E-mail</span>
        <span class="field-value">${u.email || '-'}</span>
      </div>
      <div class="field">
        <span class="field-label">Telefone</span>
        <span class="field-value">${u.telefone || 'Não informado'}</span>
      </div>
      <div class="field">
        <span class="field-label">Data de Nascimento</span>
        <span class="field-value">${u.data_nascimento ? new Date(u.data_nascimento).toLocaleDateString('pt-BR') : 'Não informada'}</span>
      </div>
      <div class="field">
        <span class="field-label">UF</span>
        <span class="field-value">${u.uf || '-'}</span>
      </div>
      <div class="field">
        <span class="field-label">Tipo de Cadastro</span>
        <span class="field-value"><span class="badge ${u.usuario_externo ? 'badge-inativo' : 'badge-ativo'}">${vinculoTipo}</span></span>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-title">2. Situação Funcional e Regime de Trabalho</div>
    <div class="grid">
      <div class="field">
        <span class="field-label">Matrícula Funcional</span>
        <span class="field-value"><strong>${u.matricula || 'Não definida'}</strong></span>
      </div>
      <div class="field">
        <span class="field-label">Situação Funcional</span>
        <span class="field-value">${situacaoFuncionalLabel}</span>
      </div>
      <div class="field">
        <span class="field-label">Situação Cadastral</span>
        <span class="field-value"><span class="badge ${u.situacao_siape === 'INATIVO' ? 'badge-inativo' : 'badge-ativo'}">${situacaoCadastralLabel}</span></span>
      </div>
      <div class="field">
        <span class="field-label">Teletrabalho / Programa</span>
        <span class="field-value">${participaPgdLabel}</span>
      </div>
      <div class="field">
        <span class="field-label">Modalidade de Trabalho</span>
        <span class="field-value">${modalidadeLabel}</span>
      </div>
      <div class="field">
        <span class="field-label">Nível de Acesso (Perfil)</span>
        <span class="field-value"><span class="badge badge-perfil">${perfilNome}</span></span>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-title">3. Lotação, Unidades Integrantes e Atribuições</div>
    ${unidadesHtml}
  </div>

  <div class="section">
    <div class="section-title">4. Regramentos Aplicáveis</div>
    <div style="margin-top: 6px;">${regramentosHtml}</div>
  </div>

  ${u.texto_complementar_plano ? `
  <div class="section">
    <div class="section-title">5. Particularidades e Observações do Agente</div>
    <div class="box-texto">${u.texto_complementar_plano}</div>
  </div>
  ` : ''}

  <div class="section">
    <div class="section-title">6. Histórico de Planos de Ação / Trabalho</div>
    ${planosHtml}
  </div>

  <div class="footer">
    Procuradoria Geral do Estado do Maranhão — Sistema de Gestão Estratégica PETRVS PGE-MA<br>
    Este dossiê reflete a situação cadastral do agente público na data de emissão.
  </div>
</body>
</html>`;
  }

  public abrirDossieEmNovaAba(usuario: any): void {
    const html = this.gerarHtmlDossie(usuario);
    const win = window.open('', '_blank');
    if (win) {
      win.document.open();
      win.document.write(html);
      win.document.close();
    }
  }
}
