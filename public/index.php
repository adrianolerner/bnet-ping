<?php
/**
 * INSTRUÇÃO: Sempre atualize a constante APP_VERSION (versões e subversões) 
 * sempre que realizar alterações futuras no código do sistema!
 */
define('APP_VERSION', '0.12.1');

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/Host.php';
require_once __DIR__ . '/../src/PingData.php';
require_once __DIR__ . '/../src/PingData.php';
require_once __DIR__ . '/../src/Lang.php';

use PingApp\Auth;
use PingApp\Settings;
use PingApp\Host;
use PingApp\PingData;
use PingApp\Lang;

Auth::startSession();

$page = $_GET['page'] ?? 'dashboard';

if ($page === 'set_lang') {
    $lang = $_GET['lang'] ?? 'en';
    if (in_array($lang, ['en', 'pt'])) {
        setcookie('lang', $lang, time() + (10 * 365 * 24 * 60 * 60), '/'); // 10 years
    }
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
    exit;
}

if ($page === 'export_csv') {
    Auth::requireLogin();

    $type = $_GET['type'] ?? 'downtime';
    $hostId = !empty($_GET['host_id']) ? (int) $_GET['host_id'] : null;
    $startDate = null;
    if (!empty($_GET['start_date'])) {
        $val = str_replace('T', ' ', $_GET['start_date']);
        if (strlen($val) === 10)
            $startDate = $val . ' 00:00:00';
        elseif (strlen($val) === 16)
            $startDate = $val . ':00';
        else
            $startDate = $val;
    }

    $endDate = null;
    if (!empty($_GET['end_date'])) {
        $val = str_replace('T', ' ', $_GET['end_date']);
        if (strlen($val) === 10)
            $endDate = $val . ' 23:59:59';
        elseif (strlen($val) === 16)
            $endDate = $val . ':59';
        else
            $endDate = $val;
    }

    if ($startDate && $endDate) {
        if (strtotime($endDate) - strtotime($startDate) > 30 * 86400) {
            $startDate = date('Y-m-d H:i:s', strtotime($endDate) - 30 * 86400);
        }
    } elseif ($endDate) {
        $startDate = date('Y-m-d H:i:s', strtotime($endDate) - 30 * 86400);
    } elseif ($startDate) {
        $endDate = date('Y-m-d H:i:s', strtotime($startDate) + 30 * 86400);
    } else {
        $endDate = date('Y-m-d H:i:s');
        $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
    }

    $db = \PingApp\Database::getConnection();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bnet_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');

    if ($type === 'downtime') {
        fputcsv($output, [\PingApp\Lang::get('csv_host_name'), \PingApp\Lang::get('csv_ip'), \PingApp\Lang::get('csv_went_offline'), \PingApp\Lang::get('csv_came_online'), \PingApp\Lang::get('csv_duration_sec')]);
        $sql = "SELECT h.name, h.ip, d.start_time, d.end_time 
                FROM host_downtimes d 
                JOIN hosts h ON d.host_id = h.id 
                WHERE 1=1";
        $params = [];
        if ($hostId) {
            $sql .= " AND h.id = ?";
            $params[] = $hostId;
        }
        if ($startDate) {
            $sql .= " AND d.start_time >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND d.start_time <= ?";
            $params[] = $endDate;
        }
        $sql .= " ORDER BY d.start_time DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $start = strtotime($row['start_time']);
            $end = $row['end_time'] ? strtotime($row['end_time']) : time();
            $duration = $end - $start;
            fputcsv($output, [$row['name'], $row['ip'], $row['start_time'], $row['end_time'] ?? \PingApp\Lang::get('csv_ongoing'), $duration]);
        }
    } else { // stats
        fputcsv($output, [\PingApp\Lang::get('csv_host_name'), \PingApp\Lang::get('csv_ip'), \PingApp\Lang::get('csv_timestamp'), \PingApp\Lang::get('csv_status'), \PingApp\Lang::get('csv_min_latency'), \PingApp\Lang::get('csv_max_latency'), \PingApp\Lang::get('csv_avg_latency'), \PingApp\Lang::get('csv_packet_loss'), \PingApp\Lang::get('csv_jitter')]);
        $sql = "SELECT h.name, h.ip, p.timestamp, p.status, p.min_ms, p.max_ms, p.avg_ms, p.packet_loss, p.jitter 
                FROM ping_results p 
                JOIN hosts h ON p.host_id = h.id 
                WHERE 1=1";
        $params = [];
        if ($hostId) {
            $sql .= " AND h.id = ?";
            $params[] = $hostId;
        }
        if ($startDate) {
            $sql .= " AND p.timestamp >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND p.timestamp <= ?";
            $params[] = $endDate;
        }
        $sql .= " ORDER BY p.timestamp DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            fputcsv($output, [$row['name'], $row['ip'], $row['timestamp'], $row['status'], $row['min_ms'], $row['max_ms'], $row['avg_ms'], $row['packet_loss'], $row['jitter']]);
        }
    }
    fclose($output);
    exit;
}

if ($page === 'export_hosts_csv') {
    Auth::requireLogin();
    $db = \PingApp\Database::getConnection();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=hosts_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, [\PingApp\Lang::get('csv_ip'), \PingApp\Lang::get('csv_name'), \PingApp\Lang::get('csv_latitude'), \PingApp\Lang::get('csv_longitude')]);
    $stmt = $db->query("SELECT ip, name, latitude, longitude FROM hosts ORDER BY name ASC");
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['ip'], $row['name'], $row['latitude'], $row['longitude']]);
    }
    fclose($output);
    exit;
}

