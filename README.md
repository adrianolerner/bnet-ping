# BNET Ping Monitor v0.12

O BNET Ping Monitor é um sistema de monitoramento de disponibilidade (uptime) desenvolvido em PHP e JavaScript, com suporte nativo à execução assíncrona. Ele acompanha o tempo de resposta, latência, jitter e a porcentagem de perda de pacotes de diversos dispositivos de rede e servidores em tempo real.

O sistema conta com um painel de administração completo, visualização em tempo real (TV View/Public) ideal para NOCs (Network Operations Centers), e sistema de alerta integrado ao WhatsApp.

## Novidades na v0.12
- **Mapa Geográfico Aprimorado:** Melhorias nas animações e marcações visuais com novos agrupamentos inteligentes (clusters) que não misturam dispositivos de status diferentes (agrupamento distinto para offline e online), com priorização das marcações offline em tela (z-index superior).
- **Relatórios Multilinguagem Dinâmicos:** A exportação de dados em CSV e PDF agora respeitam o idioma da sessão do usuário, traduzindo automaticamente cabeçalhos e status das tabelas com os novos recursos do `Lang.php`.
- **Ajustes de UI/UX:** Refatoração de botões como o "Refresh" em SVG nativo para maior fluidez e adequação do campo de busca. Injeção direta de animações por inline-styles (CSS bypass) contornando cache agressivo dos navegadores para ícones do mapa.
- **TV View Aprimorada:** O painel público (Modo TV) agora conta com Popups/Modais interativos que mostram o tempo detalhado em que o host encontra-se ligado ou desligado.
- **Importação/Exportação Avançada:** Suporte expandido de Importação via CSV incluindo geolocalização (IP, Nome, Latitude, Longitude) e um novo recurso de Exportação CSV direto do painel.

## Recursos Principais

- **Monitoramento Assíncrono:** O daemon em PHP processa os testes ICMP (Ping) assincronamente (em lotes) mantendo o baixo consumo de recursos, e interpretando os resultados corretamente tanto em ambientes Windows quanto Linux.
- **TV View (Dashboard Público):** Uma tela dedicada a telões de NOC com atualização em tempo real (polling de 5 segundos), ordenação inteligente, indicação visual, alertas sonoros e modais com histórico de estado em tempo real.
- **Métricas Detalhadas:** Gráficos responsivos criados em Chart.js exibindo Latência (Mín, Máx, Média), Perda de Pacotes e Jitter (variação de latência), com visualização por períodos (1 Hora, 24 Horas, 1 Semana, etc).
- **Histórico de Quedas:** Tabelas com suporte a paginação e busca em tempo real que registram o momento exato em que um host ficou offline, disponível tanto no formato Global (para toda a rede) quanto Individual (por host).
- **Notificações Inteligentes (WhatsApp):** Integração avançada com a API WAHA (WhatsApp HTTP API), possuindo agrupamento de mensagens (anti-spam) e delays aleatórios para contornar bloqueios de rate limit, informando o tempo exato de downtime.
- **Autenticação e Permissões (RBAC):** Sistema de permissões com níveis de Administrador e Usuário comum, isolando configurações globais do sistema, aliado a proteção Cloudflare Turnstile opcional e sanitização reforçada contra injeção SQL/XSS.
- **Responsividade e Proxies:** Interface perfeitamente adaptada para dispositivos móveis e design nativo preparado para rodar atrás de Proxies Reversos (como NGINX e Cloudflare) em subpastas ou domínios raiz.

---

## Requisitos do Sistema

- Servidor Web (Apache2 ou Nginx)
- PHP 8.0 ou superior (com extensões PDO, cURL)
- MariaDB 10.x ou MySQL 8.x
- Sistema Operacional Linux (recomendado: Ubuntu/Debian) ou Windows.

## Guia de Instalação (Apache2, PHP e MariaDB no Linux)

### 1. Preparando o Ambiente
No terminal do seu servidor Ubuntu/Debian, instale os componentes:
```bash
sudo apt update
sudo apt install apache2 php libapache2-mod-php php-mysql php-cli php-curl mariadb-server git
```

### 2. Configurando o Banco de Dados (MariaDB)
Acesse o prompt do banco de dados:
```bash
sudo mysql -u root
```
Crie o banco de dados, usuário e dê as permissões:
```sql
CREATE DATABASE ping_monitor;
CREATE USER 'ping_user'@'localhost' IDENTIFIED BY 'SUA_SENHA_FORTE';
GRANT ALL PRIVILEGES ON ping_monitor.* TO 'ping_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Clonando o Repositório e Configuração
Clone os arquivos do projeto dentro do diretório web do Apache (use a raiz ou uma subpasta, como "ping" conforme sua necessidade e seu servidor):
```bash
cd /var/www/html
git clone https://github.com/SEU-USUARIO/bnet-ping-monitor.git ping
cd ping
```

Configure o acesso ao banco de dados e APIs:
Edite diretamente as credenciais no arquivo `config.php`.
Certifique-se de que a conexão aponte para a base `ping_monitor` criada acima.

**Chave do Mapa (CARTO):** O sistema utiliza os mapas da CARTO. É necessário gerar uma chave de API em [https://carto.com/basemaps/apikey/](https://carto.com/basemaps/apikey/) e adicioná-la à variável `carto_api_key` no arquivo `config.php`.

Para popular as tabelas iniciais, importe o esquema (se incluído) ou deixe a aplicação iniciar a estrutura nativamente caso o `Database.php` use `CREATE TABLE IF NOT EXISTS` no boot.

Dê as permissões necessárias para o servidor web (use a raiz ou uma subpasta, como "ping" conforme sua necessidade e seu servidor):
```bash
sudo chown -R www-data:www-data /var/www/html/ping
```

Configure a pasta escolhida como DocumentRoot ou ajuste conforme sua necessidade, apontando para a pasta public do projeto:
Edite o arquivo ou similar '/etc/apache2/sites-available/000-default.conf'.
Edite a linha como segue: "DocumentRoot /var/www/html/public"
Reinicie o serviço do apache2.

### 4. Iniciando o Daemon de Monitoramento
O sistema possui um worker em background (Daemon) responsável por realizar os disparos de ping. Esse script deve rodar continuamente.

Você pode criar um serviço no Systemd para mantê-lo rodando:
```bash
sudo nano /etc/systemd/system/ping-daemon.service
```
Insira o seguinte conteúdo:
```ini
[Unit]
Description=BNET Ping Monitor Daemon
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/ping/src
ExecStart=/usr/bin/php /var/www/html/ping/src/PingDaemon.php
Restart=always

