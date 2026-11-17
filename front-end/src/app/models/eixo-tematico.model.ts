import { Base } from './base.model';

export interface EixoPlanejamentoResumo {
    id: string;
    nome: string;
}

export interface EixoKrResumo {
    id: string;
    nome: string;
    qtd_entregas: number;
    qtd_unidades: number;
}

export interface EixoObjetivoResumo {
    id: string;
    nome: string;
    planejamento_nome: string;
    qtd_krs: number;
    qtd_entregas: number;
    qtd_unidades: number;
    krs: EixoKrResumo[];
}

export class EixoTematico extends Base {
    public nome: string = ""; //Nome do eixo temático;
    public icone: string = ""; /* Classe do icone relacionado ao eixo temático */
    public cor: string = ""; /* Código da cor em hex */
    public descricao: string = ""; /* Descrição do eixo temático */

    /* Métricas agregadas somente leitura retornadas pelo proxyRows do back-end */
    public planejamentos?: EixoPlanejamentoResumo[];
    public qtd_objetivos?: number;
    public qtd_krs?: number;
    public qtd_entregas?: number;
    public qtd_unidades?: number;
    public objetivos_detalhes?: EixoObjetivoResumo[];

    public constructor(data?: any) { super(); this.initialization(data); }
}