<?php
namespace PingApp;

class PingData {
    public static function getMetrics($hostId, $periodHours) {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            SELECT timestamp, status, min_ms, max_ms, avg_ms, packet_loss, ttl, jitter 
            FROM ping_results 
            WHERE host_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY timestamp ASC
        ");
        
        $stmt->execute([$hostId, (int)$periodHours]);
        $rows = $stmt->fetchAll();

        $count = count($rows);
        if ($count > 600) {
            $step = (int)ceil($count / 600);
            $sampled = [];
            for ($i = 0; $i < $count; $i += $step) {
                $sampled[] = $rows[$i];
            }
            if (!empty($rows) && end($sampled) !== end($rows)) {
                $sampled[] = end($rows);
            }
            return $sampled;
        }

        return $rows;
    }

    public static function getDowntimeHistory($hostId = null) {
        $db = Database::getConnection();
        
        $sql = "
            SELECT d.start_time, d.end_time, h.ip, h.name 
            FROM host_downtimes d
            JOIN hosts h ON d.host_id = h.id
        ";
        
        if ($hostId !== null) {
            $sql .= " WHERE d.host_id = ?";
        }
        
        $sql .= " ORDER BY d.start_time DESC";
        
        $stmt = $db->prepare($sql);
        if ($hostId !== null) {
            $stmt->execute([$hostId]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }

    public static function getArchivedDowntimeHistory($hostId = null) {
        $db = Database::getConnection();
        
        $sql = "
            SELECT a.start_time, a.end_time, h.ip, h.name 
            FROM archived_host_downtimes a
            JOIN hosts h ON a.host_id = h.id
        ";
        
        if ($hostId !== null) {
            $sql .= " WHERE a.host_id = ?";
        }
        
        $sql .= " ORDER BY a.start_time DESC";
        
        $stmt = $db->prepare($sql);
        if ($hostId !== null) {
            $stmt->execute([$hostId]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }

    public static function getAnalyticsData() {
        $db = Database::getConnection();
        
        // 1. Status breakdown
        $stmt = $db->query("
            SELECT 
                SUM(CASE WHEN active = 1 AND status = 'online' THEN 1 ELSE 0 END) AS online,
                SUM(CASE WHEN active = 1 AND status = 'offline' THEN 1 ELSE 0 END) AS offline,
                SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) AS disabled,
                SUM(CASE WHEN active = 1 AND (status = 'unknown' OR status IS NULL OR status NOT IN ('online', 'offline')) THEN 1 ELSE 0 END) AS unknown,
                COUNT(*) AS total
            FROM hosts
        ");
        $statusRow = $stmt->fetch();
        $totalHosts = (int)($statusRow['total'] ?? 0);

        // 2. Top 10 offline hosts (last 7 days with outages)
        $topHostsStmt = $db->query("
            SELECT 
                h.id, 
                h.name, 
                COUNT(d.id) AS offline_count
            FROM host_downtimes d
            JOIN hosts h ON d.host_id = h.id
            WHERE d.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY h.id, h.name
            ORDER BY offline_count DESC
            LIMIT 10
        ");
        $topHosts = $topHostsStmt->fetchAll();

        // 3. Daily Online & Offline hosts history for the last 7 days
        // Shows quantity of operational hosts vs hosts with outages per day
        $onlineNow = (int)($statusRow['online'] ?? 0);
        $offlineNow = (int)($statusRow['offline'] ?? 0);
        $totalActive = $onlineNow + $offlineNow;
        $today = date('Y-m-d');

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $days[$d] = [
                'date' => $d,
                'label' => date('d/m', strtotime($d)),
                'online' => $totalActive,
                'offline' => 0
            ];
        }
        $startDateStr = array_key_first($days) . ' 00:00:00';
        $endDateStr = $today . ' 23:59:59';

        $stmt = $db->prepare("
            SELECT 
                DATE(start_time) as dt,
                COUNT(DISTINCT host_id) as affected_hosts
            FROM host_downtimes
            WHERE start_time >= ? AND start_time <= ?
            GROUP BY DATE(start_time)
        ");
        $stmt->execute([$startDateStr, $endDateStr]);
        while ($r = $stmt->fetch()) {
            $dt = $r['dt'];
            if (isset($days[$dt])) {
                $affected = (int)$r['affected_hosts'];
                $days[$dt]['offline'] = $affected;
                $days[$dt]['online'] = max(0, $totalActive - $affected);
            }
        }

        // On current date (today), set exactly the real-time hosts com queda e hosts online no momento
        if (isset($days[$today])) {
            $days[$today]['offline'] = $offlineNow;
            $days[$today]['online'] = $onlineNow;
        }

        // 4. Currently ongoing offline events (detected at this moment)
        $stmt = $db->query("
            SELECT 
                d.id,
                d.host_id,
                h.name,
                h.status as host_status,
                d.start_time,
                d.end_time,
                TIMESTAMPDIFF(SECOND, d.start_time, NOW()) as duration_seconds
            FROM host_downtimes d
            JOIN hosts h ON d.host_id = h.id
            WHERE d.end_time IS NULL
            ORDER BY d.id DESC
        ");
        $ongoing = $stmt->fetchAll();

        // 5. Recent offline events history (last 100)
        $stmt = $db->query("
            SELECT 
                d.id,
                d.host_id,
                h.name,
                h.status as host_status,
                d.start_time,
                d.end_time,
                TIMESTAMPDIFF(SECOND, d.start_time, COALESCE(d.end_time, NOW())) as duration_seconds
            FROM host_downtimes d
            JOIN hosts h ON d.host_id = h.id
            ORDER BY d.id DESC
            LIMIT 100
        ");
        $recent = $stmt->fetchAll();

        return [
            'status_breakdown' => [
                'online' => (int)($statusRow['online'] ?? 0),
                'offline' => (int)($statusRow['offline'] ?? 0),
                'disabled' => (int)($statusRow['disabled'] ?? 0),
                'unknown' => (int)($statusRow['unknown'] ?? 0),
                'total' => $totalHosts
            ],
            'top_offline_hosts' => array_map(function($r) {
                return [
                    'id' => (int)$r['id'],
                    'name' => $r['name'],
                    'offline_count' => (int)$r['offline_count']
                ];
            }, $topHosts),
            'daily_history_7d' => array_values($days),
            'daily_history_30d' => array_values($days),
            'ongoing_events' => array_map(function($r) {
                return [
                    'id' => (int)$r['id'],
                    'host_id' => (int)$r['host_id'],
                    'name' => $r['name'],
                    'status' => $r['host_status'] ?? 'offline',
                    'start_time' => $r['start_time'],
                    'end_time' => $r['end_time'],
                    'duration_seconds' => max(0, (int)$r['duration_seconds'])
                ];
            }, $ongoing),
            'recent_events' => array_map(function($r) {
                return [
                    'id' => (int)$r['id'],
                    'host_id' => (int)$r['host_id'],
                    'name' => $r['name'],
                    'status' => ($r['end_time'] === null ? 'offline' : 'online'),
                    'start_time' => $r['start_time'],
                    'end_time' => $r['end_time'],
                    'duration_seconds' => max(0, (int)$r['duration_seconds'])
                ];
            }, $recent)
        ];
    }
}

