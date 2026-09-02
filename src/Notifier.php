<?php
namespace PingApp;

class Notifier {
    public static function sendRawMessage($text) {
        $settings = Settings::getAll();
        
        if (!isset($settings['waha_enabled']) || $settings['waha_enabled'] != '1') {
            return;
        }

        $url = $settings['waha_url'] ?? '';
        $apiKey = $settings['waha_api_key'] ?? '';
        $chatId = $settings['waha_chat_id'] ?? '';
        $session = $settings['waha_session'] ?? 'default';

        if (empty($url) || empty($chatId)) {
            return;
        }

        $data = [
            'chatId' => $chatId,
            'text' => $text,
            'session' => $session
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        if (!empty($apiKey)) {
            $headers[] = 'X-Api-Key: ' . $apiKey;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $result = curl_exec($ch);
        if ($result === false) {
            error_log("WAHA Notifier Error: " . curl_error($ch));
        } else {
            error_log("WAHA Notifier Success: " . $result);
        }
        curl_close($ch);
    }

    public static function runBatchCheck() {
        $settings = Settings::getAll();
        if (!isset($settings['waha_enabled']) || $settings['waha_enabled'] != '1') {
            return;
        }
        
        $db = Database::getConnection();
        $appUrl = rtrim($settings['app_url'] ?? 'http://localhost/ping', '/');
        $dashboardLink = "{$appUrl}/?page=public";

        // 1. OFFLINE Check
        $stmtDown = $db->query("
            SELECT d.id, d.start_time, h.name, h.ip 
            FROM host_downtimes d
            JOIN hosts h ON d.host_id = h.id
            WHERE d.end_time IS NULL 
              AND d.start_time <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
              AND d.notified_down = 0
        ");
        $downHosts = $stmtDown->fetchAll();

        $messagesToSend = [];

        if (count($downHosts) > 0) {
            if (count($downHosts) > 10) {
                $text = "⚠️ *DIVERSOS HOSTS OFFLINE* (" . count($downHosts) . " hosts)\n\n"
                      . "Muitos equipamentos perderam comunicação.\n\n"
                      . "Consulte a dashboard para mais detalhes:\n{$dashboardLink}";
                $messagesToSend[] = $text;
            } elseif (count($downHosts) > 1) {
                $text = "⚠️ *ALERTA DE INFRAESTRUTURA*\n\nOs seguintes hosts caíram:\n\n";
                foreach ($downHosts as $h) {
                    $timeFormatted = date('d/m/Y H:i:s', strtotime($h['start_time']));
                    $text .= "🔴 {$h['name']} ({$h['ip']}) - {$timeFormatted}\n";
                }
                $text .= "\nDashboard: {$dashboardLink}";
                $messagesToSend[] = $text;
            } else {
                $h = $downHosts[0];
                $timeFormatted = date('d/m/Y H:i:s', strtotime($h['start_time']));
                $text = "*ALERTA DO MONITORAMENTO*\n\n"
                      . "Host: {$h['name']} ({$h['ip']})\n"
                      . "Status: 🔴 OFFLINE\n"
                      . "Data e hora da queda: {$timeFormatted}\n\n"
                      . "Dashboard: {$dashboardLink}";
                $messagesToSend[] = $text;
            }
            
            $ids = array_column($downHosts, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtUpdateDown = $db->prepare("UPDATE host_downtimes SET notified_down = 1 WHERE id IN ($placeholders)");
            $stmtUpdateDown->execute($ids);
        }

        // 2. ONLINE Check
        $stmtUp = $db->query("
            SELECT d.id, d.start_time, d.end_time, h.name, h.ip 
            FROM host_downtimes d
            JOIN hosts h ON d.host_id = h.id
            WHERE d.end_time IS NOT NULL 
              AND d.notified_down = 1 
              AND d.notified_up = 0
        ");
        $upHosts = $stmtUp->fetchAll();

        if (count($upHosts) > 0) {
            if (count($upHosts) > 10) {
                $text = "✅ *DIVERSOS HOSTS ONLINE* (" . count($upHosts) . " hosts)\n\n"
                      . "Vários equipamentos que estavam offline restabeleceram a comunicação.\n\n"
                      . "Consulte a dashboard para mais detalhes:\n{$dashboardLink}";
                $messagesToSend[] = $text;
            } elseif (count($upHosts) > 1) {
                $text = "✅ *ALERTA DE INFRAESTRUTURA*\n\nOs seguintes hosts restabeleceram a comunicação:\n\n";
                foreach ($upHosts as $h) {
                    $text .= "🟢 {$h['name']} ({$h['ip']})\n";
                }
                $text .= "\nDashboard: {$dashboardLink}";
                $messagesToSend[] = $text;
            } else {
                $h = $upHosts[0];
                $durationSec = strtotime($h['end_time']) - strtotime($h['start_time']);
                $hours = floor($durationSec / 3600);
                $minutes = floor(($durationSec % 3600) / 60);
                $durationStr = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes} minutos";

                $text = "*ALERTA DO MONITORAMENTO*\n\n"
                      . "Host: {$h['name']} ({$h['ip']})\n"
                      . "Status: 🟢 ONLINE\n"
                      . "Tempo Fora: {$durationStr}\n\n"
                      . "Dashboard: {$dashboardLink}";
                $messagesToSend[] = $text;
            }
            
            $ids = array_column($upHosts, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtUpdateUp = $db->prepare("UPDATE host_downtimes SET notified_up = 1 WHERE id IN ($placeholders)");
            $stmtUpdateUp->execute($ids);
        }

        // 3. Process the Queue with Random Delay
        foreach ($messagesToSend as $index => $msg) {
            if ($index > 0) {
                sleep(rand(5, 12));
            }
            self::sendRawMessage($msg);
        }
    }
}
