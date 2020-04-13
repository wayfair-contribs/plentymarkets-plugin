
# Wayfair Plugin Benutzerhandbuch
<div class="container-toc"></div>

## 1 Registrierung bei Wayfair

Wayfair ist ein geschlossener Marktplatz. Um dieses Plugin verwenden zu können, müssen Sie bei Wayfair als Lieferant registriert sein. 

Schicken Sie hierfür bitte eine Email an ERPSupport@wayfair.com

**Achtung (März 2020):** Die E-Mail-Adresse ist neu. Bitte aktualisieren Sie Ihre Unterlagen entsprechend.


Nachdem Sie sich erfolgreich bei Wayfair als Anbieter registriert haben, müssen Sie die unten aufgeführten Anweisungen durchlaufen.

Die Installation des Wayfair-Plugins ermöglicht die folgenden automatischen Prozesse:

- Stündlicher Auftragsdatenimport
- Stündliche Bestandsdatenabgleichung
- Generierung von Versandlabels


## 2 Rufen Sie Ihre Anmeldeinformationen ab

Um Wayfair mit plentymarkets zu verbinden, müssen Sie API-Anmeldeinformationen eingeben. Um diese zu erhalten, müssen Sie zuerst eine E-Mail an ERPSupport@wayfair.com senden und die folgenden Informationen angeben:

- Betreff : Zugang zum plentymarkets Plugin / "Name Ihres Unternehmens" (SuID)
- Ansprechpartner
- Die Funktionalitäten, die Sie verwenden möchten.

Sie erhalten in Kürze eine E-Mail mit der Bestätigung, dass Ihnen Zugriff auf die API-Tools sowie Ihre **Lieferanten-ID(s)** gewährt wurde. Als nächstes müssen Sie zu Ihrem Extranet-Konto unter partners.wayfair.com. Im Banner sollte ein Tab mit dem Namen "Developer" sichtbar sein. Wenn Sie diese nicht im Banner sehen, bewegen Sie den Cursor über den Tab "More" und es sollte im Dropdown-Menü angezeigt werden.

Bewegen Sie den Cursor über den Tab "Developer" und klicken Sie auf "Application". Sie sollten auf eine neue Seite umgeleitet werden. Klicken Sie auf dieser neuen Seite auf die Schaltfläche "+ New Application" und geben Sie Ihrer Application einen Namen und eine Beschreibung.

Klicken Sie auf "Save". Anschlie§end sollten Ihnen eine ClientID und ein Client Secret angezeigt werden. Dies sind Ihre Anmeldeinformationen, die Sie für das Wayfair-Plugin brauchen. Bewahren Sie diese an einem sicheren Ort auf, insbesondere das Client Secret, da Sie es nur einmal sehen können.


## 3 Installierung des Wayfair-Plugins in plentymarkets

Nachdem Sie das Wayfair-Plugin vom plentyMarketplace heruntergeladet haben, installieren Sie das Plugin im Menü **Plugins >> Plugin-Übersicht**.

### Authentifizierung

Führen Sie nach der Installation des Plugins in Ihrem plentymarkets-Konto die Authentifizierung aus, um den Zugriff auf die Schnittstelle zu ermöglichen.

#### Aktivierung des Zugriffs auf die Schnittstelle

1. Öffnen Sie das Menü **Plugins >> Plugin-Übersicht >> Wayfair >> Konfiguration >> Globale Einstellungen**

2. Geben Sie die Client-ID und das Client-Secret, die Sie beim Erstellen der Application im Extranet erhalten haben, in die Felder **Client-ID** und **Client-Secret** ein.

3. Ändern Sie **Modus** zu **Live**.

4. **Speichern** sie die Einstellungen.


## 4 Auftragsherkunft aktivieren

Ein Auftragsherkunft gibt den Vertriebskanal an, auf dem ein Auftrag generiert wurde. Sie müssen die Wayfair-Auftragsherkunft aktivieren, um Artikel, Eigenschaften usw. mit Wayfair zu verknüpfen.

### Auftragsherkunft für Wayfair aktivieren:

