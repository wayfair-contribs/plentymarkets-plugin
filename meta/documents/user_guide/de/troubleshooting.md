# Wayfair Plugin: Fehlerbehebung

## Einführung

Dieses Dokument richtet sich an Benutzer des Wayfair-Plugins, bei denen Probleme auftreten. Wenn es in Plentymarkets ein Problem gibt, das nicht speziell für das Wayfair-Plugin gilt, sollte es Plentymarkets gemeldet werden.

Die bekannten Probleme für aktuelle und frühere Versionen des Wayfair-Plugins können auf [Die Issues Site auf GitHub](https://github.com/wayfair-contribs/plentymarkets-plugin/issues) eingesehen werden.


## Plentymarkets Logs

Das Wayfair Plugin erstellt Informationen in den Plentymarkets Logs, die wichtige Informationen zur Untersuchung und Lösung von Problemen enthalten.

### Anzeigen der Logs

Um die Plentymarkets-Protokolle anzeigen, Gehen Sie von der Hauptseite von Plentymarkets aus zu `Daten` >> `Log`.

![log im Datenmenü](../../../images/de/troubleshooting/menu_data_log.png)

### Lesen der Logdetails

Klicken Sie in der Sicht der Log auf eine Zeile, um die "Protokolldetails" für eine Nachricht anzuzeigen.

![logdetails](../../../images/de/troubleshooting/log_details.png)

* Wichtige Details werden häufig in ** zusammenklappbaren ** Bereichen der Protokolldetails versteckt.

* Wenn Sie einen Fehlerbericht einreichen, kopieren Sie so viele Daten wie möglich aus den "Protokolldetails" in eine Datei, um sie an Wayfair zu senden.


### Legen Sie die Log Level für Wayfair fest

Die Standardeinstellungen für die Logs zeigen nicht alle Nachrichten vom Wayfair-Plugin an. Um weitere Details in den Protokollen zu erhalten, ändern Sie die Log Level für Wayfair in `Debug`:

1. Laden Sie die Plentymarkets `Log` Seite, falls sie noch nicht geöffnet ist.

2. Drück den zahnradförmiger Knopf ![zahnradförmiger Knopf](../../../images/common/button_gear.png) für `Logs Konfigurieren`.

3. Klicken Sie in der linken Liste auf `Wayfair`.
    ![wayfair in list](../../../images/de/troubleshooting/wayfair_log_category.png)

4. Aktivieren Sie das Kontrollkästchen `Aktiv`.

5. Stellen Sie die `Log level` auf `Debug` ein.

6. Stellen Sie sicher, dass die Details so aussehen:

    ![wayfair auf debug eingestellt](../../../images/de/troubleshooting/wayfair_logs_active_debug.png)

7. Klicken Sie unten im Formular auf die Schaltfläche `Speichern`, um die Einstellungen zu speichern. **Hinweis:** Es gibt keinen Hinweis darauf, dass die Einstellungen gespeichert wurden.

### Nur die Wayfair-Protokolle anzeigen

1. Laden Sie die Plentymarkets `Log` Seite, falls sie noch nicht geöffnet ist.

2. Geben Sie im Feld `Integration` auf der linken Seite "Wayfair" ein.

    ![wayfair im filter](../../../images/de/troubleshooting/filter_logs_wayfair.png)

3. Klicken Sie auf die Lupentaste ![suchschaltfläche](../../../images/common/button_search.png) um nur die Logs für wayfair anzuzeigen.

## Häufige Probleme

Bevor Sie sich an Wayfair wenden, verwenden Sie bitte die folgende Tabelle, um Probleme im Wayfair-Plugin selbst zu diagnostizieren.

| Problem | Mögliche Ursachen |
| ----- | --------------- |
| Allgemeiner Mangel an Daten, die von Wayfair stammen oder zu Wayfair gehen | <ul><li>[Anmeldeinformationen für das Plugin](initial_setup.md#1-autorisierung-des-wayfair-plugins-f%C3%BCr-den-zugriff-auf-wayfair-schnittstellen) fehlen oder sind im aktiven Plugin Set falsch.</li><li>Probleme mit der Internetverbindung.</li><li>Vorübergehende Ausfallzeiten bei Wayfair.</ul> |
| In dem an Wayfair gesendeten Inventar fehlt ein Artikel | <ul><li>Die Einstellung [`Methode zur Artikelzuweisung`](settings_guide.md#methode-zur-artikelzuweisung) in den Einstellungen von Wayfair Plugin ist nicht auf den gewünschten Wert eingestellt.</li><li>Die Einstellung [`Vollständigen Bestand an Wayfair senden`](settings_guide.md#vollst%C3%A4ndigen-bestand-an-wayfair-senden) ist **deaktiviert** und die Artikelvariation in Plentymarkets ist nicht [als für den Wayfair-Marktplatz verfügbar markiert](initial_setup.md#5-artikel-auf-wayfair-zum-verkauf-anbieten).</li><li>Die Artikelvariation in Plentymarkets ist einem Plentymarkets Lager zugeordnet, das keiner Wayfair Lieferanten ID auf der [Lager Seite](settings_guide.md#die-lager-seite) der Wayfair Plugin Einstellungen zugeordnet ist.</li><li>Die Artikelvariation in Plentymarkets hat einen leeren oder falschen Wert in dem Feld, das der Einstellung [`Methode zur Artikelzuweisung`](settings_guide.md#methode-zur-artikelzuweisung) der Wayfair Plugin Einstellungen ausgewählt ist.</li></ul> |
| Ein an Wayfair gesendeter Inventarbetrag für einen Artikel liegt unter dem erwarteten Wert | <ul><li>Keines der [Plentymarkets Lager abgestimmt auf Wayfair](settings_guide.md#die-lager-seite) hat positive Lagerbestände für den Plentymarkets Artikelvariation.</li><li>Der Wert der [Lagerpuffer](settings_guide.md#lagerpuffer) ist größer oder gleich der Bestandsmenge für die Plentymarkets Artikelvariation in [Plentymarkets Lager abgestimmt auf Wayfair](settings_guide.md#die-lager-seite).</li></ul> |
| Es kommen keine Bestellungen von Wayfair | <ul><li>Die Wayfair Lieferanten ID für die Wayfair-Bestellung nicht ist einem Plentymarkets Lager zugeordnet auf der [Lager Seite](settings_guide.md#die-lager-seite) der Wayfair Plugin Einstellungen.</li></ul> |
| Bestellungen von Wayfair werden nicht ordnungsgemäß mit Artikeln in Plentymarkets verknüpft | <ul><li>Die Einstellung [`Methode zur Artikelzuweisung`](settings_guide.md#methode-zur-artikelzuweisung) in den Einstellungen von Wayfair Plugin ist nicht auf den gewünschten Wert eingestellt.</li><li>Die Artikelvariation in Plentymarkets hat einen leeren oder falschen Wert in dem Feld, das der Einstellung [`Methode zur Artikelzuweisung`](settings_guide.md#methode-zur-artikelzuweisung) der Wayfair Plugin Einstellungen ausgewählt ist.</li></ul> |
| Lager zuordnungen fehlen oder sind für Bestellungen von Wayfair falsch | <ul><li>Probleme in Lager verbänden auf der [Lager Seite](settings_guide.md#die-lager-seite) der Wayfair Plugin Einstellungen.</li><li>Derzeit sind einer Wayfair Lieferanten ID mehrere Lager zugeordnet auf der [Lager Seite](settings_guide.md#die-lager-seite) der Wayfair Plugin Einstellungen.</li></ul> |
| Die Schaltfläche zum Herunterladen von Versandetiketten wird nicht auf der Registerkarte `Übersicht` einer Bestellung im `Versand-Center` von Plentymarkets angezeigt | <ul><li>Der Versand der Bestellung wurde nicht registriert.</li><li>Die Seite des `Versand-Center` wurde noch nicht lade de neu, nachdem die Versandregistrierung durchgeführt wurde.</li><li>Das Herunterladen von Dokumenten ist während der Versand Registrierung fehlgeschlagen.</li></ul> |
| Inventar Aktualisierungen werden nicht an Wayfair gesendet | <ul><li>Das Wayfair-Plugin wurde gerade installiert oder aktualisiert. Verwenden Sie die [Wayfair Einstellungen](settings_guide.md), um [die erste vollständige Inventarsynchronisierung](initial_setup.md#8-ersten-inventar-synchronisation) zu starten, da Wayfair dies in den ersten 24 nicht automatisch tun darf Std.</li><li>[Wayfair Einstellungen](settings_guide.md) stimmen nicht mit dem Plentymarkets-Setup überein. Überprüfen Sie "In dem an Wayfair gesendeten Inventar fehlt ein Artikel."</li><li>Konnektivitätsprobleme - Überprüfen Sie "Allgemeiner Mangel an Daten, die von Wayfair stammen oder zu Wayfair gehen."</li></ul> |

## So melden Sie Probleme an Wayfair

Wenn Sie ein Problem mit dem Wayfair-Plugin nicht beheben können, können Sie das Problem Wayfair auf [Die Issues Site für das Plugin auf GitHub](https://github.com/wayfair-contribs/plentymarkets-plugin/issues) melden.

**Wenn Sie Wayfair kontaktieren, geben Sie bitte diesen Informationen**:

* Letzte Änderungen an den Einstellungen von Plentymarkets
* Letzte Änderungen an den Einstellungen des Wayfair Plugins
* Details der Plentymarkets Logs aus Zeiten, in denen Probleme aufgetreten sind

**Um Ihnen den bestmöglichen Support zu bieten, müssen die Vertreter von Wayfair möglicherweise auch zusätzliche Fragen zu Ihrer spezifischen Konfiguration stellen.**
