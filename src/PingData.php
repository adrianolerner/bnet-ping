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
        return $stmt->fetchAll();
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
}