1. Öffnen Sie das Menü **System >> Bestellung >> Auftragsherkunft**.

2. Setzen Sie ein Häkchen neben der **Wayfair** -Auftragsherkunft.

3. Klicken Sie auf **Speichern**.


## 5 Artikel für Wayfair verfügbar machen.

Artikel, die Sie auf der Wayfair-Website verkaufen möchten, müssen für Wayfair aktiv und verfügbar sein. Diese Einstellungen werden in der **Artikel >> Artikel bearbeiten >> Artikel öffnen >> Tab: Variations-ID** vorgenommen. Bitte beachten Sie, dass Sie diese Einstellungen für alle Artikel vornehmen müssen, die Sie auf Wayfair verkaufen möchten.

### Artikelverfügbarkeit für Wayfair festlegen:

1. Gehen Sie zu **Artikel >> Artikel bearbeiten** und klicken Sie dann auf die **Artikel-ID** des Artikels, den Sie für Wayfair verfügbar machen möchten.

2. Sie werden auf den **Einstellungen** Tab des ausgewählten Artikels geleitet. Auf diesem Tab befindet sich ein Abschnitt mit der Bezeichnung **Verfügbarkeit**. Setzen Sie ein Häkchen neben der Option **Aktiv** in diesem Abschnitt.

3. Klicken Sie auf den Tab **Verfügbarkeit**.

4. Klicken Sie in das Auswahlfeld im Abschnitt **Märkte**. Eine Liste mit allen verfügbaren Märkten wird angezeigt.

5. Setzen Sie ein Häkchen neben der Option **Wayfair**.

6. Klicken Sie auf **Märkte Wählen**. Der Markt ist jetzt hinzugefügt.

7. Klicken Sie auf **Speichern**. Der Artikel ist jetzt für Wayfair verfügbar.


### Übereinstimmen einer eingehenden Bestellung mit Ihren Artikeln in plentymarkets:

Um einen Artikel in der Wayfair-Datenbank, der durch die von Wayfair zur Verfügung gestellte Supplier Item Number definiert ist, mit Ihren Artikeln in plentymarkets abzugleichen, müssen Sie konfigurieren, mit welchem ​​plentymarkets-Feld die Wayfair Supplier Item Number übereinstimmen soll.

Folgen Sie dazu den Anweisungen unten:

1. Gehen Sie zu ** System >> Märkte >> Wayfair >> Home **.

2. Wenn die Startseite des Plugins geladen ist, klicken Sie auf ** Einstellungen **.

3. Wählen Sie unter ** Artikelzuordnungsmethode ** das Feld in Ihrem plentymarkets System aus, von dem die Supplier Item Number stammt, die Sie für Wayfair angegeben haben. Sie können aus diesen drei Feldern wählen:
           ** a. ** * Variationsnummer *
           **b.** *EAN*
           ** c. ** * Marktplatzspezifische SKU *: Wählen Sie diese Option nur, wenn keines der beiden oben genannten plentymarkets-Felder mit der für Wayfair angegebenen Supplier Item Number übereinstimmt. Wenn Sie sich für diese Option entscheiden, befolgen Sie bitte die nachstehenden Anweisungen (Anpassen einer eingehenden Bestellung mithilfe der marktplatzspezifischen SKU).

4. Klicken Sie auf ** Speichern **.


#### Zuordnung einer eingehenden Bestellung mit der marktplatzspezifischen SKU.

1. Gehen Sie zu ** Artikel >> Artikel bearbeiten ** und klicken Sie auf die ** Artikel-ID ** des Artikels, den Sie mit Wayfair-Aufträgen zuordnen möchten.

2. Sie werden auf den Tab ** Einstellungen ** des ausgewählten Elements geleitet. Klicken Sie auf den Tab ** Verfügbarkeit **.

3. In diesem Tab gibt es vier verschiedene Abschnitte. Die die benötigt ist, ist die ** SKU ** Sektion.

4. Klicken Sie auf die Schaltfläche ** Hinzufügen ** (das Plus in grauem Hintergrund).

