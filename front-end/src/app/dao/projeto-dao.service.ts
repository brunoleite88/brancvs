import { Injectable, Injector } from '@angular/core';
import { Projeto } from '../models/projeto.model';
import { DaoBaseService } from './dao-base.service';

@Injectable({
  providedIn: 'root'
})
export class ProjetoDaoService extends DaoBaseService<Projeto> {

  constructor(protected injector: Injector) { 
    super("Projeto", injector);
    this.inputSearchConfig.searchFields = ["nome", "descricao", "finalidade"];
  }  
}