if ($page === 'export_pdf') {
    Auth::requireLogin();

    $type = $_GET['type'] ?? 'downtime';
    $hostId = !empty($_GET['host_id']) ? (int) $_GET['host_id'] : null;
    $startDate = null;
    if (!empty($_GET['start_date'])) {
        $val = str_replace('T', ' ', $_GET['start_date']);
        if (strlen($val) === 10)
            $startDate = $val . ' 00:00:00';
        elseif (strlen($val) === 16)
            $startDate = $val . ':00';
        else
            $startDate = $val;
    }

    $endDate = null;
    if (!empty($_GET['end_date'])) {
        $val = str_replace('T', ' ', $_GET['end_date']);
        if (strlen($val) === 10)
            $endDate = $val . ' 23:59:59';
        elseif (strlen($val) === 16)
            $endDate = $val . ':59';
        else
            $endDate = $val;
    }

    if ($startDate && $endDate) {
        if (strtotime($endDate) - strtotime($startDate) > 30 * 86400) {
            $startDate = date('Y-m-d H:i:s', strtotime($endDate) - 30 * 86400);
        }
    } elseif ($endDate) {
        $startDate = date('Y-m-d H:i:s', strtotime($endDate) - 30 * 86400);
    } elseif ($startDate) {
        $endDate = date('Y-m-d H:i:s', strtotime($startDate) + 30 * 86400);
    } else {
        $endDate = date('Y-m-d H:i:s');
        $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
    }

    $db = \PingApp\Database::getConnection();

    // Fetch data
    $data = [];
    if ($type === 'downtime') {
        $sql = "SELECT h.name, h.ip, d.start_time, d.end_time 
                FROM host_downtimes d 
                JOIN hosts h ON d.host_id = h.id 
                WHERE 1=1";
        $params = [];
        if ($hostId) {
            $sql .= " AND h.id = ?";
            $params[] = $hostId;
        }
        if ($startDate) {
            $sql .= " AND d.start_time >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND d.start_time <= ?";
            $params[] = $endDate;
        }
        $sql .= " ORDER BY d.start_time DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } else { // stats
        $sql = "SELECT h.name, h.ip, p.timestamp, p.status, p.min_ms, p.max_ms, p.avg_ms, p.packet_loss, p.jitter 
                FROM ping_results p 
                JOIN hosts h ON p.host_id = h.id 
                WHERE 1=1";
        $params = [];
        if ($hostId) {
            $sql .= " AND h.id = ?";
            $params[] = $hostId;
        }
        if ($startDate) {
            $sql .= " AND p.timestamp >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND p.timestamp <= ?";
            $params[] = $endDate;
        }
        $sql .= " ORDER BY p.timestamp DESC LIMIT 1000"; // limit to 1000 for PDF to avoid massive files
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    $hostName = \PingApp\Lang::get('report_all_hosts');
    if ($hostId) {
        $h = Host::getById($hostId);
        if ($h)
            $hostName = $h['name'];
    }

    // Render clean HTML
    ?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Report</title>
        <style>
            body {
                font-family: 'Helvetica', 'Arial', sans-serif;
                font-size: 12px;
                color: #333;
            }

            h1,
            h2,
            h3 {
                color: #1a202c;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }

            th,
            td {
                padding: 8px;
                border: 1px solid #e2e8f0;
                text-align: left;
            }

            th {
                background-color: #f8fafc;
                font-weight: bold;
            }

            .header {
                border-bottom: 2px solid #e2e8f0;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }

            .chart-container {
                width: 100%;
                height: 250px;
                margin-bottom: 20px;
            }

            @media print {
                @page {
                    size: landscape;
                    margin: 10mm;
                }

                body {
                    -webkit-print-color-adjust: exact;
                }
            }
        </style>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </head>

    <body>
        <div id="pdf-content" style="padding: 20px;">
            <div class="header">
                <h1><?= \PingApp\Lang::get('pdf_report_title') ?></h1>
                <p><strong><?= \PingApp\Lang::get('pdf_type') ?>:</strong>
                    <?= $type === 'downtime' ? \PingApp\Lang::get('pdf_type_downtime') : \PingApp\Lang::get('pdf_type_stats') ?>
                </p>
                <p><strong><?= \PingApp\Lang::get('pdf_host') ?>:</strong> <?= htmlspecialchars($hostName) ?></p>
                <p><strong><?= \PingApp\Lang::get('pdf_date_range') ?>:</strong>
                    <?= htmlspecialchars($_GET['start_date'] ?? \PingApp\Lang::get('pdf_any')) ?> to
                    <?= htmlspecialchars($_GET['end_date'] ?? \PingApp\Lang::get('pdf_any')) ?>
                </p>
                <p><strong><?= \PingApp\Lang::get('pdf_generated_on') ?>:</strong> <?= date('Y-m-d H:i:s') ?></p>
            </div>

            <table>
                <?php if ($type === 'downtime'): ?>
                    <thead>
                        <tr>
                            <th><?= \PingApp\Lang::get('pdf_host') ?></th>
                            <th><?= \PingApp\Lang::get('csv_ip') ?></th>
                            <th><?= \PingApp\Lang::get('csv_went_offline') ?></th>
                            <th><?= \PingApp\Lang::get('csv_came_online') ?></th>
                            <th><?= \PingApp\Lang::get('csv_duration') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row):
                            $start = strtotime($row['start_time']);
                            $end = $row['end_time'] ? strtotime($row['end_time']) : time();
                            $dur = $end - $start;
                            $durStr = floor($dur / 3600) . 'h ' . floor(($dur % 3600) / 60) . 'm ' . ($dur % 60) . 's';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['ip']) ?></td>
                                <td><?= $row['start_time'] ?></td>
                                <td><?= $row['end_time'] ?? \PingApp\Lang::get('csv_ongoing') ?></td>
                                <td><?= $durStr ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="5"><?= \PingApp\Lang::get('no_data') ?></td>
                            </tr><?php endif; ?>
                    </tbody>
                <?php else: ?>
                    <?php if (!empty($data)): ?>
                        <div class="chart-container">
                            <canvas id="statsChart"></canvas>
                        </div>
                    <?php endif; ?>
                    <thead>
                        <tr>
                            <th><?= \PingApp\Lang::get('pdf_host') ?></th>
                            <th><?= \PingApp\Lang::get('pdf_time') ?></th>
                            <th><?= \PingApp\Lang::get('csv_status') ?></th>
                            <th><?= \PingApp\Lang::get('csv_avg_latency') ?></th>
                            <th><?= \PingApp\Lang::get('csv_packet_loss') ?></th>
                            <th><?= \PingApp\Lang::get('csv_jitter') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= $row['timestamp'] ?></td>
                                <td><?= $row['status'] ?></td>
                                <td><?= $row['avg_ms'] ?>ms</td>
                                <td><?= $row['packet_loss'] ?>%</td>
                                <td><?= $row['jitter'] ?>ms</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="6"><?= \PingApp\Lang::get('no_data') ?></td>
                            </tr><?php endif; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", () => {
                <?php if ($type === 'stats' && !empty($data)): ?>
                    const rawData = <?= json_encode($data) ?>;
                    const labels = rawData.map(d => d.timestamp).reverse();
                    const avgData = rawData.map(d => d.avg_ms).reverse();
                    const lossData = rawData.map(d => d.packet_loss).reverse();

                    const ctx = document.getElementById('statsChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                { label: 'Avg Latency (ms)', data: avgData, borderColor: '#3b82f6', tension: 0.1, yAxisID: 'y' },
                                { label: 'Packet Loss (%)', data: lossData, borderColor: '#ef4444', tension: 0.1, yAxisID: 'y1' }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            scales: {
                                y: { type: 'linear', display: true, position: 'left' },
                                y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false } }
                            }
                        }
                    });
                <?php endif; ?>

                setTimeout(() => {
                    window.print();
                }, 500); // Wait for chart to render just in case
            });
        </script>
    </body>

    </html>
    <?php
    exit;
}

