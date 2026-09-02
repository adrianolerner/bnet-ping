<?php
namespace PingApp;

class Host {
    public static function getAll() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM hosts ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public static function getById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM hosts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function getByIp($ip) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM hosts WHERE ip = ?");
        $stmt->execute([$ip]);
        return $stmt->fetch();
    }

    public static function add($name, $ip, $lat = null, $lng = null) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO hosts (name, ip, latitude, longitude) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$name, $ip, $lat, $lng]);
    }

    public static function update($id, $name, $ip, $lat = null, $lng = null) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE hosts SET name = ?, ip = ?, latitude = ?, longitude = ? WHERE id = ?");
        return $stmt->execute([$name, $ip, $lat, $lng, $id]);
    }

    public static function delete($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM hosts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function updateStatus($id, $status) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE hosts SET status = ?, last_status_change = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public static function toggleActive($id, $active) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE hosts SET active = ? WHERE id = ?");
        return $stmt->execute([$active, $id]);
    }
}
