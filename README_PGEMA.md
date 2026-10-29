# PGE-MA | Gestão Estratégica e Projetos Estruturantes

Sistema customizado para a **Procuradoria Geral do Estado do Maranhão (PGE-MA)** a partir da plataforma aberta Petrvs (MGI/GovBR).

---

## 🎯 Objetivo da Customização

1. **Planejamento Estratégico Cíclico:** Definição e gestão de ciclos estratégicos institucionais (Eixos Temáticos, Objetivos Estratégicos e Indicadores).
2. **Projetos Estruturantes:** Gestão de portfólio de projetos estratégicos vinculados aos objetivos institucionais e entregas setoriais.
3. **Pactuação e Monitoramento de Metas:** Acompanhamento da execução e entregas entre as unidades da PGE-MA.
4. **Desacoplamento e Identidade Visual:** Remoção de travas de teletrabalho federal/PGD e adoção de identidade visual da PGE-MA e autenticação direta via Usuário/E-mail e Senha.

---

## 🛠️ Requisitos de Ambiente

- **PHP 8.2** com extensões:
  - `bcmath`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `soap`, `xml`, `zip`
- **Composer 2.x**
- **MariaDB 10.11+** ou **MySQL 8.0+**
- **Redis 5.0+**
- **Node.js 18+ / 20+** e **npm**

---

## 🚀 Guia Rápido de Instalação em Nova Máquina

### 1. Clonar o Repositório
```bash
git clone https://github.com/brunoleite88/brancvs.git
cd brancvs
```

### 2. Configurar o Backend
```bash
cd back-end
cp .env.pgema.example .env
composer install --ignore-platform-reqs
```
*Ajuste no `.env` o `DB_PORT`, `DB_USERNAME` e `DB_PASSWORD` conforme seu banco local.*

### 3. Inicializar Bancos e Migrações
Certifique-se de que o MariaDB/MySQL e o Redis estejam em execução.

```bash
# 3.1 Migrar banco central
php artisan migrate --force

# 3.2 Criar e configurar o tenant PGE-MA (via Tinker)
php artisan tinker
```
No console Tinker, execute:
```php
$tenant = App\Models\Tenant::create([
    'id' => 'pgema',
    'data' => [
        'id' => 'pgema',
        'dominio_url' => 'localhost',
        'tenancy_db_name' => 'tenantpgema',
        'log_database' => 'petrvs_logs',
        'integracao_siape_codorgao' => '20000',
        'version' => '3.2.3',
        'login_user_password' => true
    ]
]);
$tenant->domains()->create(['domain' => 'localhost']);
exit
```

Em seguida, migre e popule o tenant:
```bash
# 3.3 Migrar tabelas do tenant
php artisan tenants:migrate --tenants=pgema

# 3.4 Executar seeders obrigatórios no tenant
php artisan tenants:seed --tenants=pgema

# 3.5 Criar usuário Super Admin (caso necessário)
php artisan tinker
```
No Tinker:
```php
$tenant = App\Models\Tenant::find('pgema');
tenancy()->initialize($tenant);
$rootUnit = App\Models\Unidade::whereNull('unidade_pai_id')->first();
$devProfile = App\Models\Perfil::where('nome', 'Desenvolvedor')->first();

App\Models\Usuario::create([
    'id' => Illuminate\Support\Str::uuid()->toString(),
    'nome' => 'Administrador PGE-MA',
    'email' => 'admin@pge.ma.gov.br',
    'cpf' => '00000000000',
    'matricula' => 'admin',
    'password' => bcrypt('admin123'),
    'perfil_id' => $devProfile->id,
    'lotacao_id' => $rootUnit->id
]);
exit
```

### 4. Configurar e Compilar o Frontend
```bash
cd ../front-end
npm install
npm run build-dev
```
*(O script `build-dev` compila a aplicação Angular e copia automaticamente os artefatos estáticos para `back-end/public` e `back-end/resources/views/angular.blade.php`).*

### 5. Iniciar os Serviços
```bash
# Terminal 1: Backend API Laravel
cd back-end
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2: (Opcional) Frontend Angular com Hot-Reload
cd front-end
npm start
```

---

## 🔑 Credenciais de Acesso Inicial

- **URL:** [http://localhost:8000](http://localhost:8000)
- **Usuário / E-mail:** `admin@pge.ma.gov.br`
- **Senha:** `admin123`
- **Perfil:** Desenvolvedor (Acesso Total / Super Admin)
- **Unidade Padrão:** PGE-MA (Procuradoria Geral do Estado do Maranhão)