5. Ein neues Fenster wird geöffnet. Wählen Sie im ersten Feld (** Herkunft **)  ** Wayfair ** aus dem Dropdown-Menü aus.

6. Geben Sie im dritten Feld (** SKU **) die ** Wayfair Supplier Item Number ** die Sie für diesen Artikel bei Wayfair hinterlegt haben ein.

7. Klicken Sie auf ** Hinzufügen **.

8. Klicken Sie auf ** Speichern **.

9. Wiederholen Sie den Vorgang für alle Artikel, die Sie auf Wayfair verkaufen.


## 6 Auftragsabwicklung einrichten.

Für die automatisierte Auftragsabwicklung mit dem Wayfair-Plugin müssen **Versandprofilzuordnungen** und **Ereignisaktionen** eingerichtet werden. Führen Sie die folgenden Anweisungen aus:

### Legen Sie einen neuen Versanddienstleister an

1. Öffnen Sie das Menü **System >> Aufträge >> Versand >> Optionen >> Tab: Versanddienstleister**

2. Klicken Sie auf **+ Neu**, um einen neuen **Versanddienstleister anzulegen**.

3. Geben Sie **Wayfair Shipping** in die Felder **Name (de)** und **Name (Backend)** ein.

4. Klicken Sie in das Feld **Versanddienstleister** und wählen Sie **WayfairShipping**.

5. Klicken Sie auf **Speichern**.

### Erstellen Sie ein neues Versandprofil

1. Gehen Sie zu **System >> Aufträge >> Versand >> Optionen >> Tab: Versandprofile**

2. Klicken Sie auf **+ Neu**, um ein neues Versandprofil zu erstellen.

3. Sie sehen eine Tabelle, in der Sie Daten eingeben müssen.

4. Klicken Sie in der ersten Zeile auf das Dropdown-Menü und wählen Sie den soeben erstellten **Versanddienstleister** (sollte **WayfairShipping** sein, wenn Sie unsere Anweisungen befolgt haben).

5. Geben Sie in der zweiten und dritten Zeile einen Namen ein (wir empfehlen zur Vereinfachung **WayfairShipping**). Sie sollten auch eine Sprache in der zweiten Zeile, dritten Spalte auswählen.

6. Wählen Sie in der vierten Zeile die Markierungsnummer 6 oder 126 (die die Wayfair-Farben darstellen).

7. Wählen Sie in der fünften Spalte **Priorität n1** (die zwei Sterne).

8. Setzen Sie in der siebzehnten Spalte (**Auftragsherkunft**) ein Häkchen neben **Wayfair** (falls mehrere vorhanden sind, wählen Sie alle).

9. Scrollen Sie zum oberen Rand der Seite und klicken Sie auf die Schaltfläche **Speichern**. Alle anderen Zeilen und ihre jeweiligen Dateneinträge können leer bleiben.

### Eine neue Ereignisaktion erstellen

1. Gehen Sie zu **System >> Aufträge >> Ereignisse**

2. Klicken Sie auf **Ereignisaktion hinzufügen** (Schaltfläche "+" links auf der Seite).

3. Geben Sie den Namen **Wayfair Order Shipping Mapping** ein.

4. Wählen Sie das Ereignis **Neuer Auftrag** aus dem Dropdown-Menü aus.

5. Klicken Sie auf die Schaltfläche **Speichern**.

6. Sie sollten automatisch zur neu erstellten **Ereignisaktion** umgeleitet werden. Setzen Sie in den **Einstellungen** der Ereignisaktion ein Häkchen neben **Aktiv**.

7. Klicken Sie auf **Filter hinzufügen** und gehen Sie zu **Auftrag >> Herkunft**, um die Herkunft als Filter hinzuzufügen.

8. Im Abschnitt **Filter** sollte ein Feld mit einer Liste aller verfügbaren **Auftragsherkünfte** angezeigt werden. Setzen Sie ein Häkchen bei allen **Wayfair** Auftragsherkünften.

9. Klicken Sie auf **Aktionen hinzufügen** und gehen Sie zu **Auftrag >> Versandprofil ändern**. Klicken Sie auf **+ Hinzufügen **.