[Install]
WantedBy=multi-user.target
```

Ative e inicie o Daemon:
```bash
sudo systemctl enable ping-daemon
sudo systemctl start ping-daemon
```

### 5. Primeiro Acesso
Acesse a URL do sistema no seu navegador (ex: `http://SEU_IP/ping/public/`).
Utilize o usuário padrão inicial (que deve ser alterado imediatamente nas configurações de usuário do sistema).

---

## Estrutura do Projeto
- `public/`: Contém os assets expostos, folhas de estilo (`css/`), scripts front-end (`js/`) e o arquivo principal `index.php`.
- `src/`: Lógica central em PHP (Database, Daemon, Autenticação, Models).

---

## Arquitetura, Conceitos e Boas Práticas

### 1. Visão Geral e Arquitetura do Sistema
O **BNET Ping Monitor** é composto por duas camadas principais:
- **Serviço de Background (Daemon - PHP CLI):** Processo contínuo que realiza requisições ICMP (ping) assíncronas aos IPs cadastrados, calcula métricas (latência, jitter, perda de pacotes), registra históricos no banco de dados e envia notificações.
- **Aplicação Web (Front-end & API RESTful):** Painel administrativo e Dashboard NOC (*TV View*) construído com **PHP** (padrão *Front Controller*), **JavaScript (ES6+)**, **Chart.js** e **CSS3**.

### 2. Funcionamento da Aplicação, Rotas e API
- **Padrão Front Controller (`public/index.php`):** Centraliza a inicialização de sessões, verificação de autenticação (`Auth.php`), internacionalização (`Lang.php`) e o roteamento via `$_GET['page']`.
- **Rotas de API (JSON):**
  - `api/hosts-public`: Rota pública usada pelo dashboard de TV/NOC (omite IPs para segurança).
  - `api/hosts`: Lista completa de hosts cadastrados (requer login).
  - `api/metrics?id={id}&period={horas}`: Histórico de métricas para geração de gráficos.
  - `api/history?id={id}`: Histórico recente de quedas (*downtimes*).
  - `api/history-archived?id={id}`: Histórico de quedas arquivadas.
- **Exportação de Dados:** Suporte a relatórios em CSV (`export_csv`) e PDF (`export_pdf`).

### 3. Daemon Assíncrono (`src/PingDaemon.php`)
- **Execução Assíncrona e Paralelelismo (`proc_open`):** Os hosts são agrupados em lotes (*chunks*) de até 50 itens. Cada ping é iniciado em um processo filho não-bloqueante (`stream_set_blocking($pipes[1], 0)`), lendo saídas concorrentemente via `usleep(100000)` sem travar a execução principal.
- **Suporte Multiplataforma (Linux / Windows):** Identifica o sistema operacional (`PHP_OS_FAMILY`) e ajusta os parâmetros de linha de comando (`ping -c` / `ping -n`) e a expressão regular de parseamento dos resultados.

### 4. Banco de Dados e Persistência (`src/Database.php`)
- **Padrão Singleton:** Garante uma única instância da conexão PDO por ciclo de execução.
- **Auto-Migração (`initSchema`):** Criação e alteração automática de tabelas (`CREATE TABLE IF NOT EXISTS` / `ALTER TABLE`) na inicialização da aplicação.
- **Retenção de Dados e Expurgo (*Cleanup*):** Arquivamento periódico de quedas com mais de 30 dias (`archived_host_downtimes`) e remoção automática de registros brutos de ping antigos.

### 5. Boas Práticas e Segurança Aplicadas
- **Sanitização de Comandos Shell:** Uso de `escapeshellarg()` no envio de IPs para o terminal, prevenindo ataques de *Command Injection*.
- **Proteção Contra Brute Force:** Bloqueio temporário de IP por 5 minutos após 5 tentativas de login com erro (`rate_limits`).
- **Criptografia Segura:** Armazenamento de senhas via `password_hash()` com algoritmo BCRYPT.
- **Proteção CAPTCHA:** Suporte opcional ao Cloudflare Turnstile no login.
- **Controle de Acesso (RBAC):** Níveis de permissão diferenciados para `admin` e `user`.
- **Prevenção a SQL Injection:** Uso exclusivo de *Prepared Statements* com parâmetros vinculados no PDO.

### 6. Notificações Inteligentes (`src/Notifier.php`)
- **Integração WhatsApp (API WAHA):** Despacho automatizado de alertas de queda e restauração de serviço.
- **Agrupamento de Mensagens (Anti-Spam):** Agrupa múltiplos eventos simultâneos em um alerta consolidado para evitar inundações de mensagens.
- **Delay Aleatório:** Aplica intervalos aleatórios (`sleep(rand(5, 12))`) entre envios em lote para evitar bloqueios de *rate-limiting* no WhatsApp.