if (strpos($page, 'api/') === 0) {
    header('Content-Type: application/json');
    if ($page === 'api/hosts-public') {
        $hosts = Host::getAll();
        // Remove IP for public dashboard
        foreach ($hosts as &$h) {
            unset($h['ip']);
        }
        echo json_encode($hosts);
        exit;
    }

    Auth::requireLogin();
    if ($page === 'api/map-config') {
        $config = require __DIR__ . '/../config.php';
        echo json_encode(['carto_api_key' => $config['carto_api_key'] ?? '']);
    } elseif ($page === 'api/hosts') {
        echo json_encode(Host::getAll());
    } elseif ($page === 'api/metrics' && isset($_GET['id'])) {
        $period = $_GET['period'] ?? 1;
        echo json_encode(PingData::getMetrics($_GET['id'], $period));
    } elseif ($page === 'api/history') {
        $hostId = isset($_GET['id']) ? (int) $_GET['id'] : null;
        echo json_encode(PingData::getDowntimeHistory($hostId));
    } elseif ($page === 'api/history-archived') {
        $hostId = isset($_GET['id']) ? (int) $_GET['id'] : null;
        echo json_encode(PingData::getArchivedDowntimeHistory($hostId));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $token = $_POST['cf-turnstile-response'] ?? null;

    $result = Auth::login($username, $password, $token);
    if ($result['success']) {
        $redirect = $_SESSION['redirect_to'] ?? '?page=dashboard';
        unset($_SESSION['redirect_to']);

        if (strpos($redirect, '/') === 0) {
            $settings = \PingApp\Settings::getAll();
            if (!empty($settings['app_url'])) {
                $appUrl = rtrim($settings['app_url'], '/');
                $redirect = $appUrl . $redirect;
            }
        }

        header("Location: " . $redirect);
        exit;
    } else {
        $loginError = $result['error'];
    }
}

if ($page === 'logout') {
    Auth::logout();
}

if ($page !== 'login' && $page !== 'public') {
    if (!Auth::isLoggedIn()) {
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        header("Location: ?page=login");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'settings') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_host') {
            $ip = $_POST['ip'];
            $existingHost = Host::getByIp($ip);
            if ($existingHost) {
                $_SESSION['settings_error'] = Lang::get('host_exists_ip');
            } else {
                $lat = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
                $lng = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
                Host::add($_POST['name'], $ip, $lat, $lng);
            }
        } elseif ($_POST['action'] === 'edit_host') {
            $lat = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
            $lng = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
            Host::update($_POST['id'], $_POST['name'], $_POST['ip'], $lat, $lng);
        } elseif ($_POST['action'] === 'toggle_active_host') {
            Host::toggleActive($_POST['id'], $_POST['active']);
        } elseif ($_POST['action'] === 'delete_host') {
            Host::delete($_POST['id']);
        } elseif ($_POST['action'] === 'update_settings') {
            if (!Auth::isAdmin())
                die('Unauthorized');
            $data = [
                'ping_interval' => (int) $_POST['ping_interval'],
                'ping_count' => (int) $_POST['ping_count'],
                'turnstile_enabled' => isset($_POST['turnstile_enabled']) ? '1' : '0',
                'turnstile_site_key' => $_POST['turnstile_site_key'],
                'turnstile_secret_key' => $_POST['turnstile_secret_key'],
                'waha_enabled' => isset($_POST['waha_enabled']) ? '1' : '0',
                'waha_url' => $_POST['waha_url'],
                'waha_api_key' => $_POST['waha_api_key'],
                'waha_chat_id' => $_POST['waha_chat_id'],
                'waha_session' => $_POST['waha_session'],
                'app_url' => $_POST['app_url'] ?? '',
            ];

            $audioDir = __DIR__ . '/audio/';
            if (!is_dir($audioDir)) {
                mkdir($audioDir, 0755, true);
            }

            $uploadError = '';

            // Function to get PHP upload error message
            $getUploadErrorMsg = function ($code) {
                switch ($code) {
                    case UPLOAD_ERR_INI_SIZE:
                        return "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                    case UPLOAD_ERR_FORM_SIZE:
                        return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                    case UPLOAD_ERR_PARTIAL:
                        return "The uploaded file was only partially uploaded.";
                    case UPLOAD_ERR_NO_FILE:
                        return "No file was uploaded.";
                    case UPLOAD_ERR_NO_TMP_DIR:
                        return "Missing a temporary folder.";
                    case UPLOAD_ERR_CANT_WRITE:
                        return "Failed to write file to disk.";
                    case UPLOAD_ERR_EXTENSION:
                        return "A PHP extension stopped the file upload.";
                    default:
                        return "Unknown upload error.";
                }
            };

            if (isset($_FILES['audio_normal'])) {
                if ($_FILES['audio_normal']['error'] === UPLOAD_ERR_OK) {
                    @unlink(__DIR__ . '/audio/alert_normal.mp3');
                    @unlink(__DIR__ . '/audio/alert_normal.wav');
                    $ext = strtolower(pathinfo($_FILES['audio_normal']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['mp3', 'wav'])) {
                        if ($_FILES['audio_normal']['size'] <= 2097152) {
                            $path = 'audio/alert_normal.' . $ext;
                            if (move_uploaded_file($_FILES['audio_normal']['tmp_name'], __DIR__ . '/' . $path)) {
                                $data['audio_normal'] = $path;
                            } else {
                                $uploadError .= "Failed to write normal audio to disk. Check permissions. ";
                            }
                        } else {
                            $uploadError .= "Normal audio is too large (Max 2MB). ";
                        }
                    } else {
                        $uploadError .= "Normal audio must be MP3 or WAV. ";
                    }
                } elseif ($_FILES['audio_normal']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadError .= "Normal Audio Error: " . $getUploadErrorMsg($_FILES['audio_normal']['error']) . " ";
                }
            }

            if (!empty($_POST['delete_audio_normal'])) {
                @unlink(__DIR__ . '/audio/alert_normal.mp3');
                @unlink(__DIR__ . '/audio/alert_normal.wav');
                $data['audio_normal'] = '';
            }

            if (isset($_FILES['audio_critical'])) {
                if ($_FILES['audio_critical']['error'] === UPLOAD_ERR_OK) {
                    @unlink(__DIR__ . '/audio/alert_critical.mp3');
                    @unlink(__DIR__ . '/audio/alert_critical.wav');
                    $ext = strtolower(pathinfo($_FILES['audio_critical']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['mp3', 'wav'])) {
                        if ($_FILES['audio_critical']['size'] <= 2097152) {
                            $path = 'audio/alert_critical.' . $ext;
                            if (move_uploaded_file($_FILES['audio_critical']['tmp_name'], __DIR__ . '/' . $path)) {
                                $data['audio_critical'] = $path;
                            } else {
                                $uploadError .= "Failed to write critical audio to disk. Check permissions. ";
                            }
                        } else {
                            $uploadError .= "Critical audio is too large (Max 2MB). ";
                        }
                    } else {
                        $uploadError .= "Critical audio must be MP3 or WAV. ";
                    }
                } elseif ($_FILES['audio_critical']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadError .= "Critical Audio Error: " . $getUploadErrorMsg($_FILES['audio_critical']['error']) . " ";
                }
            }

            if (!empty($_POST['delete_audio_critical'])) {
                @unlink(__DIR__ . '/audio/alert_critical.mp3');
                @unlink(__DIR__ . '/audio/alert_critical.wav');
                $data['audio_critical'] = '';
            }

            Settings::updateMultiple($data);
            $_SESSION['settings_success'] = "Settings saved successfully.";
            if ($uploadError) {
                $_SESSION['settings_error'] = trim($uploadError);
            }
        } elseif ($_POST['action'] === 'import_csv' && isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file']['tmp_name'];
            $importErrors = [];
            $rowNum = 1;
            if (($handle = fopen($file, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 2) {
                        $ip = trim($data[0] ?? '');
                        $name = trim($data[1] ?? '');
                        $lat = isset($data[2]) ? trim($data[2]) : null;
                        $lng = isset($data[3]) ? trim($data[3]) : null;

                        if (empty($ip) || empty($name)) {
                            $importErrors[] = "Linha $rowNum: IP e Nome são obrigatórios.";
                        } else {
                            if ($lat !== null && $lat !== '' && !is_numeric($lat)) {
                                $lat = null;
                            } elseif ($lat === '') {
                                $lat = null;
                            }
                            if ($lng !== null && $lng !== '' && !is_numeric($lng)) {
                                $lng = null;
                            } elseif ($lng === '') {
                                $lng = null;
                            }

                            $existingHost = Host::getByIp($ip);
                            if ($existingHost) {
                                Host::update($existingHost['id'], $name, $ip, $lat, $lng);
                            } else {
                                Host::add($name, $ip, $lat, $lng);
                            }
                        }
                    } else {
                        if (count($data) > 0 && implode('', $data) !== '') {
                            $importErrors[] = "Linha $rowNum: Formato inválido (requer pelo menos IP, NOME).";
                        }
                    }
                    $rowNum++;
                }
                fclose($handle);
                if (!empty($importErrors)) {
                    $_SESSION['settings_error'] = "Erros na importação do CSV:<br>" . implode("<br>", $importErrors);
                } else {
                    $_SESSION['settings_success'] = "CSV importado com sucesso.";
                }
            } else {
                $_SESSION['settings_error'] = "Falha ao abrir o arquivo enviado.";
            }
        } elseif ($_POST['action'] === 'add_user') {
            if (!Auth::isAdmin())
                die('Unauthorized');
            Auth::addUser($_POST['new_username'], $_POST['new_password'], $_POST['role'] ?? 'user');
        } elseif ($_POST['action'] === 'delete_user') {
            if (!Auth::isAdmin())
                die('Unauthorized');
            Auth::deleteUser($_POST['user_id']);
        } elseif ($_POST['action'] === 'change_password') {
            $success = Auth::changePassword($_SESSION['user_id'], $_POST['current_password'], $_POST['new_password']);
            if (!$success) {
                $_SESSION['settings_error'] = "Current password was incorrect.";
            } else {
                $_SESSION['settings_success'] = "Password updated successfully.";
            }
        } elseif ($_POST['action'] === 'clear_stats') {
            if (!Auth::isAdmin())
                die('Unauthorized');
            $db = Database::getConnection();
            $db->exec("TRUNCATE TABLE ping_results");
            $db->exec("TRUNCATE TABLE host_downtimes");
            $db->exec("TRUNCATE TABLE archived_host_downtimes");
            $_SESSION['settings_success'] = "Statistics and history cleared successfully.";
        } elseif ($_POST['action'] === 'factory_reset') {
            if (!Auth::isAdmin())
                die('Unauthorized');
            $db = Database::getConnection();
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $db->exec("TRUNCATE TABLE ping_results");
            $db->exec("TRUNCATE TABLE host_downtimes");
            $db->exec("TRUNCATE TABLE archived_host_downtimes");
            $db->exec("TRUNCATE TABLE hosts");
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            $_SESSION['settings_success'] = "Factory reset completed successfully.";
        }
    }
    header("Location: ?page=settings");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BNET Ping Monitor</title>
    <link rel="stylesheet" href="css/style.css?v=1.6">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php
    $settings = Settings::getAll();
    if ($page === 'login' && isset($settings['turnstile_enabled']) && $settings['turnstile_enabled'] == '1') {
        echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }
    ?>
    <script>
        window.i18n = <?= json_encode(\PingApp\Lang::getJsTranslations()) ?>;
        window.appSettings = {
            audio_normal: <?= json_encode($settings['audio_normal'] ?? null) ?>,
            audio_critical: <?= json_encode($settings['audio_critical'] ?? null) ?>
        };
    </script>