10. Klicken Sie im Abschnitt **Aktionen** auf die Schaltfläche **Erweitern** neben **Versandprofil ändern** (Pfeil links).

11. Wählen Sie das **Versandprofil**, das Sie zuvor erstellt haben (sollte ** WayfairShipping ** sein, wenn Sie unsere Anweisungen befolgt haben).

12. Klicken Sie auf die Schaltfläche **Speichern**.

## 7 Konfigurierung der Lager-Zuordnung

Um die Bestandsdaten im Wayfair-System zu aktualisieren, müssen Sie die Lagern Ihres plentymarkets-Systems der Warehouse-ID im Wayfair-System zuordnen.

1. Gehen Sie zu **System >> Märkte >> Wayfair >> Home >> Tab: Lager**

2. Klicken Sie auf **Zuordnung hinzufügen**.

3. Wählen Sie im Feld **Lager** das Lager aus, dass Sie zuordnen mächten.

4. Geben Sie in das Feld **Supplier ID** Ihre dazugehörige Wayfair Supplier ID ein.

5. Klicken Sie auf **Speichern**.


## 8 Versandsbestätigung (ASN) an Wayfair senden

### Aktualisieren Sie die Einstellungen für den Versanddienstleister, wenn Sie auf eigene Rechnung versenden

![Select shipping method](https://i.ibb.co/5L6pxpk/asn-01.png "Select shipping method")

1.	Wählen Sie **Systems >> Markets >> Wayfair >> Home**

2.	Wählen Sie **Versandbestätigung (ASN)** und dann **Wayfair Shipping** oder **Own account Shipping** , abhängig davon wie Ihre Logistik bei Wayfair aufgesetzt ist (Wenn Sie sich nicht sicher sind, wenden Sie sich an Ihren Wayfair-Ansprechpartner).

3.	Als nächstes, geben Sie für jeden Versanddienstleister mit dem Sie Wayfair-Aufträge versenden den **SCAC codes** ein den Sie von Wayfair während des Onboarding Interviews erhalten haben.

### Erzeugen Sie ein Ereignis und binden Sie es an das Wayfair Plugin um Versandbestätigungen zu senden

1. Gehen Sie zu **System >> Aufträge >> Ereignisse**
![Create Event](https://i.ibb.co/NjDtY05/asn-02.png "Create Event")

2.	Klicken Sie auf **Ereignisaktion hinzufügen** (das "+" Feld auf der linken Seite)

3.	Geben Sie einen **Namen** ein,

4.	Wählen Sie **Statuswechsel** im **Ereignis** Feld,

5.	Wählen Sie den gewünschten Auftragsstatus für das Senden von ASNs (Versandbestätigungen).

6.	Klicken Sie auf den **Speichern** Feld.

7.	Sie sollten automatisch zur neu erstellten **Ereignisaktion** weitergeleitet werden. Setzen Sie im Abschnitt **Einstellungen** der Ereignisaktion ein Häkchen neben **Aktiv**.

8.  Klicken Sie auf **Filter hinzufügen**, und gehen Sie zu **Auftrag >> Herkunft** um die Herkunft als Filter hinzuzufügen (siehe Screenshot unten)
![Event Referrer](https://i.ibb.co/TwKLvJ5/asn-03.png "Event Referrer")

9.	Im **Filter** Abschnitt, sollte eine Box mit einer Liste aller verfügbaren *Auftragsherkünften*. angezeigt werden. Setzen Sie ein Häkchen neben allen Herkünften von **Wayfair**.
![Wayfair Referrer](https://i.ibb.co/yYpLp8q/asn-04.png "Wayfair Referrer")

10. Klicken Sie auf **Aktion hinzufügen**, und gehen Sie zu  **Plugins >> Versandsbestätigung (ASN) an Wayfair senden**. Klicken Sie auf **+ Hinzufügen**.
![Add Procedure](https://i.ibb.co/xfGrhFP/asn-05.png "Add Procedure")

Die Einstellungen sollten am Ende so aussehen:
![Add Procedure Result](https://i.ibb.co/GJPF3ZV/asn-06.png "Add Procedure Result")
