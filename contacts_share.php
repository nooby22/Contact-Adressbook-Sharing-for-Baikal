<?php
/*
 * Copyright (c) 2026 nooby22
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * Lizenz: 
 * Sie dürfen diese Datei gemäß den Bedingungen der GPL-2.0 verwenden, modifizieren und verteilen.
 *
 * Mehr Informationen: www.gnu.org
 *
 * @file contacts_share.php Rev.0.3
 * @brief Baïkal Adressbuch-Share-Manager 2026
 * This script is a UI for sharing addressbooks (with baikal admin credentials)
 * Place the file into the baïkal\html folder (rights 644), open it in your browser with admin credentials
 * 
 * @date 2026-01-03
 */

$revision="0.3";
$configPath = __DIR__ . '/../config/baikal.yaml';

$configPath = realpath(__DIR__ . '/../config/baikal.yaml');
$dbDir      = realpath(__DIR__ . '/../Specific/db/');

if (!$configPath || !file_exists($configPath)) {
    die("Fehler: Konfiguration nicht gefunden unter: " . __DIR__ . '/../Specific/config/baikal.yaml');
}

$configContent = file_get_contents($configPath);

// Hilfsfunktion zum Auslesen der YAML-Werte
function getVal($key, $content) {
    if (preg_match('/^\s*' . $key . '\s*:\s*[\'"]?(.*?)[\'"]?\s*$/m', $content, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

// 2. AUTHENTIFIZIERUNG gegen admin baikal PW Hash
$storedHash = getVal('admin_passwordhash', $configContent);
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Baïkal Admin"');
    header('HTTP/1.0 401 Unauthorized');
    die('Bitte Admin-Login nutzen.');
}

$genHash = hash("sha256", $_SERVER['PHP_AUTH_USER'] . ":BaikalDAV:" . $_SERVER['PHP_AUTH_PW']);
if ($_SERVER['PHP_AUTH_USER'] !== 'admin' || $genHash !== $storedHash) {
    header('HTTP/1.0 401 Unauthorized');
    die('Login fehlgeschlagen.');
}

try {
    // 3. ENTSCHEIDUNGSLOGIK: SQLITE ODER MYSQL BASIEREND AUF dem Wert von backend im YAML File
    $dbType = getVal('backend', $configContent);

    if (strpos($dbType, 'sqlite') !== false) {
        // --- SQLITE MODUS ---
        // Pfad direkt aus YAML extrahieren
        $dbFile = getVal('sqlite_file', $configContent);
        
        // Falls Pfad relativ ist, absolut zum Baikal-Root machen und Doppelslashes fixen
        if ($dbFile && !file_exists($dbFile)) {
             $dbFile = realpath(__DIR__ . '/../') . '/' . $dbFile;
        }
        $dbFile = str_replace('//', '/', $dbFile);
        
        if (!$dbFile || !file_exists($dbFile)) {
            throw new Exception("SQLite-Datei nicht gefunden: " . ($dbFile ?: 'Pfad leer'));
        }

        $pdo = new PDO("sqlite:" . $dbFile);
        $pkType = "INTEGER PRIMARY KEY AUTOINCREMENT";
        $principalType = "TEXT"; // SQLite erlaubt Index auf TEXT
        $insertIgnore = "INSERT OR IGNORE";
        $dbMode = "SQLite (" . basename($dbFile) . ")";
    } else {
        // --- MYSQL MODUS ---
        $mysqlHost = getVal('mysql_host', $configContent);
        $host = $mysqlHost ?: '127.0.0.1';
        $dbname = getVal('mysql_dbname', $configContent);
        $user = getVal('mysql_username', $configContent);
        $pass = getVal('mysql_password', $configContent);
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pkType = "INTEGER PRIMARY KEY AUTO_INCREMENT";
        $principalType = "VARCHAR(255)"; // FIX für MySQL Key Length Error
        $insertIgnore = "INSERT IGNORE";
        $dbMode = "MySQL/MariaDB (Host: $host)";
    }
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. TABELLE & AKTIONEN (Legt ggf. die Tabelle mit den shres an)
    $pdo->exec("CREATE TABLE IF NOT EXISTS addressbook_shares (
        id $pkType,
        addressbook_id INTEGER NOT NULL,
        share_with_principal TEXT NOT NULL,
        UNIQUE (addressbook_id, share_with_principal)
    )");

    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare("$insertIgnore INTO addressbook_shares (addressbook_id, share_with_principal) VALUES (?, ?)");
        $stmt->execute([$_POST['ab_id'], $_POST['principal']]);
    }
    if (isset($_GET['del'])) {
        $stmt = $pdo->prepare("DELETE FROM addressbook_shares WHERE id = ?");
        $stmt->execute([$_GET['del']]);
    }

    // 5. UI
    echo "<h1>Baïkal Contacts Share Manager, Rev. ".$revision."</h1>";
    echo "<p style='background:#dff0d8; padding:10px; border-radius:4px;'>Aktiv: <strong>$dbMode</strong></p>";
    
    echo "<h3>Bestehende Freigaben</h3><table border='1' cellpadding='8' style='border-collapse:collapse; width:100%'>";
    echo "<tr style='background:#f9f9f9'><th>Besitzer</th><th>Adressbuch</th><th>Ziel (Mitarbeiter)</th><th>Aktion</th></tr>";
    
    $shares = $pdo->query("SELECT s.id, a.displayname, a.principaluri, s.share_with_principal 
                           FROM addressbook_shares s 
                           JOIN addressbooks a ON s.addressbook_id = a.id")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($shares as $row) {
        echo "<tr>";
        echo "<td>{$row['principaluri']}</td>"; 
        echo "<td>" . htmlspecialchars($row['displayname']) . "</td>"; 
        echo "<td>➜ {$row['share_with_principal']}</td>";
        echo "<td><a href='?del={$row['id']}' style='color:red;'>Löschen</a></td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3>Neue Freigabe erstellen</h3><form method='post' style='border:1px solid #ccc; padding:20px;'>";
    echo "Adressbuch: <select name='ab_id' style='padding:5px;'>";
    foreach($pdo->query("SELECT id, displayname, principaluri FROM addressbooks") as $a) {
        echo "<option value='{$a['id']}'>" . htmlspecialchars($a['displayname']) . " ({$a['principaluri']})</option>";
    }
    echo "</select> <br><br> Freigeben für: <select name='principal' style='padding:5px;'>";
    foreach($pdo->query("SELECT uri FROM principals WHERE uri LIKE 'principals/%'") as $p) {
        echo "<option value='{$p['uri']}'>{$p['uri']}</option>";
    }
    echo "</select> <br><br> <input type='submit' name='add' value='Speichern' style='padding:8px 16px; cursor:pointer;'>";
    echo "</form>";

} catch (Exception $e) {
    echo "<h4>Verbindungsfehler</h4><p>" . $e->getMessage() . "</p>";
}


