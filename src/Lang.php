<?php
namespace PingApp;

class Lang {
    private static $translations = [
        'en' => [
            // General
            'bnet_monitor' => 'BNET Monitor',
            'bnet_monitor_public' => 'BNET Monitor (Public)',
            'loading' => 'Loading...',
            'no_data' => 'No data found.',
            'status' => 'Status',
            'host' => 'Host',
            'ip' => 'IP',
            'action' => 'Action',
            'online' => 'ONLINE',
            'offline' => 'OFFLINE',
            'unknown' => 'UNKNOWN',
            'now' => 'NOW',
            'duration' => 'Duration',
            
            // Menu
            'nav_dashboard' => 'Dashboard',
            'nav_map' => 'Map',
            'nav_history' => 'History',
            'nav_reports' => 'Reports',
            'nav_settings' => 'Settings',
            'nav_logout' => 'Logout',
            
            // Login
            'login_welcome' => 'Welcome Back',
            'login_subtitle' => 'Sign in to BNET Monitor',
            'login_username' => 'Username',
            'login_password' => 'Password',
            'login_btn' => 'Sign In',
            
            // Dashboard
            'dash_overview' => 'Status Overview',
            'sort_name' => 'Sort by Name',
            'sort_ip' => 'Sort by IP',
            'sort_offline_first' => 'Offline First',
            'search_placeholder' => 'Search by name or IP...',
            // Public TV View
            'tv_view' => 'TV View',
            'enable_audio' => 'Enable Audio',
            'tv_host_details' => 'Host Details',
            'tv_status' => 'Status',
            'tv_duration' => 'Duration in state',
            'tv_close' => 'Close',
            
            // Host Details & Charts
            'period_1h' => '1 Hour',
            'period_24h' => '24 Hours',
            'period_1w' => '1 Week',
            'period_1m' => '1 Month',
            
            // History Tab
            'history_title' => 'Global Downtime History',
            'history_archived_title' => 'Archived Downtimes (> 30 days)',
            'host_history_title' => 'Downtime History',
            'btn_old_downtimes' => 'Old Downtimes (> 30 days)',
            'btn_recent_downtimes' => 'Recent Downtimes',
            'search_date_time' => 'Search date & time...',
            'search_history_placeholder' => 'Search by name, IP or date...',
            'date_time_from' => 'Date & Time (From)',
            'date_time_to' => 'Date & Time (To)',
            
            // Reports
            'report_gen_title' => 'Reports Generation',
            'report_export_data' => 'Export Data',
            'report_type' => 'Report Type',
            'report_downtime' => 'Downtime History (Status Changes)',
            'report_stats' => 'Ping Statistics (Raw Data)',
            'report_target_host' => 'Target Host',
            'report_all_hosts' => 'All Hosts',
            'report_start_date' => 'Start Date (Optional)',
            'report_end_date' => 'End Date (Optional)',
            'btn_gen_pdf' => '📄 Generate PDF',
            'btn_exp_csv' => '📊 Export CSV',
            
            // Settings - Tabs
            'tab_sys_config' => 'System Configuration',
            'tab_host_mgmt' => 'Host Management',
            'tab_user_mgmt' => 'User Management',
            
            // Settings - Sys Config
            'sys_ping_interval' => 'Ping Interval (seconds)',
            'sys_ping_count' => 'Ping Count per Interval',
            'sys_cf_turnstile' => 'Cloudflare Turnstile',
            'sys_cf_enable' => 'Enable Turnstile Protection',
            'sys_site_key' => 'Site Key',
            'sys_secret_key' => 'Secret Key',
            'sys_waha' => 'WAHA Notifications (WhatsApp)',
            'sys_waha_enable' => 'Enable WhatsApp Alerts',
            'sys_api_url' => 'API URL',
            'sys_api_key' => 'API Key (Optional)',
            'sys_chat_id' => 'Chat ID (e.g. 123456@c.us)',
            'sys_session_name' => 'Session Name',
            'sys_app_url' => 'App URL (for links)',
            'btn_save_config' => 'Save Configuration',
            'sys_data_mgmt' => 'Data Management',
            'btn_clear_stats' => '⚠️ Clear Statistics & History',
            'btn_factory_reset' => '🚨 Factory Reset (Delete Everything)',
            'sys_audio_alerts' => 'Audio Alerts (Public Dashboard)',
            'sys_audio_normal' => 'Normal Alert Sound (Optional)',
            'sys_audio_critical' => 'Critical Alert Sound (>50% Offline) (Optional)',
            
            // Settings - Host Mgmt
            'host_add_new' => 'Add New Host',
            'host_name' => 'Host Name',
            'host_ip' => 'IP Address',
            'host_lat' => 'Latitude (Optional)',
            'host_lng' => 'Longitude (Optional)',
            'btn_add_host' => 'Add Host',
            'host_existing' => 'Existing Hosts',
            'host_active' => 'Active',
            'btn_edit' => 'Edit',
            'btn_delete' => 'Delete',
            'host_edit' => 'Edit Host',
            'btn_save' => 'Save',
            'btn_cancel' => 'Cancel',
            'btn_enable' => 'Enable',
            'btn_disable' => 'Disable',
            
            // Map
            'map_view_details' => 'View Details',
            'filter_all' => 'All',
            'btn_refresh' => 'Refresh',
            
            // CSV & PDF Reports
            'csv_host_name' => 'Host Name',
            'csv_ip' => 'IP',
            'csv_went_offline' => 'Went Offline',
            'csv_came_online' => 'Came Online',
            'csv_duration' => 'Duration',
            'csv_duration_sec' => 'Duration (Seconds)',
            'csv_ongoing' => 'ONGOING',
            'csv_timestamp' => 'Timestamp',
            'csv_status' => 'Status',
            'csv_min_latency' => 'Min Latency',
            'csv_max_latency' => 'Max Latency',
            'csv_avg_latency' => 'Avg Latency',
            'csv_packet_loss' => 'Packet Loss %',
            'csv_jitter' => 'Jitter',
            'csv_latitude' => 'Latitude',
            'csv_longitude' => 'Longitude',
            'csv_name' => 'Name',
            'pdf_report_title' => 'BNET Monitor Report',
            'pdf_type' => 'Type',
            'pdf_host' => 'Host',
            'pdf_date_range' => 'Date Range',
            'pdf_generated_on' => 'Generated on',
            'pdf_type_downtime' => 'Downtime History',
            'pdf_type_stats' => 'Ping Statistics (Last 1000)',
            'pdf_any' => 'Any',
            'pdf_time' => 'Time',
            
            // CSV
            'host_import_title' => 'Import/Export Hosts (CSV)',
            'host_import_format' => 'Format: IP, NAME, LATITUDE, LONGITUDE (no headers)',
            'btn_import_csv' => 'Import CSV',
            'btn_export_csv' => 'Export CSV',
            
            // Settings - User Mgmt
            'user_change_password' => 'Change My Password',
            'user_current_password' => 'Current Password',
            'user_new_password' => 'New Password',
            'btn_update_password' => 'Update Password',
            'role_user' => 'User',
            'role_admin' => 'Admin',
            'user_add_new' => 'Add New User',
            'user_username' => 'Username',
            'user_password' => 'Password',
            'btn_add_user' => 'Add User',
            'user_existing' => 'Existing Users',
            'user_role' => 'Role',
            'user_created' => 'Created At',
            
            // JS alerts & confirms
            'js_confirm_clear' => 'Are you sure you want to delete ALL statistics and history? This cannot be undone!',
            'js_confirm_reset' => 'FACTORY RESET: This will delete ALL statistics, history, AND ALL HOSTS! Are you absolutely sure?',
            'js_confirm_delete_host' => 'Delete this host?',
            'js_confirm_delete_user' => 'Delete this user?',
            'js_prev' => 'Prev',
            'js_next' => 'Next',
            'js_page' => 'Page',
            'js_of' => 'of',
            
            // JS charting & strings
            'js_latency_ms' => 'Latency (ms)',
            'js_packet_loss' => 'Packet Loss (%)',
            'js_jitter_ms' => 'Jitter (ms)',
            'js_host_offline_alert' => '⚠️ HOST OFFLINE:',
            'js_critical_offline_alert' => '🚨 MULTIPLE HOSTS OFFLINE!',
            'host_exists_ip' => 'A host with this IP already exists. We recommend editing the existing item instead.'
        ],
        
        'pt' => [
            // General
            'bnet_monitor' => 'Monitor BNET',
            'bnet_monitor_public' => 'Monitor BNET (Público)',
            'loading' => 'Carregando...',
            'no_data' => 'Nenhum dado encontrado.',
            'status' => 'Status',
            'host' => 'Host',
            'ip' => 'IP',
            'action' => 'Ação',
            'online' => 'ONLINE',
            'offline' => 'OFFLINE',
            'unknown' => 'DESCONHECIDO',
            'now' => 'AGORA',
            'duration' => 'Duração',
            
            // Menu
            'nav_dashboard' => 'Dashboard',
            'nav_map' => 'Mapa',
            'nav_history' => 'Histórico',
            'nav_reports' => 'Relatórios',
            'nav_settings' => 'Configurações',
            'nav_logout' => 'Sair',
            
            // Login
            'login_welcome' => 'Bem-vindo(a) de volta',
            'login_subtitle' => 'Faça login no Monitor BNET',
            'login_username' => 'Usuário',
            'login_password' => 'Senha',
            'login_btn' => 'Entrar',
            
            // Dashboard
            'dash_overview' => 'Visão Geral do Sistema',
            'sort_name' => 'Ordenar por Nome',
            'sort_ip' => 'Ordenar por IP',
            'sort_offline_first' => 'Offline Primeiro',
            'search_placeholder' => 'Buscar por nome ou IP...',
            // Public TV View
            'tv_view' => 'Modo TV',
            'enable_audio' => 'Ativar Áudio',
            'tv_host_details' => 'Detalhes do Host',
            'tv_status' => 'Status',
            'tv_duration' => 'Duração no estado',
            'tv_close' => 'Fechar',
            
            // Host Details & Charts
            'period_1h' => '1 Hora',
            'period_24h' => '24 Horas',
            'period_1w' => '1 Semana',
            'period_1m' => '1 Mês',
            
            // History Tab
            'history_title' => 'Histórico Global de Quedas',
            'history_archived_title' => 'Quedas Arquivadas (> 30 dias)',
            'host_history_title' => 'Histórico de Quedas',
            'btn_old_downtimes' => 'Quedas Antigas (> 30 dias)',
            'btn_recent_downtimes' => 'Quedas Recentes',
            'search_date_time' => 'Buscar por data e hora...',
            'search_history_placeholder' => 'Buscar por nome, IP ou data...',
            'date_time_from' => 'Data e Hora (Início)',
            'date_time_to' => 'Data e Hora (Retorno)',
            
            // Reports
            'report_gen_title' => 'Geração de Relatórios',
            'report_export_data' => 'Exportar Dados',
            'report_type' => 'Tipo de Relatório',
            'report_downtime' => 'Histórico de Quedas (Status)',
            'report_stats' => 'Estatísticas de Ping (Dados Brutos)',
            'report_target_host' => 'Host Alvo',
            'report_all_hosts' => 'Todos os Hosts',
            'report_start_date' => 'Data de Início (Opcional)',
            'report_end_date' => 'Data de Fim (Opcional)',
            'btn_gen_pdf' => '📄 Gerar PDF',
            'btn_exp_csv' => '📊 Exportar CSV',
            
            // Settings - Tabs
            'tab_sys_config' => 'Configuração do Sistema',
            'tab_host_mgmt' => 'Gerenciamento de Hosts',
            'tab_user_mgmt' => 'Gerenciamento de Usuários',
            
            // Settings - Sys Config
            'sys_ping_interval' => 'Intervalo de Ping (segundos)',
            'sys_ping_count' => 'Testes de Ping por Intervalo',
            'sys_cf_turnstile' => 'Cloudflare Turnstile',
            'sys_cf_enable' => 'Ativar Proteção Turnstile',
            'sys_site_key' => 'Chave do Site (Site Key)',
            'sys_secret_key' => 'Chave Secreta (Secret Key)',
            'sys_waha' => 'Notificações WAHA (WhatsApp)',
            'sys_waha_enable' => 'Ativar Alertas via WhatsApp',
            'sys_api_url' => 'URL da API',
            'sys_api_key' => 'Chave da API (Opcional)',
            'sys_chat_id' => 'Chat ID (ex: 123456@c.us)',
            'sys_session_name' => 'Nome da Sessão',
            'sys_app_url' => 'URL da Aplicação (para links)',
            'btn_save_config' => 'Salvar Configuração',
            'sys_data_mgmt' => 'Gerenciamento de Dados',
            'btn_clear_stats' => '⚠️ Limpar Estatísticas e Histórico',
            'btn_factory_reset' => '🚨 Reset de Fábrica (Apagar Tudo)',
            'sys_audio_alerts' => 'Alertas Sonoros (Dashboard Pública)',
            'sys_audio_normal' => 'Som de Alerta Normal (Opcional)',
            'sys_audio_critical' => 'Som de Alerta Crítico (>50% Offline) (Opcional)',
            
            // Settings - Host Mgmt
            'host_add_new' => 'Adicionar Novo Host',
            'host_name' => 'Nome do Host',
            'host_ip' => 'Endereço IP',
            'host_lat' => 'Latitude (Opcional)',
            'host_lng' => 'Longitude (Opcional)',
            'btn_add_host' => 'Adicionar Host',
            'host_existing' => 'Hosts Cadastrados',
            'host_active' => 'Ativo',
            'btn_edit' => 'Editar',
            'btn_delete' => 'Excluir',
            'host_edit' => 'Editar Host',
            'btn_save' => 'Salvar',
            'btn_cancel' => 'Cancelar',
            'btn_enable' => 'Ativar',
            'btn_disable' => 'Desativar',
            
            // Map
            'map_view_details' => 'Ver Detalhes',
            'filter_all' => 'Todos',
            'btn_refresh' => 'Atualizar',
            
            // CSV & PDF Reports
            'csv_host_name' => 'Nome do Host',
            'csv_ip' => 'IP',
            'csv_went_offline' => 'Caiu em',
            'csv_came_online' => 'Voltou em',
            'csv_duration' => 'Duração',
            'csv_duration_sec' => 'Duração (Segundos)',
            'csv_ongoing' => 'EM ANDAMENTO',
            'csv_timestamp' => 'Data/Hora',
            'csv_status' => 'Status',
            'csv_min_latency' => 'Latência Mín',
            'csv_max_latency' => 'Latência Máx',
            'csv_avg_latency' => 'Latência Média',
            'csv_packet_loss' => 'Perda de Pacotes %',
            'csv_jitter' => 'Jitter',
            'csv_latitude' => 'Latitude',
            'csv_longitude' => 'Longitude',
            'csv_name' => 'Nome',
            'pdf_report_title' => 'Relatório Monitor BNET',
            'pdf_type' => 'Tipo',
            'pdf_host' => 'Host',
            'pdf_date_range' => 'Período',
            'pdf_generated_on' => 'Gerado em',
            'pdf_type_downtime' => 'Histórico de Quedas',
            'pdf_type_stats' => 'Estatísticas de Ping (Últimas 1000)',
            'pdf_any' => 'Qualquer',
            'pdf_time' => 'Hora',
            
            // CSV
            'host_import_title' => 'Importar/Exportar Hosts (CSV)',
            'host_import_format' => 'Formato: IP, NOME, LATITUDE, LONGITUDE (sem cabeçalho)',
            'btn_import_csv' => 'Importar CSV',
            'btn_export_csv' => 'Exportar CSV',
            
            // Settings - User Mgmt
            'user_change_password' => 'Alterar Minha Senha',
            'user_current_password' => 'Senha Atual',
            'user_new_password' => 'Nova Senha',
            'btn_update_password' => 'Atualizar Senha',
            'role_user' => 'Usuário',
            'role_admin' => 'Administrador',
            'user_add_new' => 'Adicionar Novo Usuário',
            'user_username' => 'Usuário',
            'user_password' => 'Senha',
            'btn_add_user' => 'Adicionar Usuário',
            'user_existing' => 'Usuários Cadastrados',
            'user_role' => 'Função (Role)',
            'user_created' => 'Criado em',
            
            // JS alerts & confirms
            'js_confirm_clear' => 'Tem certeza que deseja apagar TODAS as estatísticas e históricos? Isso não pode ser desfeito!',
            'js_confirm_reset' => 'RESET DE FÁBRICA: Isso apagará TODAS as estatísticas, históricos E TODOS OS HOSTS! Tem certeza absoluta?',
            'js_confirm_delete_host' => 'Excluir este host?',
            'js_confirm_delete_user' => 'Excluir este usuário?',
            'js_prev' => 'Ant',
            'js_next' => 'Próx',
            'js_page' => 'Página',
            'js_of' => 'de',
            
            // JS charting & strings
            'js_latency_ms' => 'Latência (ms)',
            'js_packet_loss' => 'Perda de Pacotes (%)',
            'js_jitter_ms' => 'Jitter (ms)',
            'js_host_offline_alert' => '⚠️ HOST OFFLINE:',
            'js_critical_offline_alert' => '🚨 DIVERSOS HOSTS OFFLINE!',
            'host_exists_ip' => 'Já existe um host cadastrado com este IP. Recomendamos editar o item existente em vez de criar um novo.'
        ]
    ];

    public static function getLanguage() {
        if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], ['en', 'pt'])) {
            return $_COOKIE['lang'];
        }
        return 'en';
    }

    public static function get($key) {
        $lang = self::getLanguage();
        return self::$translations[$lang][$key] ?? self::$translations['en'][$key] ?? $key;
    }
    
    public static function getJsTranslations() {
        $lang = self::getLanguage();
        return self::$translations[$lang];
    }
}
