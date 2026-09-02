<?php
namespace PingApp;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Host.php';
require_once __DIR__ . '/Notifier.php';

class PingDaemon {
    
    public static function run() {
        echo "Starting Ping Daemon (Async)...\n";
        
        $lastCleanup = 0;
        $lastNotificationCheck = 0;
        
        while (true) {
            $settings = Settings::getAll();
            $interval = (int)($settings['ping_interval'] ?? 60);
            $count = min(10, (int)($settings['ping_count'] ?? 4));
            
            if (time() - $lastCleanup > 3600) {
                self::runCleanup();
                $lastCleanup = time();
            }

            if (time() - $lastNotificationCheck > 300) {
                try {
                    Notifier::runBatchCheck();
                } catch (\Exception $e) {
                    echo "Error in notification check: " . $e->getMessage() . "\n";
                }
                $lastNotificationCheck = time();
            }
            
            $hosts = Host::getAll();
            $activeHosts = array_filter($hosts, function($h) {
                return !isset($h['active']) || $h['active'] == 1;
            });
            
            if (count($activeHosts) > 0) {
                $chunks = array_chunk($activeHosts, 50);
                
                foreach ($chunks as $chunk) {
                    self::pingHostsAsync($chunk, $count);
                }
            }
            
            echo "Sleeping for $interval seconds...\n";
            sleep($interval);
        }
    }

    private static function runCleanup() {
        try {
            $db = Database::getConnection();
            
            $db->exec("
                INSERT INTO archived_host_downtimes (host_id, start_time, end_time)
                SELECT host_id, start_time, end_time 
                FROM host_downtimes 
                WHERE end_time IS NOT NULL AND end_time < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $db->exec("DELETE FROM host_downtimes WHERE end_time IS NOT NULL AND end_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $db->exec("DELETE FROM ping_results WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $db->exec("DELETE FROM archived_host_downtimes WHERE end_time < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
            
            echo "Cleanup task completed.\n";
        } catch (\Exception $e) {
            echo "Error during cleanup: " . $e->getMessage() . "\n";
        }
    }

    private static function pingHostsAsync($hosts, $count) {
        $os = PHP_OS_FAMILY;
        $processes = [];
        
        foreach ($hosts as $host) {
            $ip = escapeshellarg($host['ip']);
            if ($os === 'Windows') {
                $cmd = "ping -n $count $ip";
            } else {
                $cmd = "ping -c $count $ip";
            }
            
            $descriptorspec = [
               1 => ["pipe", "w"],
               2 => ["pipe", "w"]
            ];
            
            $process = proc_open($cmd, $descriptorspec, $pipes);
            if (is_resource($process)) {
                stream_set_blocking($pipes[1], 0);
                $processes[$host['id']] = [
                    'process' => $process,
                    'pipes' => $pipes,
                    'host' => $host,
                    'output' => ''
                ];
            }
        }
        
        $active = count($processes);
        while ($active > 0) {
            foreach ($processes as $id => &$p) {
                if ($p['process'] === null) continue;
                
                $status = proc_get_status($p['process']);
                $p['output'] .= stream_get_contents($p['pipes'][1]);
                
                if (!$status['running']) {
                    fclose($p['pipes'][1]);
                    fclose($p['pipes'][2]);
                    $exitCode = $status['exitcode'];
                    proc_close($p['process']);
                    
                    self::processResult($p['host'], $p['output'], $exitCode, $os, $count);
                    
                    $p['process'] = null;
                    $active--;
                }
            }
            usleep(100000); 
        }
    }
    
    private static function processResult($host, $outputStr, $exitCode, $os, $count) {
        $metrics = self::parsePingOutput($outputStr, $os, $count);
        
        if ($count >= 5) {
            $isOnline = ($metrics['packet_loss'] <= 50 && $exitCode === 0) ? 'online' : 'offline';
        } else {
            $isOnline = ($metrics['packet_loss'] < 100 && $exitCode === 0) ? 'online' : 'offline';
        }
        
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO ping_results 
            (host_id, status, min_ms, max_ms, avg_ms, packet_loss, ttl, jitter)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $host['id'],
            $isOnline,
            $metrics['min'],
            $metrics['max'],
            $metrics['avg'],
            $metrics['packet_loss'],
            $metrics['ttl'],
            $metrics['jitter']
        ]);

        if ($host['status'] !== $isOnline) {
            if ($isOnline === 'offline') {
                $stmtDown = $db->prepare("INSERT INTO host_downtimes (host_id, start_time) VALUES (?, NOW())");
                $stmtDown->execute([$host['id']]);
            } else if ($isOnline === 'online' && $host['status'] === 'offline') {
                $stmtUp = $db->prepare("UPDATE host_downtimes SET end_time = NOW() WHERE host_id = ? AND end_time IS NULL ORDER BY start_time DESC LIMIT 1");
                $stmtUp->execute([$host['id']]);
            }

            Host::updateStatus($host['id'], $isOnline);
        }
        
        echo "Pinged {$host['name']} ({$host['ip']}) - Status: $isOnline\n";
    }

    private static function parsePingOutput($output, $os, $count) {
        $metrics = [
            'min' => null, 'max' => null, 'avg' => null, 
            'packet_loss' => 100, 'ttl' => null, 'jitter' => null
        ];
        
        if ($os === 'Windows') {
            if (preg_match('/(?:Perdidos|Lost).*?\((\d+)%/i', $output, $matches)) {
                $metrics['packet_loss'] = (float)$matches[1];
            } elseif (preg_match('/(\d+)% loss/i', $output, $matches)) {
                $metrics['packet_loss'] = (float)$matches[1];
            }

            // Handle encoding issues with accented characters by using '.' in the regex
            if (preg_match('/(?:inacess.vel|unreachable|inaccesible|esgotado|timed out)/i', $output)) {
                $metrics['packet_loss'] = 100;
            }

            if (preg_match('/(?:nimo|imum).*?(\d+)ms.*?(?:ximo|imum).*?(\d+)ms.*?(?:dia|erage).*?(\d+)ms/i', $output, $matches)) {
                $metrics['min'] = (float)$matches[1];
                $metrics['max'] = (float)$matches[2];
                $metrics['avg'] = (float)$matches[3];
                $metrics['jitter'] = abs($metrics['max'] - $metrics['min']) / 2;
            }
            if (preg_match('/TTL=(\d+)/i', $output, $matches)) {
                $metrics['ttl'] = (int)$matches[1];
            }
        } else {
            if (preg_match('/(\d+)% packet loss/i', $output, $matches)) {
                $metrics['packet_loss'] = (float)$matches[1];
            }
            if (preg_match('/min\/avg\/max\/(?:mdev|stddev) = ([\d\.]+)\/([\d\.]+)\/([\d\.]+)\/([\d\.]+)/i', $output, $matches)) {
                $metrics['min'] = (float)$matches[1];
                $metrics['avg'] = (float)$matches[2];
                $metrics['max'] = (float)$matches[3];
                $metrics['jitter'] = (float)$matches[4]; 
            }
            if (preg_match('/ttl=(\d+)/i', $output, $matches)) {
                $metrics['ttl'] = (int)$matches[1];
            }
        }
        
        return $metrics;
    }
}
