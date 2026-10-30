import { Base } from './base.model';

export type ProjetoStatus = 'PLANEJADO' | 'INICIADO' | 'CONCLUIDO' | 'SUSPENSO' | 'CANCELADO';

export class Projeto extends Base {
    public numero: number = 0;
    public nome: string = "";
    public descricao: string = "";
    public finalidade: string = "";
    public status: ProjetoStatus = 'PLANEJADO';
    public data_inicio: Date = new Date();
    public data_fim: Date = new Date();
    public duracao: number = 0;
    public progresso: number = 0;
    public custo: number = 0;

    public constructor(data?: any) { 
        super(); 
        this.initialization(data); 
    }
}
