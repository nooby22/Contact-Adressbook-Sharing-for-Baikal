<?php

namespace Baikal\Core;

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
 * @file SharedReadonlyCardDAVBackend.php
 * @brief Handles shared Adressbooks created by Baïkal Adressbuch-Share-Manager 2026
  *
 * Baïkal Extension 2026: Flexibles Read-Only Contact Sharing V0.2
 * Modifikation für Baikal, um Adressbooks im read-only Modus teilen zu koennen.
 * Erlaubt das Teilen von Adressbuechern ueber eine zusaetzliche Tabelle 'addressbook_shares'.
 *
 *
 * Place the file into the \baikal\Core\Frameworks\Baikal\Core\ folder (rights 644), open it in your browser with admin credentials
 *
 * 1. Dieses Script im Ordner \baikal\Core\Frameworks\Baikal\Core\ (dort liegt auch Server.php) mit Dateirechten 644 abspeichern
 * 2. Das zweite benoetigte Skript, also das modifizierte Server.php Skript, dann dort gegen das originale austauschen (einfach das original Skript in Server.php_ori umbenennen). 
 *	  Das modifizierte Server.php Script liefert Zusatzfunktionen zur Nutzung der angelegten Tabelle und zur Kontrolle der Schreibrechte.
 *    Es werden keinerlei Daten in den "originalen" Baikal Tabellen verändert, die Datenbankstruktur bleibt abseits der Zusatztabelle unveraendert.
 *
*/

use Sabre\CardDAV\Backend\PDO as CardDAVPDO;
use Sabre\DAV\Exception\Forbidden;

class SharedReadonlyCardDAVBackend extends CardDAVPDO {

    protected $currentUserPrincipal = null;

    public function setCurrentUser($principalUri) {
        $this->currentUserPrincipal = $principalUri;
    }

    /**
     * Erweitert die Liste der Adressbücher um die Freigaben mit dem 'shared-' Präfix.
     * Inklusive Error-Handling für den Fall, dass die Zusatztabelle fehlt.
     */
    public function getAddressBooksForUser($principalUri) {
        // Zuerst die eigenen Adressbücher laden
        $addressBooks = parent::getAddressBooksForUser($principalUri);

        try {
            $stmt = $this->pdo->prepare('
                SELECT a.id, a.uri, a.displayname, a.principaluri, a.description, a.synctoken 
                FROM addressbooks a
                JOIN addressbook_shares s ON a.id = s.addressbook_id
                WHERE s.share_with_principal = ?
            ');
            $stmt->execute([$principalUri]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $addressBooks[] = [
                    'id'           => $row['id'],
                    'uri'          => 'shared-' . $row['id'] . '-' . $row['uri'], 
                    'principaluri' => $principalUri,
                    '{DAV:}displayname' => $row['displayname'] . ' (Geteilt)',
                    '{http://sabredav.org/ns}read-only' => true,
                    '{urn:ietf:params:xml:ns:carddav}addressbook-description' => $row['description'],
                    '{http://sabredav.org/ns}sync-token' => $row['synctoken'],
                ];
            }
        } catch (\Exception $e) {
            // Falls die Tabelle 'addressbook_shares' nicht existiert,
            // fangen wir den SQL-Fehler ab. Der Server gibt dann einfach nur
            // die eigenen (nicht geteilten) Adressbücher zurück.
            // Optional: error_log("Baïkal Extension: Tabelle addressbook_shares fehlt.");
        }

        return $addressBooks;
    }


    /**
     * Harte Sperre: Verhindert Schreibvorgänge, wenn 'shared-' in der URL vorkommt.
     */
    protected function checkWritePermissionByUri($addressBookId) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        if (strpos($requestUri, 'shared-') !== false) {
            // Eine detaillierte Meldung hilft dem Client, den Fehler zuzuordnen
            throw new Forbidden('Schreibzugriff verweigert: Dies ist ein geteiltes Adressbuch. Änderungen wurden verworfen.');
        }
    }

    public function updateCard($addressBookId, $cardUri, $cardData) {
        $this->checkWritePermissionByUri($addressBookId);
        return parent::updateCard($addressBookId, $cardUri, $cardData);
    }

    public function createCard($addressBookId, $cardUri, $cardData) {
        $this->checkWritePermissionByUri($addressBookId);
        return parent::createCard($addressBookId, $cardUri, $cardData);
    }

    public function deleteCard($addressBookId, $cardUri) {
        $this->checkWritePermissionByUri($addressBookId);
        return parent::deleteCard($addressBookId, $cardUri);
    }

    /**
     * ACL-Signale: Teilt dem Client (z.B. Thunderbird) proaktiv mit, was er darf.
     */
    public function getPrivileges($nodePath, $principalUri) {
        if (strpos($nodePath, 'shared-') !== false) {
            // Wir lassen {DAV:}write, {DAV:}bind (anlegen) und {DAV:}unbind (löschen) weg.
            // Das signalisiert modernen Clients, die UI auf schreibgeschützt zu stellen.
            return [
                '{DAV:}read', 
                '{DAV:}read-current-user-privilege-set'
            ];
        }
        // Eigene Adressbücher behalten volle Rechte
        return ['{DAV:}all'];
    }
}


