# Wayfair-Plugin: Verwenden des Testmodus

## Einführung
Das Wayfair-Plugin verfügt über einen Testmodus zur Bewertung seiner Funktionen, ohne die Live-Produktionsdaten in Wayfair-Systemen zu beeinträchtigen. ** Die Einstellung "Modus" gilt ebenso wie die API-Anmeldeinformationen separat für jedes Plugin-Set in Plentymarkets. **

** WICHTIG: Diese Einstellung ändert nichts an der Funktionsweise von Plentymarkets. Dies bedeutet, dass die Bestellinformationen zwischen den Systemen von Plentymarkets und den Systemen von Wayfair abweichen können. **

## Testmodus aktivieren

1. Wenn Ihre Organisation keine ** Wayfair API Sandbox ** -Anwendungen hat, erstellen Sie eine neue. Die Prozedur entspricht [den Anweisungen zum Abrufen von Anmeldeinformationen] (Getting_credentials.md), mit der Ausnahme, dass der Schieberegler im Erstellungsdialog in der Position "Sandbox" belassen werden sollte. Sie können die Anmeldeinformationen für die neue Sandbox-Anwendung verwerfen. Das Wayfair-Plugin muss seine Produktionsanmeldeinformationen auch im Testmodus weiterhin für die ordnungsgemäße Funktionalität verwenden.

2. Verwenden Sie in den [Globalen Einstellungen für das Wayfair-Plugin] (initial_setup.md # 1 - Autorisierung der Wayfair-Plugins für den Zugriff auf Wayfair-Schnittstellen) im aktiven Plugin-Set den Einstellungswähler "Modus" zum Einstellen es zu `Test`

3. Speichern Sie die globalen Einstellungen für das Wayfair-Plugin

4. Melden Sie sich bei Plentymarkets ab und wieder an, um sicherzustellen, dass die Einstellungen wirksam werden.

## Technische Details
* Wenn Sie den Testmodus verwenden, wird eine Interaktion mit Wayfair, die normalerweise den Status der Wayfair-Bestelldaten ändern würde, mit einem "dryMode" -Flag angefordert, das die Wayfair-Systeme anweist, eine Statusänderung während der Verarbeitung der Anforderung zu vermeiden.

* ** Obwohl der "Test" -Modus derzeit nicht die Wayfair-API-Sandbox verwendet, können Änderungen an API-Sandbox-Daten das Verhalten des Wayfair-Plugins im "Test" -Modus beeinflussen. **
