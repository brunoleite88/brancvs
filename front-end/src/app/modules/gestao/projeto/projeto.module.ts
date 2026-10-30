import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SharedModule } from 'src/app/shared/shared.module';
import { ReactiveFormsModule } from '@angular/forms';
import { ProjetoRoutingModule } from './projeto-routing.module';
import { ProjetoListComponent } from './projeto-list/projeto-list.component';
import { ProjetoFormComponent } from './projeto-form/projeto-form.component';

@NgModule({
  declarations: [
    ProjetoListComponent,
    ProjetoFormComponent
  ],
  imports: [
    CommonModule,
    SharedModule,
    ReactiveFormsModule,
    ProjetoRoutingModule
  ]
})
export class ProjetoModule { }