</head>

<body>
    <?php if ($page !== 'login' && $page !== 'public'): ?>
        <nav class="navbar">
            <div class="nav-brand">
                <img src="img/logo.png" alt="<?= Lang::get('bnet_monitor') ?>"
                    style="height: 35px; width: auto; max-width: 200px; object-fit: contain;"
                    onerror="this.outerHTML='<?= Lang::get('bnet_monitor') ?>'">
            </div>
            <div class="nav-links">
                <a href="?page=dashboard"
                    class="<?= $page === 'dashboard' ? 'active' : '' ?>"><?= Lang::get('nav_dashboard') ?></a>
                <a href="?page=map" class="<?= $page === 'map' ? 'active' : '' ?>"><?= Lang::get('nav_map') ?></a>
                <a href="?page=history"
                    class="<?= $page === 'history' ? 'active' : '' ?>"><?= Lang::get('nav_history') ?></a>
                <a href="?page=reports"
                    class="<?= $page === 'reports' ? 'active' : '' ?>"><?= Lang::get('nav_reports') ?></a>
                <a href="?page=settings"
                    class="<?= $page === 'settings' ? 'active' : '' ?>"><?= Lang::get('nav_settings') ?></a>
                <div
                    style="display:inline-block; margin-left:1rem; border-left:1px solid var(--border); padding-left:1rem;">
                    <a href="?page=set_lang&lang=en"
                        style="padding:0.2rem 0.5rem; color:<?= Lang::getLanguage() === 'en' ? 'var(--accent)' : 'var(--text)' ?>;">EN</a>
                    |
                    <a href="?page=set_lang&lang=pt"
                        style="padding:0.2rem 0.5rem; color:<?= Lang::getLanguage() === 'pt' ? 'var(--accent)' : 'var(--text)' ?>;">PT</a>
                </div>
                <a href="?page=logout" class="logout-btn"><?= Lang::get('nav_logout') ?></a>
            </div>
        </nav>
    <?php endif; ?>

    <main class="<?= $page === 'public' ? 'public-container' : 'container' ?>">
        <?php if ($page === 'login'): ?>
            <div class="login-container">
                <div class="login-box">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <img src="img/logo.png" alt="Logo"
                            style="height: 80px; width: auto; max-width: 100%; object-fit: contain;"
                            onerror="this.style.display='none'">
                    </div>
                    <h2 style="text-align: center; margin-top: 0;"><?= Lang::get('login_welcome') ?></h2>
                    <?php if (isset($loginError)): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($loginError) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="?page=login">
                        <div class="form-group">
                            <label><?= Lang::get('login_username') ?></label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label><?= Lang::get('login_password') ?></label>
                            <input type="password" name="password" required>
                        </div>
                        <?php if (isset($settings['turnstile_enabled']) && $settings['turnstile_enabled'] == '1'): ?>
                            <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($settings['turnstile_site_key']) ?>">
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-block"><?= Lang::get('login_btn') ?></button>
                    </form>
                </div>
            </div>
        <?php elseif ($page === 'dashboard'): ?>
            <div class="dashboard-header">
                <h2><?= Lang::get('dash_overview') ?></h2>
                <div class="dashboard-controls" style="display: flex; gap: 1rem;">
                    <select id="sortSelect" class="form-select">
                        <option value="name"><?= Lang::get('sort_name') ?></option>
                        <option value="ip"><?= Lang::get('sort_ip') ?></option>
                        <option value="status"><?= Lang::get('sort_offline_first') ?></option>
                    </select>
                    <input type="text" id="searchInput" placeholder="<?= Lang::get('search_placeholder') ?>"
                        class="search-input">
                    <a href="?page=public" class="btn btn-primary" target="_blank" title="<?= Lang::get('tv_view') ?>"
                        style="white-space: nowrap;"><?= Lang::get('tv_view') ?></a>
                </div>
            </div>
            <div class="hosts-grid" id="hostsGrid">
                <!-- Populated by JS -->
            </div>
        <?php elseif ($page === 'public'): ?>
            <div class="public-header"
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem; padding: 0.5rem 1rem; background: var(--bg-card); border-bottom: 1px solid var(--border);">
                <div class="nav-brand" style="margin: 0; padding: 0;">
                    <img src="img/logo.png" alt="<?= Lang::get('bnet_monitor_public') ?>"
                        style="height: 30px; width: auto; max-width: 150px; object-fit: contain;"
                        onerror="this.outerHTML='<h1 style=\'margin: 0; font-size: 1.1rem;\'><?= Lang::get('bnet_monitor_public') ?></h1>'">
                </div>
                <div style="display:flex; gap: 0.5rem; align-items:center; flex-wrap: wrap; justify-content: flex-end;">
                    <select id="sortSelect" class="form-select"
                        style="width: auto; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.8rem; background: var(--bg-input); color: white; border: 1px solid var(--border);">
                        <option value="status"><?= Lang::get('sort_offline_first') ?></option>
                        <option value="name"><?= Lang::get('sort_name') ?></option>
                    </select>
                    <button id="tvModeBtn" class="btn btn-secondary btn-sm"
                        style="padding: 0.25rem 0.5rem; font-size: 0.8rem; white-space: nowrap;"><?= Lang::get('tv_view') ?>:
                        ON</button>
                    <button id="enableAudioBtn" class="btn btn-primary btn-sm"
                        style="padding: 0.25rem 0.5rem; font-size: 0.8rem; white-space: nowrap;"><?= Lang::get('enable_audio') ?></button>
                    <div id="hostSummary"
                        style="font-size: 0.8rem; font-weight: bold; display: flex; gap: 0.5rem; white-space: nowrap;">
                    </div>
                    <span id="clock"
                        style="font-size: 1rem; font-weight: bold; white-space: nowrap; color: #38bdf8;">--:--:--</span>
                </div>
            </div>
            <div class="public-grid" id="publicGrid"
                style="display: grid; height: calc(100vh - 45px); gap: 0.4rem; padding: 0 0.5rem 0.5rem 0.5rem; overflow: hidden;">
                <!-- Populated by JS -->
            </div>
            <script src="js/public.js?v=1.7"></script>
        <?php elseif ($page === 'map'): ?>
            <div class="dashboard-header">
                <h2><?= Lang::get('nav_map') ?></h2>
                <div class="dashboard-controls" style="display: flex; gap: 1rem;">
                    <select id="mapStatusFilter" class="form-select">
                        <option value="all"><?= Lang::get('filter_all') ?></option>
                        <option value="offline"><?= Lang::get('offline') ?></option>
                        <option value="online"><?= Lang::get('online') ?></option>
                    </select>
                    <input type="text" id="mapSearchInput" placeholder="<?= Lang::get('search_placeholder') ?>"
                        class="search-input" style="min-width: 250px;">
                    <button class="btn btn-secondary" onclick="mapRefresh()" title="<?= Lang::get('btn_refresh') ?>"
                        style="display: flex; align-items: center; justify-content: center; padding: 0.5rem 0.8rem; border-radius: 6px;">
                        <svg id="mapRefreshIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" style="transition: opacity 0.3s ease;">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
            <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
            <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
            <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
            <div id="map"
                style="width: 100%; height: calc(90vh - 150px); border-radius: 8px; border: 1px solid var(--border); z-index: 1;">
            </div>
            <style>
                .host-tooltip {
                    background: transparent;
                    border: none;
                    box-shadow: none;
                    color: white;
                    font-weight: 600;
                    font-size: 0.85rem;
                    text-shadow: 1px 1px 2px black, -1px -1px 2px black, 1px -1px 2px black, -1px 1px 2px black;
                }

                .leaflet-tooltip-left.host-tooltip::before,
                .leaflet-tooltip-right.host-tooltip::before,
                .leaflet-tooltip-top.host-tooltip::before,
                .leaflet-tooltip-bottom.host-tooltip::before {
                    display: none;
                }

                @keyframes pulseGreen {
                    0% {
                        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
                    }

                    70% {
                        box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
                    }

                    100% {
                        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
                    }
                }

                @keyframes pulseRed {
                    0% {
                        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
                    }

                    70% {
                        box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
                    }

                    100% {
                        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
                    }
                }

                .custom-div-icon {
                    overflow: visible !important;
                }
            </style>
            <script src="js/map.js?v=1.3"></script>
        <?php elseif ($page === 'host' && isset($_GET['id'])):
            $host = Host::getById($_GET['id']);
            if (!$host)
                die('Host not found');
            ?>
            <div class="host-details-header">
                <h2><?= htmlspecialchars($host['name']) ?> <span
                        class="host-ip">(<?= htmlspecialchars($host['ip']) ?>)</span></h2>
                <div class="period-selector">
                    <a href="?page=host&id=<?= $host['id'] ?>&period=1"
                        class="btn <?= (!isset($_GET['period']) || $_GET['period'] == 1) ? 'btn-active' : '' ?>"><?= Lang::get('period_1h') ?></a>
                    <a href="?page=host&id=<?= $host['id'] ?>&period=24"
                        class="btn <?= (isset($_GET['period']) && $_GET['period'] == 24) ? 'btn-active' : '' ?>"><?= Lang::get('period_24h') ?></a>
                    <a href="?page=host&id=<?= $host['id'] ?>&period=168"
                        class="btn <?= (isset($_GET['period']) && $_GET['period'] == 168) ? 'btn-active' : '' ?>"><?= Lang::get('period_1w') ?></a>
                    <a href="?page=host&id=<?= $host['id'] ?>&period=720"
                        class="btn <?= (isset($_GET['period']) && $_GET['period'] == 720) ? 'btn-active' : '' ?>"><?= Lang::get('period_1m') ?></a>
                </div>
            </div>
            <div class="charts-container">
                <div class="chart-box">
                    <canvas id="latencyChart"></canvas>
                </div>
                <div class="chart-box">
                    <canvas id="packetLossChart"></canvas>
                </div>
                <div class="chart-box">
                    <canvas id="jitterChart"></canvas>
                </div>
            </div>

            <div class="history-table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 id="hostHistoryTitle" style="margin: 0; padding: 0; border: none;">
                        <?= Lang::get('host_history_title') ?>
                    </h3>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <button id="toggleArchiveHostBtn" class="btn btn-primary btn-sm"
                            style="white-space: nowrap;"><?= Lang::get('btn_old_downtimes') ?></button>
                        <input type="text" id="hostHistorySearch" placeholder="<?= Lang::get('search_date_time') ?>"
                            class="search-input" style="max-width: 250px;">
                    </div>
                </div>
                <table class="history-table" id="hostHistoryTable">
                    <thead>
                        <tr>
                            <th style="cursor:pointer; user-select:none;" data-sort="date">
                                <?= Lang::get('date_time_from') ?> ↕
                            </th>
                            <th><?= Lang::get('date_time_to') ?></th>
                            <th><?= Lang::get('duration') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" class="text-muted text-center"><?= Lang::get('loading') ?></td>
                        </tr>
                    </tbody>
                </table>
                <div id="hostHistoryPagination"
                    style="margin-top: 1rem; display: flex; justify-content: center; gap: 0.5rem; align-items: center;">
                </div>
            </div>

            <script>
                const hostId = <?= $host['id'] ?>;
                const period = <?= $_GET['period'] ?? 1 ?>;
            </script>
            <script src="js/host.js?v=1.3"></script>
        <?php elseif ($page === 'reports'):
            $hosts = Host::getAll();
            ?>
            <div class="dashboard-header">
                <h2><?= Lang::get('report_gen_title') ?></h2>
            </div>

            <div class="settings-card">
                <h3><?= Lang::get('report_export_data') ?></h3>
                <form id="reportForm" method="GET" action="?">
                    <input type="hidden" name="page" id="reportPageAction" value="export_csv">

                    <div class="form-group">
                        <label><?= Lang::get('report_type') ?></label>
                        <select name="type" class="form-select" style="width: 100%;">
                            <option value="downtime"><?= Lang::get('report_downtime') ?></option>
                            <option value="stats"><?= Lang::get('report_stats') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?= Lang::get('report_target_host') ?></label>
                        <select name="host_id" class="form-select" style="width: 100%;">
                            <option value=""><?= Lang::get('report_all_hosts') ?></option>
                            <?php foreach ($hosts as $h): ?>
                                <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?>
                                    (<?= htmlspecialchars($h['ip']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="display: flex; gap: 1rem;">
                        <div style="flex: 1;">
                            <label><?= Lang::get('report_start_date') ?></label>
                            <input type="datetime-local" name="start_date" class="form-control"
                                style="width: 100%; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid var(--border); background: var(--bg-input); color: white;">
                        </div>
                        <div style="flex: 1;">
                            <label><?= Lang::get('report_end_date') ?></label>
                            <input type="datetime-local" name="end_date" class="form-control"
                                style="width: 100%; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid var(--border); background: var(--bg-input); color: white;">
                        </div>
                    </div>

                    <div class="form-group" style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="button" class="btn btn-primary" onclick="submitReport('export_pdf')"
                            style="flex: 1;"><?= Lang::get('btn_gen_pdf') ?></button>
                        <button type="button" class="btn btn-success" onclick="submitReport('export_csv')"
                            style="flex: 1;"><?= Lang::get('btn_exp_csv') ?></button>
                    </div>
                </form>
            </div>
            <script>
                function submitReport(actionPage) {
                    const form = document.getElementById('reportForm');
                    document.getElementById('reportPageAction').value = actionPage;
                    if (actionPage === 'export_pdf') {
                        form.target = '_blank'; // open PDF generator in new tab so it can download
                    } else {
                        form.target = '_self';
                    }
                    form.submit();
                }
            </script>
        <?php elseif ($page === 'settings'): ?>
            <?php if (isset($_SESSION['settings_error'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($_SESSION['settings_error']);
                    unset($_SESSION['settings_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['settings_success'])): ?>
                <div class="alert"
                    style="background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2);">
                    <?= htmlspecialchars($_SESSION['settings_success']);
                    unset($_SESSION['settings_success']); ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <?php if (Auth::isAdmin()): ?>
                    <button class="tab-button active"
                        onclick="openTab(event, 'sysConfig')"><?= Lang::get('tab_sys_config') ?></button>
                <?php endif; ?>
                <button class="tab-button <?= Auth::isAdmin() ? '' : 'active' ?>"
                    onclick="openTab(event, 'hostMgmt')"><?= Lang::get('tab_host_mgmt') ?></button>
                <button class="tab-button" onclick="openTab(event, 'userMgmt')"><?= Lang::get('tab_user_mgmt') ?></button>
            </div>

            <?php if (Auth::isAdmin()): ?>
                <div id="sysConfig" class="tab-content active">
                    <!-- System Config -->
                    <div class="settings-card">
                        <h3><?= Lang::get('tab_sys_config') ?></h3>
                        <form method="POST" action="?page=settings" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_settings">
                            <div class="form-group">
                                <label><?= Lang::get('sys_ping_interval') ?></label>
                                <input type="number" name="ping_interval"
                                    value="<?= htmlspecialchars($settings['ping_interval'] ?? '60') ?>" min="10">
                            </div>
                            <div class="form-group">
                                <label><?= Lang::get('sys_ping_count') ?></label>
                                <input type="number" name="ping_count"
                                    value="<?= htmlspecialchars($settings['ping_count'] ?? '4') ?>" min="1" max="10">
                            </div>

                            <div class="divider"></div>
                            <h4><?= Lang::get('sys_cf_turnstile') ?></h4>
                            <div class="form-group toggle-group">
                                <label class="switch">
                                    <input type="checkbox" name="turnstile_enabled" <?= (isset($settings['turnstile_enabled']) && $settings['turnstile_enabled'] == '1') ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span><?= Lang::get('sys_cf_enable') ?></span>
                            </div>
                            <div class="form-group">
                                <label><?= Lang::get('sys_site_key') ?></label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <input type="password" name="turnstile_site_key" id="ts_site_key"
                                        value="<?= htmlspecialchars($settings['turnstile_site_key'] ?? '') ?>"
                                        style="width: 100%; padding-right: 40px;">
                                    <button type="button" onclick="togglePassword('ts_site_key')"
                                        style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: var(--text);">👁️</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><?= Lang::get('sys_secret_key') ?></label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <input type="password" name="turnstile_secret_key" id="ts_sec_key"
                                        value="<?= htmlspecialchars($settings['turnstile_secret_key'] ?? '') ?>"
                                        style="width: 100%; padding-right: 40px;">
                                    <button type="button" onclick="togglePassword('ts_sec_key')"
                                        style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: var(--text);">👁️</button>
                                </div>
                            </div>

                            <div class="divider"></div>
                            <h4><?= Lang::get('sys_waha') ?></h4>
                            <div class="form-group toggle-group">
                                <label class="switch">
                                    <input type="checkbox" name="waha_enabled" <?= (isset($settings['waha_enabled']) && $settings['waha_enabled'] == '1') ? 'checked' : '' ?>>
                                    <span class="slider round"></span>
                                </label>
                                <span><?= Lang::get('sys_waha_enable') ?></span>
                            </div>
                            <div class="form-group">
                                <label><?= Lang::get('sys_api_url') ?></label>
                                <input type="text" name="waha_url" value="<?= htmlspecialchars($settings['waha_url'] ?? '') ?>"
                                    placeholder="http://localhost:3001/api/sendText">
                            </div>
                            <div class="form-group">
                                <label><?= Lang::get('sys_api_key') ?></label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <input type="password" name="waha_api_key" id="waha_api_key_input"
                                        value="<?= htmlspecialchars($settings['waha_api_key'] ?? '') ?>"
                                        style="width: 100%; padding-right: 40px;">
                                    <button type="button" onclick="togglePassword('waha_api_key_input')"
                                        style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: var(--text);">👁️</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><?= Lang::get('sys_chat_id') ?></label>
                                <input type="text" name="waha_chat_id"
                                    value="<?= htmlspecialchars($settings['waha_chat_id'] ?? '') ?>"
                                    placeholder="xxxxxxxx@g.us">
                            </div>
                            <div class="form-group">
                                <label><?= Lang::get('sys_session_name') ?></label>
                                <input type="text" name="waha_session"
                                    value="<?= htmlspecialchars($settings['waha_session'] ?? 'default') ?>">
                            </div>

                            <div class="divider"></div>
                            <h4><?= Lang::get('sys_audio_alerts') ?></h4>
                            <div class="form-group">
                                <label><?= Lang::get('sys_audio_normal') ?></label>
                                <input type="file" name="audio_normal" accept="audio/mpeg, audio/wav"
                                    style="color: var(--text);">
                                <?php if (!empty($settings['audio_normal'])): ?>
                                    <small style="display:block; margin-top:0.5rem; color:var(--success);">Current:
                                        <?= htmlspecialchars($settings['audio_normal']) ?></small>
                                    <label
                                        style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; font-size: 0.9em; font-weight: normal; cursor: pointer;">
                                        <input type="checkbox" name="delete_audio_normal" value="1">
                                        <?= Lang::get('btn_delete') ?? 'Delete current audio' ?>
                                    </label>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label><?= Lang::get('sys_audio_critical') ?></label>
                                <input type="file" name="audio_critical" accept="audio/mpeg, audio/wav"
                                    style="color: var(--text);">
                                <?php if (!empty($settings['audio_critical'])): ?>
                                    <small style="display:block; margin-top:0.5rem; color:var(--success);">Current:
                                        <?= htmlspecialchars($settings['audio_critical']) ?></small>
                                    <label
                                        style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; font-size: 0.9em; font-weight: normal; cursor: pointer;">
                                        <input type="checkbox" name="delete_audio_critical" value="1">
                                        <?= Lang::get('btn_delete') ?? 'Delete current audio' ?>
                                    </label>
                                <?php endif; ?>
                            </div>

                            <div class="divider"></div>
                            <h4>Application Settings</h4>
                            <div class="form-group">
                                <label><?= Lang::get('sys_app_url') ?></label>
                                <input type="text" name="app_url"
                                    value="<?= htmlspecialchars($settings['app_url'] ?? 'http://localhost/ping') ?>"
                                    placeholder="http://your-domain.com/ping">
                            </div>

                            <button type="submit" class="btn btn-primary"><?= Lang::get('btn_save_config') ?></button>
                        </form>
                        <script>
                            function togglePassword(id) {
                                const el = document.getElementById(id);
                                if (el.type === 'password') {
                                    el.type = 'text';
                                } else {
                                    el.type = 'password';
                                }
                            }
                        </script>
                        <div class="divider"></div>
                        <h4><?= Lang::get('sys_data_mgmt') ?></h4>
                        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                            <form method="POST" action="?page=settings"
                                onsubmit="return confirm(window.i18n.js_confirm_clear);">
                                <input type="hidden" name="action" value="clear_stats">
                                <button type="submit" class="btn btn-warning"
                                    style="background-color: #f59e0b; color: white;"><?= Lang::get('btn_clear_stats') ?></button>
                            </form>
                            <form method="POST" action="?page=settings"
                                onsubmit="return confirm(window.i18n.js_confirm_reset);">
                                <input type="hidden" name="action" value="factory_reset">
                                <button type="submit" class="btn btn-danger"><?= Lang::get('btn_factory_reset') ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div id="hostMgmt" class="tab-content<?= Auth::isAdmin() ? '' : ' active' ?>">
                <!-- Host Management -->
                <div class="settings-card">
                    <h3><?= Lang::get('tab_host_mgmt') ?></h3>
                    <form method="POST" action="?page=settings" class="add-host-form">
                        <input type="hidden" name="action" value="add_host">
                        <div class="input-group">
                            <input type="text" name="name" placeholder="<?= Lang::get('host_name') ?>" required>
                            <input type="text" name="ip" placeholder="<?= Lang::get('host_ip') ?>" required>
                            <input type="text" name="latitude" placeholder="<?= Lang::get('host_lat') ?>">
                            <input type="text" name="longitude" placeholder="<?= Lang::get('host_lng') ?>">
                            <button type="submit" class="btn btn-success"><?= Lang::get('btn_add_host') ?></button>
                        </div>
                    </form>

                    <div style="margin-bottom: 1rem; margin-top: 1rem;">
                        <input type="text" id="hostMgmtSearch" placeholder="Search hosts by name or IP..."
                            class="search-input" style="width: 100%; max-width: 100%;">
                    </div>
                    <div class="hosts-list">
                        <?php foreach (Host::getAll() as $h): ?>
                            <div class="host-item">
                                <div class="host-info" id="host_info_<?= $h['id'] ?>">
                                    <span class="host-name">
                                        <?= htmlspecialchars($h['name']) ?>
                                        <?php if (isset($h['active']) && $h['active'] == '0')
                                            echo '<span class="text-muted" style="font-size: 0.8em; margin-left: 0.5rem;">(Disabled)</span>'; ?>
                                    </span>
                                    <span class="host-ip text-muted"><?= htmlspecialchars($h['ip']) ?></span>
                                </div>
                                <div class="host-edit-form" id="host_edit_<?= $h['id'] ?>"
                                    style="display:none; flex:1; margin-right:1rem;">
                                    <form method="POST" action="?page=settings" style="display:flex; gap:0.5rem; width:100%;">
                                        <input type="hidden" name="action" value="edit_host">
                                        <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                        <input type="text" name="name" value="<?= htmlspecialchars($h['name']) ?>" required
                                            style="flex:1;">
                                        <input type="text" name="ip" value="<?= htmlspecialchars($h['ip']) ?>" required
                                            style="flex:1;">
                                        <input type="text" name="latitude" value="<?= htmlspecialchars($h['latitude'] ?? '') ?>"
                                            placeholder="<?= Lang::get('host_lat') ?>" style="flex:1; max-width: 150px;">
                                        <input type="text" name="longitude"
                                            value="<?= htmlspecialchars($h['longitude'] ?? '') ?>"
                                            placeholder="<?= Lang::get('host_lng') ?>" style="flex:1; max-width: 150px;">
                                        <button type="submit"
                                            class="btn btn-success btn-sm"><?= Lang::get('btn_save') ?></button>
                                        <button type="button" class="btn btn-danger btn-sm"
                                            onclick="toggleEdit(<?= $h['id'] ?>)"><?= Lang::get('btn_cancel') ?></button>
                                    </form>
                                </div>
                                <div class="host-actions" id="host_actions_<?= $h['id'] ?>" style="display:flex; gap:0.5rem;">
                                    <button type="button" class="btn btn-primary btn-sm"
                                        onclick="toggleEdit(<?= $h['id'] ?>)"><?= Lang::get('btn_edit') ?></button>
                                    <form method="POST" action="?page=settings">
                                        <input type="hidden" name="action" value="toggle_active_host">
                                        <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                        <input type="hidden" name="active"
                                            value="<?= isset($h['active']) && $h['active'] == '0' ? '1' : '0' ?>">
                                        <button type="submit"
                                            class="btn <?= isset($h['active']) && $h['active'] == '0' ? 'btn-success' : 'btn-warning' ?> btn-sm">
                                            <?= isset($h['active']) && $h['active'] == '0' ? Lang::get('btn_enable') : Lang::get('btn_disable') ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="?page=settings"
                                        onsubmit="return confirm(window.i18n.js_confirm_delete_host)">
                                        <input type="hidden" name="action" value="delete_host">
                                        <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                        <button type="submit"
                                            class="btn btn-danger btn-sm"><?= Lang::get('btn_delete') ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <script>
                    document.getElementById('hostMgmtSearch').addEventListener('input', function (e) {
                        const term = e.target.value.toLowerCase();
                        const items = document.querySelectorAll('.hosts-list .host-item');
                        items.forEach(item => {
                            const name = item.querySelector('.host-name').textContent.toLowerCase();
                            const ip = item.querySelector('.host-ip').textContent.toLowerCase();
                            if (name.includes(term) || ip.includes(term)) {
                                item.style.display = 'flex';
                            } else {
                                item.style.display = 'none';
                            }
                        });
                    });

                    function toggleEdit(id) {
                        const info = document.getElementById('host_info_' + id);
                        const edit = document.getElementById('host_edit_' + id);
                        const actions = document.getElementById('host_actions_' + id);

                        if (info.style.display === 'none') {
                            info.style.display = 'flex';
                            actions.style.display = 'flex';
                            edit.style.display = 'none';
                        } else {
                            info.style.display = 'none';
                            actions.style.display = 'none';
                            edit.style.display = 'flex';
                        }
                    }
                </script>

                <!-- Import/Export Hosts -->
                <div class="settings-card" style="margin-top: 2rem;">
                    <h3 style="margin-top:0;"><?= Lang::get('host_import_title') ?></h3>
                    <p class="text-muted mb-3"><?= Lang::get('host_import_format') ?></p>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <form method="POST" action="?page=settings" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="import_csv">
                            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                <input type="file" name="csv_file" accept=".csv" required class="file-input"
                                    style="flex: 1; min-width: 200px;">
                                <button type="submit" class="btn btn-primary"><?= Lang::get('btn_import_csv') ?></button>
                            </div>
                        </form>

                        <div>
                            <a href="?page=export_hosts_csv" class="btn btn-primary"
                                style="display: inline-block;"><?= Lang::get('btn_export_csv') ?></a>
                        </div>
                    </div>
                </div>
            </div>

            <div id="userMgmt" class="tab-content">
                <!-- User Management -->
                <div class="settings-card">
                    <h3><?= Lang::get('tab_user_mgmt') ?></h3>

                    <h4 style="margin-top:0;"><?= Lang::get('user_change_password') ?></h4>
                    <form method="POST" action="?page=settings">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-group">
                            <input type="password" name="current_password"
                                placeholder="<?= Lang::get('user_current_password') ?>" required>
                        </div>
                        <div class="form-group">
                            <input type="password" name="new_password" placeholder="<?= Lang::get('user_new_password') ?>"
                                required>
                        </div>
                        <button type="submit"
                            class="btn btn-primary btn-sm"><?= Lang::get('btn_update_password') ?></button>
                    </form>

                    <?php if (Auth::isAdmin()): ?>
                        <div class="divider"></div>
                        <h4><?= Lang::get('user_add_new') ?></h4>
                        <form method="POST" action="?page=settings">
                            <input type="hidden" name="action" value="add_user">
                            <div class="input-group">
                                <input type="text" name="new_username" placeholder="<?= Lang::get('user_username') ?>" required>
                                <input type="password" name="new_password" placeholder="<?= Lang::get('user_password') ?>"
                                    required>
                                <select name="role" class="form-select" required>
                                    <option value="user"><?= Lang::get('role_user') ?></option>
                                    <option value="admin"><?= Lang::get('role_admin') ?></option>
                                </select>
                                <button type="submit" class="btn btn-success"><?= Lang::get('btn_add_user') ?></button>
                            </div>
                        </form>

                        <h4 style="margin-bottom: 0.5rem;"><?= Lang::get('user_existing') ?></h4>
                        <div class="hosts-list" style="max-height: 200px;">
                            <?php foreach (Auth::getAllUsers() as $u): ?>
                                <div class="host-item" style="padding: 0.5rem 1rem;">
                                    <span><?= htmlspecialchars($u['username']) ?> <small
                                            class="text-muted">(<?= htmlspecialchars($u['role'] === 'admin' ? Lang::get('role_admin') : Lang::get('role_user')) ?>)</small></span>
                                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                        <form method="POST" action="?page=settings"
                                            onsubmit="return confirm(window.i18n.js_confirm_delete_user)">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"><?= Lang::get('btn_delete') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>

            <script>
                function openTab(evt, tabName) {
                    var i, tabcontent, tablinks;
                    tabcontent = document.getElementsByClassName("tab-content");
                    for (i = 0; i < tabcontent.length; i++) {
                        tabcontent[i].classList.remove("active");
                    }
                    tablinks = document.getElementsByClassName("tab-button");
                    for (i = 0; i < tablinks.length; i++) {
                        tablinks[i].classList.remove("active");
                    }
                    document.getElementById(tabName).classList.add("active");

                    if (evt) {
                        evt.currentTarget.classList.add("active");
                    } else {
                        // find matching button
                        const btns = document.getElementsByClassName("tab-button");
                        for (let b of btns) {
                            if (b.getAttribute('onclick').includes(tabName)) {
                                b.classList.add("active");
                            }
                        }
                    }

                    sessionStorage.setItem('activeSettingsTab', tabName);
                }

                document.addEventListener('DOMContentLoaded', () => {
                    const activeTab = sessionStorage.getItem('activeSettingsTab');
                    if (activeTab) {
                        openTab(null, activeTab);
                    }
                });
            </script>
        <?php elseif ($page === 'history'): ?>
            <div class="dashboard-header">
                <h2 id="globalHistoryTitle"><?= Lang::get('history_title') ?></h2>
                <div class="dashboard-controls" style="display: flex; gap: 1rem; align-items: center;">
                    <button id="toggleArchiveBtn" class="btn btn-primary"
                        style="white-space: nowrap;"><?= Lang::get('btn_old_downtimes') ?></button>
                    <input type="text" id="historySearch"
                        placeholder="<?= Lang::get('search_history_placeholder') ?? 'Search by name, IP or date...' ?>"
                        class="search-input" style="width: 300px;">
                </div>
            </div>
            <div class="history-table-container">
                <table class="history-table" id="globalHistoryTable">
                    <thead>
                        <tr>
                            <th style="cursor:pointer; user-select:none;" data-sort="date">
                                <?= Lang::get('date_time_from') ?> ↕</th>
                            <th><?= Lang::get('date_time_to') ?></th>
                            <th><?= Lang::get('duration') ?></th>
                            <th style="cursor:pointer; user-select:none;" data-sort="name"><?= Lang::get('host_name') ?> ↕
                            </th>
                            <th style="cursor:pointer; user-select:none;" data-sort="ip"><?= Lang::get('host_ip') ?> ↕</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="text-muted text-center"><?= Lang::get('loading') ?></td>
                        </tr>
                    </tbody>
                </table>
                <div id="globalHistoryPagination"
                    style="margin-top: 1rem; display: flex; justify-content: center; gap: 0.5rem; align-items: center;">
                </div>
            </div>
            <script src="js/history.js?v=1.0"></script>
        <?php else: ?>
            <div class="empty-state">
                <h2>404 - Page not found</h2>
                <a href="?page=dashboard" class="btn btn-primary">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($page === 'dashboard'): ?>
        <script src="js/dashboard.js?v=1.3"></script>
    <?php endif; ?>
    <div
        style="position: fixed; bottom: 5px; right: 10px; font-size: 0.7rem; color: rgba(255,255,255,0.3); pointer-events: none; z-index: 9999;">
        Desenvolvido por: Adriano Lerner Biesek | v<?= APP_VERSION ?>
    </div>
</body>

</html>