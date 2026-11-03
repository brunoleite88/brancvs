# MANUAL DO USUÁRIO ILUSTRADO
## SISTEMA DE GESTÃO ESTRATÉGICA – PGE-MA
### Guia Prático de Operação para o Gabinete, Gestores (Chefias), Integrantes e Administradores

---

## SUMÁRIO

1. [INTRODUÇÃO E HISTÓRICO DE DESENVOLVIMENTO](#1-introdução-e-histórico-de-desenvolvimento)
   - 1.1. Origem Tecnológica e Licença de Código Aberto (MIT)
   - 1.2. Customização e Modernização para a PGE-MA
2. [APRESENTAÇÃO E CONTROLE DE ACESSO](#2-apresentação-e-controle-de-acesso)
   - 2.1. Níveis de Acesso e Perfis Institucionais
   - 2.2. Acesso ao Sistema e Autenticação
3. [TELA INICIAL (HOME) E NAVEGAÇÃO](#3-tela-inicial-home-e-navegação)
   - 3.1. Visão Geral da Interface
   - 3.2. Seletor de Unidades e Chave "Subordinadas"
   - 3.3. Cards de Resumo e Métricas Rápidas
   - 3.4. Atalho Estratégico para o Cockpit do Gabinete
   - 3.5. Card Dinâmico "Agenda de Marcos e Prazos"
4. [MÓDULO DE PLANEJAMENTO](#4-módulo-de-planejamento)
   - 4.1. Planejamentos Institucionais (Ciclo 2026–2030)
   - 4.2. Regramentos Instituidores e Programas Contínuos
   - 4.3. Unidades Organizacionais (42 Setores)
   - 4.4. Agentes Públicos e Atribuições Funcionais
   - 4.5. Eixos Temáticos e Elementos Estratégicos
5. [MÓDULO DE PLANOS DE AÇÃO SETORIAIS](#5-módulo-de-planos-de-ação-setoriais)
   - 5.1. Estrutura e Vinculação Estratégica das Ações
   - 5.2. Guia Prático para a Chefia Imediata
   - 5.3. Cadastro de Entregas e Ações com Layout Responsivo
   - 5.4. Registro de Execução e Atualização Periódica
6. [MÓDULO DA CORREGEDORIA (MEU RELATÓRIO)](#6-módulo-da-corregedoria-meu-relatório)
   - 6.1. Finalidade e Obrigatoriedade da Prestação de Contas
   - 6.2. Guia para o Procurador (Preenchimento Mensal)
   - 6.3. Guia para a Chefia Imediata (Homologação Mensal)
7. [MÓDULO DE PAINÉIS GERENCIAIS (HUB CENTRAL)](#7-módulo-de-painéis-gerenciais-hub-central)
   - 7.1. Hub de Painéis Gerenciais
   - 7.2. Painel 1: Metas e Entregas (`/metas-entregas`)
   - 7.3. Painel 2: Conformidade (`/conformidade`)
   - 7.4. Painel 3: Cockpit Executivo do Gabinete & Alta Gestão (`/cockpit-gabinete`)
   - 7.5. Painel 4: Projetos Estruturantes da PGE-MA (`/projetos`)
   - 7.6. Painel 5: Workflow de Repactuação e Monitoramento Periódico (`/repactuacoes`)
   - 7.7. Preferências do Usuário e Customização da Interface
8. [INTEGRAÇÃO COM PROCESSOS SEI-MA & EMISSÃO DE DESPACHO OFICIAL EM PDF](#8-integração-com-processos-sei-ma--emissão-de-despacho-oficial-em-pdf)
   - 8.1. Rastreabilidade Processual nos Projetos e Repactuações
   - 8.2. Vínculo de Número de Processo Administrativo SEI-MA
   - 8.3. Geração Automática de Minuta de Despacho Oficial em PDF
9. [MÓDULO DESENVOLVEDOR & AUDITORIA TÉCNICA](#9-módulo-desenvolvedor--auditoria-técnica)
   - 9.1. Acesso Restrito e Governança de TIC (`MOD_DEV`)
   - 9.2. Consulta de Logs de Erros do Sistema
   - 9.3. Logs de Alterações e Auditoria de Dados
   - 9.4. Gestão de Rotinas de Integração em Segundo Plano
   - 9.5. Simulação de Usuário (*Impersonate*) para Diagnóstico e Suporte
10. [CONFIGURAÇÕES, NÍVEIS DE ACESSO (PERFIS) & GESTÃO DE CAPACIDADES](#10-configurações-níveis-de-acesso-perfis--gestão-de-capacidades)
    - 10.1. Acesso ao Menu "Configurações"
    - 10.2. Gestão de Níveis de Acesso (Perfis de Segurança RBAC)
    - 10.3. Árvore de Permissões e Catálogo das 14 Novas Capacidades de Governança
    - 10.4. Matriz de Atribuição Recomendada por Perfil
11. [MATRIZ DE ROTINAS, GOVERNANÇA E RESPONSABILIDADES](#11-matriz-de-rotinas-governança-e-responsabilidades)
12. [SUPORTE, CONTATOS E CANAIS INSTITUCIONAIS](#12-suporte-contatos-e-canais-institucionais)

---

## 1. INTRODUÇÃO E HISTÓRICO DE DESENVOLVIMENTO

### 1.1. Origem Tecnológica e Licença de Código Aberto (MIT)
O **Sistema de Gestão Estratégica da Procuradoria Geral do Estado do Maranhão (PGE-MA)** tem sua infraestrutura de software baseada no projeto de código aberto **Petrvs**, originalmente concebido pelo **Ministério da Gestão e da Inovação em Serviços Públicos (MGI)** do Governo Federal.

Disponibilizado sob a **Licença MIT** (*Massachusetts Institute of Technology*), o código original assegura a liberdade plena para estudo, refatoração, aprimoramento institucional contínuo e desenvolvimento soberano por órgãos e entidades da administração pública brasileira.

### 1.2. Customização e Modernização para a PGE-MA
Embora o núcleo técnico de microsserviços e multitenancy do Petrvs tenha servido de alicerce, o sistema original encontrava-se rigidamente acoplado às regras do Programa de Gestão e Desempenho (PGD) da administração federal direta (Decreto Federal nº 11.072/2022 e INs nº 65/2020 e nº 24/2023), incompatíveis com o regime jurídico e a autonomia da advocacia pública estadual.

A PGE-MA empreendeu um profundo ciclo de modernização técnica e institucional:

1. **Expurgo de Resíduos Normativos Federais e Legados:**
   - Remoção de vínculos obrigatórios com tabelas e webservices federais (SIAPE, conectores federais e IN 24/2023);
   - Eliminação de conceitos restritivos de controle horário ou teletrabalho com regras federais de afastamento do país, respeitando a Lei Orgânica da PGE-MA e as prerrogativas dos Procuradores do Estado;
   - Unificação de terminologias na interface institucional através de mapeamento léxico corporativo:
     * *Planos de Entrega* $\rightarrow$ **Planos de Ação Setoriais**
     * *Planos de Trabalho* $\rightarrow$ **Planos de Ação Individuais**
     * *Atividades* $\rightarrow$ **Registros de Execução**
     * *Entidades* $\rightarrow$ **Instituições**
     * *Usuários* $\rightarrow$ **Agentes Públicos**
     * *Perfis* $\rightarrow$ **Níveis De Acesso**

2. **Aderência Integral ao Planejamento Estratégico PGE-MA (Ciclo 2026–2030):**
   - Estruturação dos 4 grandes Eixos Temáticos e Resultados-Chave (KRs) aprovados pelo Gabinete;
   - Vinculação obrigatória das ações das 42 unidades organizacionais aos Objetivos Institucionais.

3. **Governança dos 10 Projetos Estruturantes (Grupos de Trabalho – GTs):**
   - Criação de módulo dedicado para monitoramento executivo dos 10 GTs prioritários (como a *Força-Tarefa Profisco III / BID*, *Saneamento de RPVs e Precatórios*, *Integração PJe-SPA Delay Zero*, *Comitê Fiscal PDA/SEFAZ* e *Regimento Interno Único*).

4. **Workflow de Repactuação e Governança Periódica:**
   - Formalização de fluxo estruturado para revisão tempestiva de metas, cronogramas e escopos, com parecer fundamentado da Procuradoria Adjunta de Assuntos Estratégicos (PGAAE) e homologação do Gabinete.

5. **Integração Nativa com o SEI-MA & Despacho em PDF:**
   - Rastreabilidade de processos administrativos estaduais vinculados e geração automática de minutas de termos oficiais e despachos homologatórios em PDF com o Brasão do Maranhão.

6. **Prestação de Contas Integrada com a Corregedoria-Geral:**
   - Módulo mensal para quantitativos e registros qualitativos da atuação contenciosa e consultiva dos Procuradores com fluxo de homologação pelas chefias imediatas.

---

## 2. APRESENTAÇÃO E CONTROLE DE ACESSO

### 2.1. Níveis de Acesso e Perfis Institucionais
O acesso ao sistema é estritamente controlado pelo modelo de **Controle de Acesso Baseado em Papéis (RBAC)**, com permissões granulares por unidade de lotação:

* **Gabinete & Alta Gestão:** Acesso integral ao Cockpit Executivo, homologação final de repactuações, painel de riscos e aprovação do macroplanejamento institucional.
* **Procurador-Chefe / Diretor / Gestor Setorial:** Gestão do Plano de Ação Setorial da sua Especializada ou Regional, distribuição de entregas, solicitação formal de repactuação e homologação dos relatórios mensais da Corregedoria.
* **Procurador / Assessor / Integrante:** Atualização do andamento das suas ações, registro de entregas e preenchimento mensal do *Meu Relatório* da Corregedoria.
* **Administrador / Suporte de TIC:** Gerenciamento de Níveis de Acesso, parametrização de instituições, unidades, agentes públicos e diagnóstico via Módulo Desenvolvedor.

### 2.2. Acesso ao Sistema e Autenticação
O acesso é realizado via navegador web através do endereço institucional disponibilizado pela Assessoria de TIC da PGE-MA.

1. Insira seu e-mail institucional ou identificador funcional;
2. Digite sua senha pessoal de acesso;
3. Clique em **"Entrar"**.

![Tela de Acesso e Login do Sistema PGE-MA](prints/01_tela_login.png)

> [!NOTE]
> O sistema adota sessão autenticada com tokens seguros JWT. Caso seu perfil possua atribuições em mais de uma unidade organizacional, você poderá alternar entre elas a qualquer momento através do seletor no cabeçalho superior.

---

## 3. TELA INICIAL (HOME) E NAVEGAÇÃO

### 3.1. Visão Geral da Interface
Ao efetuar login, o sistema apresenta a tela principal de trabalho configurada de acordo com a unidade de lotação ativa:

![Tela Inicial - Destaques da Unidade e Agenda de Marcos e Prazos](prints/02_home_destaques.png)

A interface padrão é composta por:
* **Barra de Navegação Superior:** Logotipo institucional da PGE-MA, seletor de unidade ativa, chave de visão subordinada, atalho do Cockpit e menu do usuário.
* **Menu Lateral Retrátil (Drawer):** Acesso rápido aos módulos de *Gestão*, *Planejamento*, *Planos de Ação Setoriais*, *Corregedoria*, *Painéis Gerenciais*, *Configurações* e *Desenvolvedor*.
* **Área Central de Trabalho:** Indicadores sintéticos da unidade e centro de alertas operacionais.

### 3.2. Seletor de Unidades e Chave "Subordinadas"
* **Seletor de Unidade:** Permite alternar entre a visão institucional da Sede (`PGE-MA`) e qualquer Procuradoria Especializada (ex.: `PDA`, `PERJP`, `PMA`) ou Regional (ex.: `PRI - Imperatriz`, `PRC - Caxias`).
* **Chave "Subordinadas":**
  * Quando **ativada** (padrão na Sede), agrega os números, ações e agentes públicos de todas as 42 unidades vinculadas na árvore hierárquica.
  * Quando **desativada**, restringe a visualização estritamente aos membros e projetos alocados no próprio setor imediato.

### 3.3. Cards de Resumo e Métricas Rápidas
No topo da página inicial, três blocos fornecem uma visão instantânea da situação funcional:
1. **Planos de Ação Vigentes:** Indica se a unidade selecionada e seus órgãos subordinados possuem plano setorial formalmente aprovado e em execução no ciclo anual.
2. **Membros Regulares:** Exibe o número de integrantes cadastrados na unidade com situação de trabalho regularizada.
3. **Métricas Rápidas:** Apresenta o consolidado de **42 setores executores**, **13 agentes públicos cadastrados** e **1.174 ações estratégicas cadastradas** no banco de dados da instituição.

### 3.4. Atalho Estratégico para o Cockpit do Gabinete
Para agilizar o acesso dos gestores e da cúpula institucional aos indicadores consolidados de desempenho, a tela inicial conta com um atalho de acesso direto ao **Cockpit Executivo do Gabinete**:

![Tela Inicial com Atalho Estratégico para o Cockpit do Gabinete](prints/44_home_com_atalho_cockpit.png)

Ao clicar no botão azul **"Cockpit do Gabinete"**, o gestor é encaminhado instantaneamente ao painel executivo com velocímetros estratégicos, saúde financeira e metas dos 10 GTs.

### 3.5. Card Dinâmico "Agenda de Marcos e Prazos"
Localizado na porção central da tela inicial, a **Agenda de Marcos e Prazos** atua como uma central de monitoramento preventivo:

* **Filtros Rápidos:**
  * **Todos:** Lista cronológica de todos os marcos previstos para o setor;
  * **Atenção:** Destaca compromissos vencidos ou com vencimento nos próximos 30 a 60 dias;
  * **Próximos:** Apresenta entregas e relatórios planejados para o médio prazo.
* **Sinalização Visual por Cores:**
  * 🔴 **Vencido (Vermelho):** Prazos expirados (relatórios pendentes ou entregas atrasadas) que demandam saneamento prioritário;
  * 🟡 **A Vencer / Atenção (Amarelo):** Relatórios em elaboração ou entregas com prazo iminente;
  * 🔵 **Planejado (Azul):** Ações e compromissos com cronograma regular em andamento.
* **Ação com 1 Clique:** O botão **"Acessar"** direciona o usuário imediatamente para a tela de preenchimento ou homologação do respectivo item (*Meu Relatório*, *Planos Setoriais* ou *Projetos Estruturantes*).

---

## 4. MÓDULO DE PLANEJAMENTO

Acessado pelo menu lateral em **Gestão > Planejamento** (ou através de **Configurações** para unidades e usuários), reúne os alicerces estruturais, estratégicos e organizacionais da PGE-MA.

![Menu do Módulo de Planejamento da PGE-MA](prints/03_planejamento_menu.png)

---

### 4.1. Planejamentos Institucionais (Ciclo 2026–2030)
Define a visão de futuro, a missão institucional, a matriz SWOT/FOFA, os eixos temáticos e os objetivos estratégicos da advocacia pública estadual para o ciclo plurianual da PGE-MA.

#### 4.1.1. Visão em Lista dos Planejamentos Institucionais
Ao acessar **Gestão > Planejamento**, o sistema apresenta a grade com os planejamentos cadastrados:

![Lista de Planejamentos Institucionais](prints/09_planejamento_institucional_lista.png)

Na listagem, o gestor visualiza:
* **Nome:** *Plano Estratégico Institucional PGE-MA (Ciclo 2026–2030)*;
* **Período de Vigência:** Data de início (`01/01/2027`) e término planejado (`31/12/2030`);
* **Unidade Responsável:** *Procuradoria Geral do Estado do Maranhão (Sede)*;
* **Contador de Objetivos Vinculados:** Badge indicando a existência de **88 objetivos e metas (KRs)** cadastrados;
* **Ações:** Botão de edição direta (ícone de lápis) e menu de opções com acesso à consulta de informações e trilha de auditoria.

#### 4.1.2. Formulário de Planejamento Institucional (Aba Dados)
Ao clicar no botão de edição ou em um planejamento, o sistema abre o formulário completo de parametrização:

![Formulário de Planejamento Institucional - Aba Dados](prints/10_planejamento_institucional_form.png)

Campos estruturantes da aba **Dados**:
* **Nome do Planejamento:** Identificação oficial do ciclo estratégico;
* **Unidade Gestora:** Vínculo institucional (`PGE-001 - Sede`);
* **Janela Temporal:** Datas formais de início e conclusão;
* **Missão Institucional:** *"Promover a defesa judicial e a consultoria jurídica do Estado do Maranhão, orientando a legalidade dos atos administrativos e defendendo o interesse público com integridade e eficiência."*
* **Visão de Futuro:** *"Ser uma instituição de excelência e referência nacional em advocacia pública, inovação e sustentabilidade institucional até 2030."*
* **Resultados Institucionais e Valores:** Diretrizes éticas e compromissos públicos que norteiam a atuação funcional de todos os membros e servidores.

#### 4.1.3. Matriz de Diagnóstico Estratégico (Aba Análise SWOT / FOFA)
Na aba **"Análise SWOT / FOFA"**, o planejamento consolida o diagnóstico estratégico de ambiente interno e externo da PGE-MA:

![Matriz de Análise SWOT / FOFA do Planejamento Institucional](prints/10b_planejamento_institucional_swot.png)

Os quatro quadrantes compreendem:
1. **Forças (Ambiente Interno - Verde):** Corpo funcional altamente qualificado de Procuradores do Estado e assessores jurídicos; estrutura especializada por matéria (Fiscal, Judicial, Administrativo, Patrimônio e Meio Ambiente); atuação proativa na recuperação da Dívida Ativa; e Escola Superior da Advocacia Pública (ESAP) estruturada com Plano Anual de Capacitação (PAC).
2. **Oportunidades (Ambiente Externo - Azul):** Modernização tecnológica e fortalecimento institucional via financiamento BID / Profisco III (10 artefatos estruturantes); integração de dados em tempo real com SEFAZ, TJMA e TCE-MA; e adoção de Inteligência Artificial generativa e jurimetria (Projeto MinutIA) para saneamento de demandas repetitivas.
3. **Fraquezas (Ambiente Interno - Amarelo):** Estoque histórico de precatórios e requisições de pequeno valor (RPVs) demandando saneamento e compensação; gargalos no fluxo de subsídios instrutórios entre secretarias e a PGE; e necessidade de renovação contínua do parque tecnológico descentralizado.
4. **Ameaças (Ambiente Externo - Vermelho):** Risco de bloqueios judiciais imediatos (SISBAJUD) e pagamentos duplicados de RPVs; e instabilidades de plataformas processuais externas do Poder Judiciário (PJe e portais).

#### 4.1.4. Árvore de Elementos e Objetivos Estratégicos (Aba Elementos)
Na aba **"Elementos"**, o gestor tem acesso à estrutura hierárquica completa dos objetivos e metas vinculados ao plano, categorizados pelos grandes eixos estratégicos:

![Árvore de Elementos e Objetivos Estratégicos por Eixo](prints/10c_planejamento_institucional_elementos.png)

Principais Eixos e Objetivos parametrizados:
* **Eixo Estratégico 1 – Incrementar a arrecadação e otimizar a cobrança da dívida ativa:**
  * *O1:* Reduzir o montante de precatórios e requisições de pequeno valor;
  * *O2:* Aumentar a arrecadação da dívida ativa;
  * *O3:* Aproximar a PGE do contribuinte e incentivar a conformidade fiscal;
  * *O4:* Fortalecer a Identificação e Recuperação de Ativos Fiscais;
  * *O5:* Ampliar a utilização de ferramentas de inteligência fiscal.
* **Eixo Estratégico 2 – Eficiência Administrativa e Redução de Custos Operacionais:**
  * *O1:* Reduzir custos operacionais;
  * *O2:* Otimizar a gestão de compras, contratos e pessoas.
* **Eixo Estratégico 3 – Incrementar e executar integralmente o orçamento institucional:**
  * *O1:* Ampliar fontes de receita e otimizar a execução orçamentária.
* **Eixo Estratégico 4 – Melhorar a experiência do usuário dos serviços da PGE:**
  * *O1:* Qualificar o atendimento aos órgãos estaduais e cidadãos.
* **Eixo Estratégico 5 – Alcançar maior eficiência e resolutividade nos processos internos e externos.**

---

### 4.2. Regramentos Instituidores e Programas Contínuos
Este submódulo institucionaliza e acompanha a conformidade de **Programas Contínuos** transversais da PGE-MA.
* **Exemplo de Aplicação:** Cadastro do *Programa Prazo Zero Perdido*, centralizando as diretrizes de governança de prazos processuais judiciais, matriz de responsabilidades, portarias regulamentares e eventos de capacitação contínua.

---

### 4.3. Unidades Organizacionais (42 Setores)
Permite a manutenção, navegação e consulta da estrutura organizacional de todas as Procuradorias Especializadas, Regionais, Assessorias, Centro de Estudos e Gabinete.

#### 4.3.1. Listagem Geral de Unidades Organizacionais
Localizada em **Configurações > Unidades**, exibe a relação completa das 42 unidades ativas na PGE-MA:

![Lista de Unidades Organizacionais da PGE-MA](prints/11_planejamento_unidades_lista.png)

A tabela apresenta:
* **Sigla:** Identificação da unidade (ex.: `SCCF`, `NIRF`, `PCF`, `PA`, `SGRH`, `SCC`, `PT`, `SGA`, `UGAM`, `PRI`, `PGAAJ`);
* **Nome Institucional:** Denominação completa (ex.: *Procuradoria da Dívida Ativa*, *Procuradoria do Contencioso Fiscal*, *Procuradoria Administrativa*);
* **Código Funcional:** Código padronizado (`PGE-017`, `PGE-012`, `PGE-016`...);
* **Cidade:** Município de instalação (São Luís, Imperatriz, Caxias, etc.);
* **Situação:** Indicadores de regularidade e conformidade funcional.

#### 4.3.2. Formulário de Configuração e Edição de Unidade
Ao selecionar uma unidade para edição (exemplo: `PDA - Procuradoria da Dívida Ativa`), o formulário permite parametrizar seus dados essenciais:

![Formulário de Edição e Configuração de Unidade](prints/12_form_unidade_edicao.png)

Campos disponíveis:
* **Chave Instituidora / Executora:** Define se a unidade pode instituir planejamentos próprios e se executa planos de ação setoriais;
* **Código:** Código oficial no organograma (`PGE-017`);
* **Sigla:** Identificador abreviado (`PDA`);
* **Nome:** Nome por extenso da unidade;
* **Cidade:** Município sede;
* **Unidade Pai (Superior Imediata):** Órgão de subordinação direta na hierarquia (`PGE-001 - Sede`);
* **Instituição:** Vínculo institucional (`PGE-MA`).

---

### 4.4. Agentes Públicos e Atribuições Funcionais
Gerencia o cadastro dos membros (Procuradores do Estado), assessores jurídicos e servidores administrativos, associando-os às suas respectivas lotações e competências funcionais.

#### 4.4.1. Listagem de Agentes Públicos
Acessada em **Configurações > Agentes Públicos**, reúne todos os usuários ativos com filtros por CPF, nome, lotação, nível de acesso e situação cadastral:

![Gestão de Agentes Públicos da PGE-MA](prints/13_planejamento_agentes_publicos.png)

A grade apresenta Matrícula, CPF, Nome completo, Regramento vigente associado, Nível de Acesso (Perfil) e Situação funcional (Ativo/Inativo).

#### 4.4.2. Formulário de Atribuições e Lotação de Agente Público
Ao abrir o cadastro de um agente público e acessar a aba **"Atribuições"**, o gestor define o perfil de acesso e os vínculos de autoridade na unidade:

![Formulário de Atribuições e Lotação de Agente Público](prints/14_form_agente_atribuicoes.png)

Configurações de governança:
* **Nível de Acesso:** Definição do perfil RBAC (ex.: *Administrador Geral*, *Perfil Unidade*, *Perfil Desenvolvedor*);
* **Vínculo de Lotação:** Associação à unidade organizacional (`PGE-001 - Sede`, Especializada ou Regional);
* **Regramento Vinculado:** Associação ao *Programa de Gestão e Ação Estratégica PGE-MA (Ciclo 2026–2030)*;
* **Badges de Atribuição:**
  * **★ Chefe:** Titular da chefia imediata com poderes de homologação e gestão do plano setorial;
  * **Chefe Substituto:** Delegação formal de autoridade na ausência do titular;
  * **Lotado:** Integrante da equipe responsável pelo cumprimento das ações e envio de relatórios correcionais;
  * **Colaborador:** Atuação transversal temporária em comitês ou projetos.

---

### 4.5. Eixos Temáticos e Elementos Estratégicos
Em **Cadastros > Elementos Estratégicos**, o sistema parametriza os tipos de elementos que estruturam o desdobramento do planejamento institucional e da cadeia de valor da PGE-MA:

![Lista de Elementos Estratégicos (Tipos de Objetivos da PGE-MA)](prints/15_elementos_estrategicos_lista.png)

O módulo padroniza:
* **Objetivos Estratégicos:** Grandes metas corporativas plurianuais que expressam os resultados finalísticos desejados;
* **Metas (Key Results / KRs):** Indicadores quantitativos de monitoramento contínuo com metas percentuais e prazos definidos;
* **Elementos da Cadeia de Valor:** Processos finalísticos de suporte e governança da advocacia pública estadual.

---

## 5. MÓDULO DE PLANOS DE AÇÃO SETORIAIS

Ambiente de trabalho descentralizado onde cada Especializada e Regional planeja, executa e monitora suas metas para o ciclo anual.

![Gestão de Planos de Ação Setoriais](prints/16_planos_setoriais_lista.png)

### 5.1. Estrutura e Vinculação Estratégica das Ações
Cada Plano Setorial é composto por um conjunto estruturado de **Ações e Entregas**:
* **Alinhamento Obrigatório:** Nenhuma ação setorial pode ser instituída sem vínculo com ao menos um **Elemento Estratégico (Objetivo)** institucional;
* **Responsável Nominal:** Cada entrega possui um Procurador ou Assessor designado;
* **Parcerias Coexecutoras:** Permite indicar outras Especializadas que atuam em conjunto na iniciativa.

![Planos de Ação Setoriais Povoados com Metas e Indicadores](prints/19_planos_setoriais_povoados.png)

### 5.2. Guia Prático para a Chefia Imediata
1. Navegue até **Gestão > Planos de Ação Setoriais**;
2. Verifique se a sua unidade está selecionada no topo;
3. Para o ciclo vigente, acesse as ações clicando no ícone de lista da linha do plano;
4. Para incluir uma nova iniciativa, clique em **"Adicionar Ação"**;
5. Preencha o título, descrição executiva, data-limite de conclusão e agente público responsável;
6. Clique em **"Salvar"**.

![Detalhamento de Ações do Plano Setorial](prints/20_plano_setorial_acoes_detalhe.png)

### 5.3. Cadastro de Entregas e Ações com Layout Responsivo
O formulário de cadastro e edição de entregas foi projetado para assegurar máxima clareza e precisão:

![Formulário de Cadastro e Edição de Entrega Setorial](prints/26_form_entrega_exato_user.png)

Campos principais:
* **Descrição da Entrega:** Nome claro do produto ou resultado esperado;
* **Elemento Estratégico:** Seleção do Objetivo Institucional vinculado;
* **Data Inicial e Data Final:** Janela temporal de execução;
* **Unidade Demandante e Coexecutoras:** Órgãos envolvidos;
* **Membros Responsáveis:** Agentes designados nominalmente.

### 5.4. Registro de Execução e Atualização Periódica
Recomenda-se a atualização **quinzenal** do avanço físico das ações pelas chefias ou responsáveis designados:
* **Não Iniciado:** Ação aguardando marco de abertura ou liberação de recursos;
* **Em Andamento:** Atividades em execução regular;
* **Concluído:** Entrega concluída e homologada pela unidade.

---

## 6. MÓDULO DA CORREGEDORIA (MEU RELATÓRIO)

Módulo obrigatório destinado à prestação de contas mensal da atuação judicial e consultiva dos Procuradores do Estado.

![Módulo da Corregedoria - Meu Relatório Mensal](prints/08_corregedoria_meu_relatorio.png)

### 6.1. Finalidade e Obrigatoriedade da Prestação de Contas
A tempestividade no envio e na homologação dos relatórios de atividades da Corregedoria é monitorada em tempo real pelo sistema e compõe o **Índice de Conformidade Institucional**.

### 6.2. Guia para o Procurador (Preenchimento Mensal)
1. Acesse **Gestão > Corregedoria > Meu Relatório**;
2. Selecione a **Competência** correspondente (mês/ano de referência);
3. Registre os quantitativos e detalhamentos qualitativos:
   * **Peças Judiciais:** Petições iniciais, contestações, recursos (apelações, agravos, embargos, RE/RESP) e manifestações interlocutórias;
   * **Audiências Realizadas:** Presenciais e telepresenciais acompanhadas;
   * **Sustentações Orais:** Sessões de julgamento nos Tribunais Estaduais e Superiores (TJMA, TRT-16, TRF-1, STJ e STF);
   * **Atividades Consultivas:** Pareceres, notas jurídicas, despachos e análises de minutas de editais ou contratos;
4. Revise os lançamentos e clique em **"Enviar para Homologação da Chefia"** até o **5º dia útil** do mês subsequente.

### 6.3. Guia para a Chefia Imediata (Homologação Mensal)
1. Acesse **Gestão > Corregedoria > Homologação de Relatórios**;
2. Verifique a lista de relatórios submetidos pelos integrantes da sua unidade;
3. Clique sobre o relatório para analisar o extrato das atividades lançadas;
4. Clique em **"Homologar"** para aprovação final ou **"Devolver para Ajustes"** caso seja necessária alguma retificação;
5. A conclusão deve ocorrer até o **10º dia útil** do mês de referência.

---

## 7. MÓDULO DE PAINÉIS GERENCIAIS (HUB CENTRAL)

Disponível no menu lateral em **Gestão > Painéis Gerenciais**, o Hub consolida a inteligência gerencial da PGE-MA através de **5 painéis executivos integrados**:

![Hub Principal de Painéis Gerenciais da PGE-MA](prints/49_paineis_gerenciais_hub_5cards.png)

Cada card direciona com um clique para a dimensão executiva desejada:
1. **Metas e Entregas (`/metas-entregas`)**
2. **Conformidade (`/conformidade`)**
3. **Projetos Estruturantes (`/projetos`)**
4. **Cockpit do Gabinete (`/cockpit-gabinete`)**
5. **Repactuação de Metas (`/repactuacoes`)**

---

### 7.1. Painel 1: Metas e Entregas (`/metas-entregas`)
Oferece análise gráfica comparativa do alinhamento e da velocidade de conclusão das ações institucionais:

![Painel de Metas e Entregas - Alinhamento e Execução Setorial](prints/06_painel_metas_entregas.png)

* **Alinhamento Institucional:** Proporção de ações voltadas aos Objetivos 2026–2030 versus rotinas internas;
* **Execução dos Planos Setoriais:** Proporção de ações concluídas, em andamento e pendentes por unidade;
* **Volume por Categoria:** Detalhamento do volume de entregas e registros de execução da equipe;
* **Exportação:** Geração de relatórios executivos em formato PDF para apresentações e deliberações colegiadas.

---

### 7.2. Painel 2: Conformidade (`/conformidade`)
Monitora a tempestividade e a regularidade cadastral e correcional de todas as 42 unidades:

![Painel de Conformidade - Tempestividade Correcional e Planos Vigentes](prints/07_painel_conformidade.png)

* **Relatórios Mensais Enviados no Prazo:** Percentual de relatórios submetidos até o 5º dia útil;
* **Relatórios Homologados no Prazo:** Percentual de avaliações concluídas pelas chefias até o 10º dia útil;
* **Status de Planos Setoriais:** Unidades com plano regular ativo versus unidades com pendência de abertura;
* **Aderência aos Prazos de Ações:** Sinalização ágil de iniciativas no cronograma regular versus em atraso.

---

### 7.3. Painel 3: Cockpit Executivo do Gabinete & Alta Gestão (`/cockpit-gabinete`)
Ambiente de decisão estratégica em tempo real para o Procurador-Geral, Procuradores-Chefes e Gestores de Cúpula:

![Cockpit Executivo do Gabinete da PGE-MA](prints/42_cockpit_gabinete_page.png)

#### Indicadores Globais no Topo (Cards Executivos):
* **Taxa Global de Entregas (82,4%):** Percentual de entregas institucionais concluídas dentro dos padrões técnicos esperados;
* **Aderência ao Cronograma (78,6%):** Percentual de marcos e ações rigorosamente dentro do prazo pactuado;
* **Execução Orçamentária Estratégica (76,8%):** Recursos alocados e executados nas iniciativas estratégicas (R$ 14,2M executados de R$ 18,5M previstos);
* **Conformidade Correcional (94,2%):** Índice de regularidade dos relatórios mensais homologados pelas chefias.

#### Filtros de Período e Lotação:
* **Período de Análise:** Ano Corrente, 1º Semestre, 2º Semestre ou Visão Quinquenal (Ciclo 2026–2030);
* **Seletor de Lotação:** Permite filtrar a visão global da PGE-MA ou isolar uma Especializada/Regional;
* **Botão "Exportar Relatório Executivo PDF":** Emite relatório consolidado formatado para prestação de contas governamental.

#### Visão por Eixo Estratégico & Tabela Setorial:
* **Gráficos por Eixo:** Desempenho comparativo dos 4 eixos estratégicos;
* **Tabela de Desempenho Setorial:** Lista todas as Especializadas e Regionais com barra de progresso visual, número de ações no prazo, ações em atraso e status (🟢 Normal, 🟡 Atenção, 🔴 Crítico);
* **Painel dos 10 GTs Estruturantes:** Velocímetros individuais de progresso para cada projeto de alta prioridade.

---

### 7.4. Painel 4: Projetos Estruturantes da PGE-MA (`/projetos`)
Acompanhamento dos **10 Grupos de Trabalho (GTs)** criados pelo Gabinete da PGE-MA:

* `[GT-01]` Força-Tarefa Profisco III / BID (10 Artefatos Estruturantes)
* `[GT-02]` Saneamento de RPVs e Precatórios Judiciais (PERJP)
* `[GT-03]` Comitê de Integração Fiscal e Recuperação de Créditos (PDA + NIRF + SEFAZ)
* `[GT-04]` Estabilidade do SPA, Integração PJe-SPA (Delay Zero) e IA (MINUTIA)
* `[GT-05]` Qualificação Recursal e Revezamento de Sustentações Orais
* `[GT-06]` Regimento Interno Único e Governança Descentralizada de Prazos
* `[GT-07]` Rede Institucional de Comunicação de Êxitos Judiciais
* `[GT-08]` Integridade, Transparência Adaptada à LGPD e Prevenção ao Assédio
* `[GT-09]` Integração Digital Descentralizada (Fórum Des. José Sarney)
* `[GT-10]` Comitê Gestor do Plano Anual de Capacitação (PAC / ESAP)

O módulo conta com **três modos de visualização instantâneos**:

#### 1. Modo Cards Executivos:
Apresenta cada projeto em um card detalhado com status de prioridade, líder designado, prazo final, barra de avanço percentual e identificação do processo SEI-MA vinculado:

![Projetos Estruturantes - Visualização em Cards Executivos](prints/45_projetos_painel_cards.png)

Projetos com processos oficiais SEI-MA são identificados por badge e atalho direto:

![Projetos Estruturantes com Identificação de Processos SEI-MA](prints/57_projetos_com_processos_sei.png)

#### 2. Modo Gráfico de Gantt Interativo:
Cronograma temporal contínuo dos 10 projetos estruturantes, permitindo visualizar sobreposição de fases, marcos intermediários e dependências:

![Projetos Estruturantes - Cronograma em Gráfico de Gantt](prints/46_projetos_painel_gantt.png)

#### 3. Modo Matriz de Riscos (Probabilidade x Impacto 3x3):
Mapeamento visual da governança que posiciona cada projeto de acordo com a severidade dos riscos identificados (Baixo, Médio ou Alto Risco):

![Projetos Estruturantes - Matriz de Riscos 3x3](prints/47_projetos_painel_matriz.png)

#### Modal de Detalhamento Completo do Projeto:
Ao clicar no botão **"Detalhes"** de qualquer projeto, abre-se uma janela com:
* Resumo executivo e justificativa;
* Resultados-Chave (KRs) vinculados;
* Entregáveis e artefatos esperados;
* Equipe de Procuradores e Servidores participantes do GT;
* Número do Processo SEI-MA de referência e atalho de consulta.

![Modal de Detalhes do Projeto Estruturante](prints/48_projetos_painel_modal_detalhes.png)

![Modal de Detalhes com Vínculo ao Processo SEI-MA](prints/58_projetos_modal_detalhes_sei.png)

---

### 7.5. Painel 5: Workflow de Repactuação e Monitoramento Periódico (`/repactuacoes`)
Garante segurança jurídica, rastreabilidade e governança formal para qualquer solicitação de revisão de prazos, metas quantitativas ou escopos.

O painel é estruturado em **4 abas complementares**:

#### Aba 1: Fila de Deliberação (Gabinete / PGAAE)
Espaço de trabalho onde o Gabinete e a Procuradoria Adjunta de Assuntos Estratégicos analisam solicitações pendentes submetidas pelas chefias setoriais:

![Fila de Deliberação de Repactuações do Gabinete](prints/50_repactuacoes_fila_gabinete.png)

Ao clicar em **"Emitir Parecer / Homologar"**, a autoridade preenche a fundamentação técnica e define o encaminhamento (Homologado, Devolvido para Ajustes ou Rejeitado):

![Modal de Parecer e Homologação de Repactuação](prints/51_modal_homologar_parecer.png)

#### Aba 2: Quadro Geral de Solicitações
Histórico completo de pedidos de repactuação com filtros por unidade solicitante, tipo de alteração (Prazo, Meta ou Escopo), período e situação:

![Quadro Geral de Solicitações de Repactuação](prints/52_repactuacoes_quadro_geral.png)

#### Aba 3: Análise de Causas-Raiz e Motivos
Gráficos que categorizam as justificativas dos pedidos (ex.: Bloqueio Externo SEI/Sefaz, Complexidade Imprevista, Remanejamento de Pessoal, Mudança de Diretriz), orientando correções sistêmicas:

![Painel Analítico de Causas-Raiz de Repactuação](prints/54_repactuacoes_causas_motivos.png)

#### Aba 4: Trilha de Auditoria Imutável
Registro cronológico de todas as ocorrências de repactuação, documentando quem solicitou, data/hora, perfil de acesso, parâmetros anteriores e novos valores aprovados:

![Modal com Trilha de Auditoria Completa da Repactuação](prints/53_modal_trilha_auditoria.png)

#### Como a Chefia Setorial Solicita uma Repactuação:
1. Acesse **Gestão > Painéis Gerenciais > Repactuação de Metas**;
2. Clique no botão azul **"Nova Solicitação"**;
3. Selecione a ação ou projeto a ser repactuado;
4. Escolha o tipo de solicitação (*Prorrogação de Prazo*, *Revisão de Meta Quantitativa* ou *Ajuste de Escopo*);
5. Informe o novo valor ou data sugerida;
6. Selecione o motivo padronizado e elabore a justificativa técnica fundamentada;
7. Informe o número do processo SEI-MA de referência (se houver);
8. Clique em **"Enviar para Deliberação"**.

![Modal de Nova Solicitação de Repactuação](prints/55_modal_nova_repactuacao.png)

---

### 7.6. Preferências do Usuário e Customização da Interface
Clicando no nome do usuário no canto superior direito e selecionando **"Preferências"**, o sistema disponibiliza o painel individual de personalização:

![Painel de Preferências do Usuário](prints/18_preferencias_usuario_modal.png)

* **Tema Visual Institucional:** Alternância entre o **Azul Institucional PGE-MA** (padrão), modo **Claro (Light)** e modo **Escuro (Dark)**;
* **Etiquetas Pessoais (Tags):** Criação de tags coloridas personalizadas para classificação interna de tarefas;
* **Canais de Notificação:** Habilitação de avisos no sistema, por e-mail e alertas de vencimento de prazos.

---

## 8. INTEGRAÇÃO COM PROCESSOS SEI-MA & EMISSÃO DE DESPACHO OFICIAL EM PDF

### 8.1. Rastreabilidade Processual nos Projetos e Repactuações
Para assegurar plena conformidade com as rotinas de protocolo e processo administrativo eletrônico do Governo do Estado do Maranhão, o sistema conecta cada entrega estratégica ou solicitação de repactuação ao seu correspondente número de processo administrativo no **SEI-MA**.

![Quadro de Repactuações com Vínculo ao SEI-MA](prints/56_repactuacoes_integracao_sei.png)

### 8.2. Vínculo de Número de Processo Administrativo SEI-MA
* O número do processo administrativo (formato padrão `SEI-XXXXX/2026` ou equivalente estadual) pode ser inserido na criação ou edição do projeto e da repactuação;
* A qualquer momento, o gestor pode consultar a trilha de auditoria para verificar o processo administrativo associado:

![Trilha de Auditoria com Identificação do Processo SEI-MA](prints/59_repactuacao_modal_trilha_sei.png)

### 8.3. Geração Automática de Minuta de Despacho Oficial em PDF
Ao homologar uma deliberação de repactuação ou concluir um marco de projeto, o sistema disponibiliza a funcionalidade de emissão da **Minuta de Despacho / Termo de Repactuação Oficial em PDF**:

1. Na Fila de Deliberação ou na Trilha de Auditoria, localize o registro homologado;
2. Clique no botão de ação **"Gerar Despacho Oficial (PDF)"**;
3. O sistema compila os dados e realiza o download do documento gerado via **DomPDF**, contendo:
   * **Brasão Oficial do Estado do Maranhão** em alta definição;
   * Cabeçalho oficial da **Procuradoria Geral do Estado do Maranhão**;
   * Identificação da autoridade homologadora (Procurador-Geral ou PGAAE);
   * Número de registro da solicitação e número do processo SEI-MA vinculado;
   * Relatório circunstanciado das metas anteriores e repactuadas;
   * Texto de fundamentação jurídica e dispositivo homologatório;
   * Chave de autenticação digital e carimbo de data/hora.

> [!TIP]
> O PDF gerado pode ser anexado diretamente ao processo no SEI-MA como termo oficial de juntada, dispensando retrabalho de digitação manual pela equipe de apoio.

---

## 9. MÓDULO DESENVOLVEDOR & AUDITORIA TÉCNICA

Módulo de uso exclusivo da equipe técnica de Tecnologia da Informação e Comunicação (TIC) e auditores de sistemas, acessível pelo ícone **`</>`** no menu lateral retrátil:

![Menu Lateral com Acesso ao Módulo Desenvolvedor](prints/60_menu_drawer_dev.png)

Ao abrir o menu de desenvolvimento, o sistema exibe os recursos avançados de diagnóstico e infraestrutura:

![Módulo Desenvolvedor Aberto](prints/61_modulo_desenvolvedor_aberto.png)

### 9.1. Acesso Restrito e Governança de TIC (`MOD_DEV`)
O acesso a este módulo requer a capacidade técnica especial **`MOD_DEV`**. O sistema possui um usuário dedicado de homologação e suporte técnico:
* **Usuário:** `desenvolvedor@pge.ma.gov.br`
* **Perfil:** Desenvolvedor / Suporte de TIC

### 9.2. Consulta de Logs de Erros do Sistema
Permite monitorar, filtrar e diagnosticar exceções de runtime e requisições que geraram falha na aplicação, sem expor mensagens de erro 500 não tratadas:

![Módulo Desenvolvedor - Logs de Erros do Sistema](prints/62_logs_erros_desenvolvedor.png)

Recursos disponíveis:
* **Filtros por Data e Período:** Busca por dia, semana ou mês de ocorrência;
* **Filtro por Tipo de Exceção:** Localização rápida de erros de banco, timeout ou validação;
* **Stack Trace Formatado:** Exibição clara da linha de código e arquivo causador da inconsistência;
* **Paginação Segura:** Navegação estável sem travamento de tela.

### 9.3. Logs de Alterações e Auditoria de Dados
Registra detalhadamente toda mutação de estado realizada no banco de dados:
* Inserções, alterações e exclusões lógicas (*soft delete*);
* Identificação do agente público responsável pela operação;
* Snapshot dos valores antes e depois da gravação;
* Endereço IP e carimbo de data/hora.

### 9.4. Gestão de Rotinas de Integração em Segundo Plano
Ambiente para acompanhamento e disparo sob demanda das rotinas automatizadas de sincronização de dados institucionais:

![Módulo Desenvolvedor - Rotinas de Integração](prints/63_rotinas_integracao_desenvolvedor.png)

* **Status das Rotinas:** Exibe o status da última execução (Sucesso, Em Execução ou Falha);
* **Disparo Manual:** Permite forçar a execução imediata de uma rotina sem aguardar o cron;
* **Parâmetros de Carga:** Configuração de intervalos e limiares de processamento em lote.

### 9.5. Simulação de Usuário (*Impersonate*) para Diagnóstico e Suporte
Recurso essencial para a equipe de suporte e helpdesk:
* Permite ao administrador do sistema simular a visão exata da interface com o perfil e as permissões de um Procurador ou Assessor específico;
* Facilita a identificação de eventuais problemas de parametrização de permissões sem a necessidade de solicitar a senha pessoal do agente público;
* Todas as ações realizadas sob simulação são auditadas com indicação explícita do administrador simulador.

---

## 10. CONFIGURAÇÕES, NÍVEIS DE ACESSO (PERFIS) & GESTÃO DE CAPACIDADES

O módulo de **Configurações** encontra-se posicionado no menu lateral, agrupando as ferramentas essenciais de parametrização da plataforma:

![Menu Lateral com a Seção Configurações](prints/64_menu_com_configuracoes.png)

### 10.1. Acesso ao Menu "Configurações"
Ao clicar no menu **Configurações**, são exibidos quatro submódulos estruturantes:

![Menu Configurações Expandido](prints/65_configuracoes_expandido.png)

1. **Instituições (`entidades`):** Parametrização da entidade governamental PGE-MA, brasão, dados institucionais e regras globais de negócio.
2. **Unidades (`unidades`):** Gestão da estrutura organizacional das 42 Procuradorias Especializadas, Regionais e Assessorias.
3. **Agentes Públicos (`usuarios`):** Cadastro de membros e servidores, credenciais e lotações funcionais.
4. **Níveis De Acesso (`perfis`):** Configuração de perfis de segurança e concessão de capacidades granulares no sistema.

### 10.2. Gestão de Níveis de Acesso (Perfis de Segurança RBAC)
A tela de **Níveis De Acesso** lista todos os perfis disponíveis na PGE-MA, exibindo o nome institucional, nível de autorização e o quantitativo de agentes públicos vinculados a cada papel:

![Lista de Níveis De Acesso (Perfis)](prints/66_tela_niveis_de_acesso_perfis.png)

Ao selecionar um perfil e clicar em **"Editar"**, o sistema abre o formulário de parametrização cadastral e governança:

![Formulário de Edição de Nível de Acesso](prints/67_edicao_perfil_com_novas_capacidades.png)

### 10.3. Árvore de Permissões e Catálogo das 14 Novas Capacidades de Governança
Na aba **"Capacidades"**, o administrador gerencia a árvore completa de permissões do sistema:

![Aba de Capacidades com Árvore de Permissões](prints/68_aba_capacidades_arvore.png)

Expandindo os nós da árvore, é possível visualizar o catálogo com as **14 novas capacidades de governança** criadas especificamente para o ciclo da PGE-MA:

![Árvore Expandida com as 14 Novas Capacidades de Governança](prints/69_novas_capacidades_expandidas.png)

#### Catálogo das 14 Novas Capacidades Granulares:

| Código Técnico | Nome da Capacidade | Descrição Funcional e Finalidade Institucional |
| :--- | :--- | :--- |
| `MOD_COCKPIT_EXEC` | **Acesso ao Cockpit Executivo** | Permite visualizar o painel executivo com velocímetros estratégicos, saúde orçamentária e KPIs de entregas do Gabinete. |
| `MOD_PROJ_ESTRUT` | **Visualizar Projetos Estruturantes** | Permite consultar os 10 Projetos Estruturantes da PGE-MA nas visões de Cards, Cronograma Gantt e Matriz de Riscos 3x3. |
| `MOD_PROJ_EDIT` | **Gerenciar Projetos Estruturantes** | Permite criar, alterar marcos, KRs, entregáveis, composição da equipe e processos SEI-MA dos Projetos Estruturantes. |
| `MOD_REPACT_SOLICITAR` | **Solicitar Repactuação de Metas** | Permite à chefia de Especializada ou Regional formalizar pedidos de revisão de prazos, metas quantitativas ou escopo. |
| `MOD_REPACT_FILA` | **Acessar Fila de Deliberação** | Permite visualizar e triar a fila de repactuações submetidas à análise da PGAAE e do Gabinete. |
| `MOD_REPACT_PARECER` | **Emitir Parecer e Homologar** | Concede autoridade para fundamentar pareceres e homologar deliberações de repactuação de metas. |
| `MOD_REPACT_AUDITORIA` | **Auditoria de Repactuações** | Permite visualizar o quadro geral de solicitações, gráficos de causas-raiz e a trilha de auditoria completa. |
| `MOD_INTEG_SEI` | **Visualizar Vínculos SEI-MA** | Permite consultar os números de processos administrativos SEI-MA associados a projetos e repactuações. |
| `MOD_INTEG_SEI_CONFIG` | **Configurar Integração SEI-MA** | Permite parametrizar credenciais, endpoints e regras de sincronização com o barramento do SEI-MA. |
| `MOD_DESPACHO_PDF` | **Emitir Despacho Oficial PDF** | Permite gerar e fazer download de minutas de despacho oficial e termos de repactuação com Brasão do Estado via DomPDF. |
| `MOD_DEV` | **Módulo Desenvolvedor** | Permite visualizar o ícone `</>` no menu retrátil e acessar o ambiente técnico de desenvolvimento. |
| `LOG_ERRO_DEV` | **Consultar Logs de Erros** | Permite pesquisar, filtrar e diagnosticar erros e exceções de sistema na interface do desenvolvedor. |
| `LOG_ALTERACOES_DEV` | **Consultar Logs de Auditoria** | Permite auditar operações de inserção, alteração e exclusão de dados em todas as tabelas do sistema. |
| `ROTINAS_INTEG_DEV` | **Gerenciar Rotinas de Integração** | Permite monitorar e disparar manualmente rotinas em lote e sincronizações em segundo plano. |

### 10.4. Matriz de Atribuição Recomendada por Perfil

| Perfil Institucional | Capacidades Recomendadas |
| :--- | :--- |
| **Administrador Geral** | Possui acesso integral a todas as 14 capacidades por herança irrestrita. |
| **Gabinete / Procurador-Geral** | `MOD_COCKPIT_EXEC`, `MOD_PROJ_ESTRUT`, `MOD_PROJ_EDIT`, `MOD_REPACT_FILA`, `MOD_REPACT_PARECER`, `MOD_REPACT_AUDITORIA`, `MOD_INTEG_SEI`, `MOD_DESPACHO_PDF`. |
| **PGAAE (Coord. Estratégica)** | `MOD_COCKPIT_EXEC`, `MOD_PROJ_ESTRUT`, `MOD_PROJ_EDIT`, `MOD_REPACT_FILA`, `MOD_REPACT_PARECER`, `MOD_REPACT_AUDITORIA`, `MOD_INTEG_SEI`, `MOD_DESPACHO_PDF`. |
| **Chefia de Especializada / Regional**| `MOD_COCKPIT_EXEC`, `MOD_PROJ_ESTRUT`, `MOD_REPACT_SOLICITAR`, `MOD_REPACT_AUDITORIA`, `MOD_INTEG_SEI`, `MOD_DESPACHO_PDF`. |
| **Membro / Integrante da Unidade** | `MOD_PROJ_ESTRUT`, `MOD_INTEG_SEI`. |
| **Desenvolvedor / Suporte de TIC** | `MOD_DEV`, `LOG_ERRO_DEV`, `LOG_ALTERACOES_DEV`, `ROTINAS_INTEG_DEV`, `MOD_INTEG_SEI_CONFIG`. |

---

## 11. MATRIZ DE ROTINAS, GOVERNANÇA E RESPONSABILIDADES

A tabela abaixo resume as rotinas obrigatórias, periodicidade e papéis no ecossistema de gestão estratégica da PGE-MA:

| Frequência | Papel Institucional | Ação Obrigatória no Sistema | Módulo do Sistema |
| :--- | :--- | :--- | :--- |
| **Diária** | Chefia Imediata | Acompanhar a *Agenda de Marcos e Prazos* na Home para prevenir expiração de prazos. | `Home` |
| **Semanal / Quinzenal**| Responsável por Ação | Atualizar o percentual de execução e o status das entregas no Plano Setorial. | `Planos Setoriais` |
| **Até o 5º dia útil** | Procurador do Estado | Preencher e enviar o Relatório Mensal de Atividades da competência anterior. | `Corregedoria > Meu Relatório` |
| **Até o 10º dia útil**| Chefia Imediata | Analisar e homologar os relatórios mensais dos integrantes da unidade. | `Corregedoria > Homologação` |
| **Mensal** | Gabinete / PGAAE | Monitorar os velocímetros do Cockpit, saúde orçamentária e matriz de riscos dos 10 GTs. | `Painéis > Cockpit do Gabinete` |
| **Sob Demanda** | Chefia Setorial | Submeter solicitação fundamentada de repactuação de prazos/metas via workflow formal. | `Painéis > Repactuações` |
| **Sob Demanda** | Gabinete / PGAAE | Analisar deliberações pendentes, emitir parecer e gerar Despacho Oficial em PDF. | `Painéis > Repactuações` |
| **Anual** | Chefia Imediata | Instituir e pactuar o novo ciclo anual do Plano de Ação Setorial da unidade. | `Planos Setoriais` |
| **Contínua** | Suporte de TIC | Monitorar logs de erros, integridade das rotinas e parametrizar Níveis de Acesso. | `Desenvolvedor` e `Configurações` |

---

## 12. SUPORTE, CONTATOS E CANAIS INSTITUCIONAIS

Para esclarecimento de dúvidas e solicitações de atendimento técnico, utilize os canais institucionais:

* **Gabinete / Procuradoria Adjunta de Assuntos Estratégicos (PGAAE):**
  * Dúvidas sobre o alinhamento estratégico aos Objetivos 2026–2030, Eixos Temáticos, governança dos 10 GTs e critérios de repactuação de metas.
* **Corregedoria-Geral da PGE-MA:**
  * Orientações sobre o preenchimento dos relatórios de atividades da atuação finalística contenciosa/consultiva e prazos correcionais de homologação.
* **Assessoria de Tecnologia da Informação e Comunicação (TIC):**
  * Suporte técnico para problemas de autenticação, concessão de Níveis de Acesso (perfis), parametrização de novas unidades e homologação de rotinas de integração.
