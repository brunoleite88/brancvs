export type TipoMotivoAfastamento = {
  id: string;
  nome: string;
  sigla: string;
  horas: number;
};

export type Ocorrencia = {
  id: string;
  usuario_id: string;
  usuario?: { id: string, nome: string };
  observacoes: string | null;
  data_inicio: string;
  data_fim: string;
  horas: number | null;
  tipo_motivo_afastamento_id: string;
  tipo_motivo_afastamento?: TipoMotivoAfastamento;
  created_at?: string;
};

export type OcorrenciaFormValue = {
  usuario_id: string;
  observacoes: string;
  data_inicio: string;
  data_fim: string;
  tipo_motivo_afastamento_id: string;
  horas: string;
};

export type ImpactoConsolidacoes = {
  operacao_bloqueada: boolean;
  gera_dispensa: boolean;
  remove_dispensa: boolean;
};

export type OcorrenciaProjetoPlano = {
  id: string;
  tipo: 'PROJETO' | 'PLANO_SETORIAL' | 'PLANO_INDIVIDUAL' | 'GERAL';
  titulo: string;
  motivo?: string;
  descricao: string;
  impacto?: string;
  novo_prazo_proposto?: string;
  acao_mitigatoria?: string;
  status: 'PENDENTE' | 'EM_ANDAMENTO' | 'RESOLVIDO' | 'CANCELADO';
  projeto_id?: string;
  projeto?: { id: string; nome: string };
  plano_entrega_id?: string;
  plano_entrega?: { id: string; nome: string };
  plano_trabalho_id?: string;
  usuario_id: string;
  usuario?: { id: string; nome: string; email: string };
  data_inicio: string;
  data_fim: string;
  created_at?: string;
};
