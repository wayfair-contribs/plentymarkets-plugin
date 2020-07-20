# Wayfair-Plugin: Ersteinrichtung

## Voraussetzungen

* [Ein Plentymarkets-System] (https://www.plentymarkets.co.uk).

* Administratorrechte auf dem Plentymarkets-System, auf dem das Wayfair-Plugin verwendet wird
    - Die Einstellung "Zugriff" des Plentymarkets-Benutzers muss "Admin" sein
    - Der Plentymarkets-Benutzer muss in der Lage sein, Plugin-Sets zu ändern

* Aktiver Wayfair-Lieferantenstatus
    * Eine Wayfair-Lieferanten-ID ist erforderlich
    * [Informationen für potenzielle Lieferanten] (https://partners.wayfair.com/d/onboarding/sell-on-wayfair)

* [Wayfair API-Anmeldeinformationen] (Getting_credentials.md).

* [Installation des Wayfair-Plugins] (plugin_installation.md).


## 1. Autorisieren des Wayfair-Plugins für den Zugriff auf Wayfair-Schnittstellen
Nachdem das Plugin in Ihrem Plentymarkets Plugin Set installiert wurde, muss das Plugin so konfiguriert werden, dass beim Herstellen einer Verbindung zu den Wayfair-Schnittstellen die richtigen Anmeldeinformationen verwendet werden.

* ** Das Autorisierungsverfahren muss für jedes Plugin-Set durchgeführt werden, das das Wayfair-Plugin enthält **.
* Durch Kopieren eines Plugin-Sets werden die Autorisierungsinformationen in das neue Plugin-Set kopiert.
* Ein exportiertes oder importiertes Plugin-Set kann die Autorisierungsinformationen enthalten.

Die Autorisierungsschritte sind wie folgt:
1. Gehen Sie auf der Hauptseite von Plentymarkets zu `Plugins` >>` Plugin Set Übersicht`

2. Suchen Sie das Plugin-Set, das mit dem Client verknüpft ist, mit dem Wayfair verwendet wird.

3. Klicken Sie auf die Schaltfläche "Bearbeiten" für das gewünschte Plugin-Set

4. Klicken Sie in der Wayfair-Zeile des Plugin-Sets auf die Schaltfläche "Einstellungen".

4. Gehen Sie im Menü auf der linken Seite zu "Konfiguration" >> "Globale Einstellungen".

5. Geben Sie im Bereich "Lieferanteneinstellungen" die Werte "Client ID" und "Client Secret" ein, die Ihren Wayfair API-Anmeldeinformationen entsprechen

6. Ändern Sie die Einstellung "Modus" in "Live" - ​​siehe [Informationen zum Modus "Test"] (test_mode.md)

7. Klicken Sie in der Symbolleiste über den Einstellungen auf die Schaltfläche "Speichern"

## 2. Aktivieren Sie den Auftragsverweis
Ein Auftragsverweis in Plentymarkets identifiziert den Vertriebskanal, auf dem ein Auftrag generiert wurde. Damit das Plentymarkets-System Bestellungen ordnungsgemäß von der Wayfair-API importieren kann, muss der Wayfair-Auftragsreferent aktiviert sein:

1. Gehen Sie auf der Hauptseite von Plentymarkets zu `Setup` >>` Orders` >> `Order Referrer`.

2. Setzen Sie ein Häkchen neben den Bestellempfänger ** Wayfair **.

3. Klicken Sie auf ** Speichern **.

## 3. Einrichten von Plentymarkets für den Versand über Wayfair
Befolgen Sie die in [den Wayfair-Versandanweisungen] (wayfair_shipping.md) beschriebenen Verfahren, um eine ordnungsgemäße Integration mit Wayfair beim Versand von Bestellartikeln sicherzustellen.

## 4. Auf Wayfair bestellte Artikel mit Artikelvariationen in Plentymarkets abgleichen:
Um eingehende Bestellungen von Wayfair ordnungsgemäß verarbeiten zu können, muss das Wayfair-Plugin die Lieferanten-Teilenummern in Wayfair-Systemen mit einem bestimmten Feld von Artikelvariationen in Plentymarkets abgleichen. Standardmäßig geht das Wayfair-Plugin davon aus, dass die "Variationsnummer" ** (nicht zu verwechseln mit der ID der Variation) ** der Variation eines Artikels in Plentymarkets mit der Teilenummer des Wayfair-Lieferanten übereinstimmt.

Wenn die Wayfair-Lieferanten-Teilenummern für Ihre Organisation in einem alternativen Feld in Ihren Plentymarkets-Artikelvariationen angezeigt werden sollen, ändern Sie den Wert von [Einstellung "Artikelzuordnungsmethode" (settings_guide.md # Artikelzuordnungsmethode) und aktualisieren Sie die Variationen entsprechend.

## 5. Artikel auf Wayfair zum Verkauf anbieten
Artikel, die Sie auf dem Wayfair-Markt verkaufen möchten, müssen in Plentymarkets als aktiv betrachtet werden. Der Benutzer von Plentymarkets kann auch festlegen, welche Artikel auf Wayfair zum Verkauf stehen. ** Beachten Sie, dass der Lagerbestand und die bestellten Artikel auf der Ebene "Variation" gesteuert werden. **

Befolgen Sie diese Anweisungen, um sicherzustellen, dass ein Artikel zum Verkauf angeboten wird:

1. Gehen Sie auf der Hauptseite von Plentymarkets zu `Item` >>` Edit item`

2. Suchen Sie nach Artikeln und öffnen Sie sie

3. ** Klicken Sie für jedes Element ** auf "Variationen" und öffnen Sie sie

4. ** Für jede Variation **:

    1. Stellen Sie auf der Registerkarte "Einstellungen" sicher, dass das Kontrollkästchen "Aktiv" im Abschnitt "Verfügbarkeit" aktiviert ist.

    2. Wenn [die Einstellung "Alle Inventargegenstände an Wayfair senden?" (Settings_guide.md # Alle Inventargegenstände an Wayfair senden) deaktiviert ist, wechseln Sie zur Registerkarte "Verfügbarkeit" der Variation und fügen Sie "Wayfair" zur Liste im Bereich "Märkte" hinzu.

    3. Klicken Sie auf die Schaltfläche "Speichern" neben der Variations-ID (nicht auf die höhere Schaltfläche für das Element).


## 6. Konfigurieren der Warehouse-Zuordnungen so, dass sie mit den Wayfair-Lieferanten-IDs übereinstimmen.

Um die Inventardaten im Wayfair-System zu aktualisieren, müssen Sie die Lager in Ihrem Plentymarkets-System den Lieferanten-IDs im Wayfair-System auf der Seite [Lager] (settings_guide.md # warehouses-page) der [Einstellungen des Plugins] zuordnen. (settings_guide.md).

## 7. Konfigurieren von Plentymarkets zum Senden der Lieferbestätigung (ASN) an Wayfair

### 7.1 Einstellen des Wayfair-Plugins, um die richtigen Versandinformationen an Wayfair zu senden
Benutzer des Wayfair-Plugins, die Bestellungen über ihre eigenen Konten versenden möchten (anstatt die Versanddienste von Wayfair zu nutzen), müssen die Konfigurationseinstellungen für [Versandbestätigung (ASN)] (https://github.com/wayfair-contribs/plentymarkets-plugin/) aktualisieren. blob / master / meta / documents / user_guide / de / settings_guide.md # Schiffsbestätigung-asn-Seite), um ihre spezifische Konfiguration widerzuspiegeln.

Wenn die Versanddienste von Wayfair verwendet werden sollen, sollten die ASN-Einstellungen des Wayfair-Plugins in ihrem Standardstatus ("Wayfair-Versand") belassen werden.

### 7.2 Erstellen eines Ereignisses für Plentymarkets-Bestellungen, das Versandinformationen an Wayfair sendet

1. Klicken Sie in der oberen Navigationsleiste auf ** Setup ** und gehen Sie dann zu ** Bestellungen >> Ereignisse **
! [Ereignis erstellen] (https://i.ibb.co/NjDtY05/asn-02.png "Ereignis erstellen")

2. Klicken Sie auf ** Ereignisprozedur hinzufügen ** (die Schaltfläche "+" links auf der Seite).

3. Geben Sie einen beliebigen ** Namen ** ein

4. Wählen Sie im Feld ** Ereignis ** die Option ** Statusänderung ** (in der Kategorie ** Auftragsänderung **).

5. Wählen Sie im Feld ** Ereignis ** die Statusänderung aus, die das Senden eines Lieferavis an Wayfair auslösen soll, z. B. ** zur Vorbereitung des Versands **.

6. Klicken Sie auf die Schaltfläche ** Speichern **.

7. Sie sollten automatisch zur neu erstellten ** Ereignisprozedur ** umgeleitet werden. Setzen Sie im Abschnitt ** Einstellungen ** der Ereignisprozedur ein Häkchen neben ** Aktiv **.

8. Klicken Sie auf ** Filter hinzufügen ** und gehen Sie zu ** Bestellung >> Referrer **, um den Referrer als Filter hinzuzufügen (siehe Abbildung unten).

! [Event Referrer] (https://i.ibb.co/TwKLvJ5/asn-03.png "Event Referrer")

9. Im Abschnitt ** Filter ** sollte ein Feld mit einer Liste aller verfügbaren Auftragsverweise angezeigt werden. Setzen Sie ein Häkchen neben alle ** Wayfair ** Bestellempfänger.

! [Wayfair Referrer] (https://i.ibb.co/yYpLp8q/asn-04.png "Wayfair Referrer")

10. Klicken Sie auf ** Prozedur hinzufügen ** und gehen Sie zu ** Plugins >> Send Ship Confirmation (ASN) an Wayfair **. Klicken Sie auf ** + hinzufügen **.

! [Prozedur hinzufügen] (https://i.ibb.co/xfGrhFP/asn-05.png "Prozedur hinzufügen")

Das endgültige Einstellungsergebnis sollte ungefähr so ​​aussehen:

! [Prozedurergebnis hinzufügen] (https://i.ibb.co/GJPF3ZV/asn-06.png "Prozedurergebnis hinzufügen")

## 8. Durchführen der ersten Inventarsynchronisation
Sobald alles eingerichtet ist, ist es Zeit, Artikel zum Verkauf auf Wayfair aufzulisten.

Schließen Sie das Setup ab, indem Sie eine vollständige Inventarsynchronisierung auf der Seite [Vollständiges Inventar] (settings_guide.md # vollständige Inventarseite) der [Wayfair-Markteinstellungen] (settings_guide.md) starten.

** Nach dieser ersten Inventarsynchronisierung sendet das Wayfair-Plugin regelmäßig Inventaraktualisierungen an Wayfair, ohne dass weitere manuelle Aktivierungen erforderlich sind. **
