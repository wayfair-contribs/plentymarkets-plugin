import { Component } from "@angular/core";
import { SettingsService } from "../../core/services/settings/settings.service";
import { Language, TranslationService } from "angular-l10n";

@Component({
  selector: "settings",
  template: require("./settings.component.html"),
})
export class SettingsComponent {
  private static readonly TRANSLATION_KEY_NEGATIVE_NOT_ALLOWED =
    "negative_not_allowed";
  private static readonly TRANSLATION_KEY_MUST_BE_POSITIVE =
    "value_must_be_positive";
  private static readonly MESSAGE_DELIM = ", ";

  @Language()
  public lang: string;

  public status = { type: null, value: null, timestamp: null };

  public stockBuffer = null;
  public defaultOrderStatus = null;
  // Default Shipping Provider is deprecated as of 1.1.2
  public defaultShippingProvider = null;
  public defaultItemMappingMethod = null;
  public importOrdersSince = null;
  public isAllInventorySyncEnabled = null;

  public constructor(
    private settingsService: SettingsService,
    private translation: TranslationService
  ) {}

  public ngOnInit(): void {
    this.loadSettingsFromStorage();
  }

  /**
   * Save in-memory settings after validation
   */
  public saveSettings(): void {
    this.clearMessage();
    this.showTranslatedInfo("saving_status");

    let error = this.normalizeSettings();
    if (error && error.length > 0) {
      this.showErrorVerbose(error);
      return;
    }

    error = this.validateSettings();
    if (error && error.length > 0) {
      this.showErrorVerbose(error);
      return;
    }

    this.saveSettingsToStorage();
  }

  /**
   * Save the current settings to storage
   */
  private saveSettingsToStorage(): void {
    this.settingsService.save(this.serializeSettings()).subscribe(
      (data) => {
        this.loadSettingsFromObject(data);

        this.showTranslatedInfo("saved");
      },
      (err) => {
        this.showTranslatedError("error_save");
      }
    );
  }

  /**
   * Serialize the current in-memory settings to an Object
   */
  private serializeSettings(): object {
    return {
      stockBuffer: this.stockBuffer,
      defaultOrderStatus: this.defaultOrderStatus,
      defaultShippingProvider: this.defaultShippingProvider,
      defaultItemMappingMethod: this.defaultItemMappingMethod,
      importOrdersSince: this.importOrdersSince,
      isAllInventorySyncEnabled: this.isAllInventorySyncEnabled,
    };
  }

  /**
   * Load the settings in storage into the in-memory settings
   */
  private loadSettingsFromStorage(): void {
    this.settingsService.fetch().subscribe(
      (data) => {
        this.loadSettingsFromObject(data);
      },
      (err) => {
        this.showErrorVerbose(this.translation.translate("error_fetch"));
      }
    );
  }

  /**
   * Load the settings in an Object into the in-memory settings
   * @param data the settings as an Object
   */
  private loadSettingsFromObject(data): void {
    this.stockBuffer = data.stockBuffer;
    this.defaultOrderStatus = data.defaultOrderStatus;
    this.defaultShippingProvider = data.defaultShippingProvider;
    this.defaultItemMappingMethod = data.defaultItemMappingMethod;
    this.importOrdersSince = data.importOrdersSince;
    this.isAllInventorySyncEnabled = data.isAllInventorySyncEnabled;
  }

  /**
   * normalize the settings in memory,
   * returning any errors as string
   * @returns string
   */
  private normalizeSettings(): string {
    if (this.importOrdersSince) {
      this.importOrdersSince = new Date(this.importOrdersSince)
        .toISOString()
        .slice(0, 10);
    }

    return null;
  }

  /**
   * validate the settings in memory,
   * returning any errors as string
   * @returns string
   */
  private validateSettings(): string {
    let issues = [];

    if (this.stockBuffer && (isNaN(this.stockBuffer) || this.stockBuffer < 0)) {
      issues.push(
        this.translation.translate("buffer") +
          ": " +
          this.translation.translate(
            SettingsComponent.TRANSLATION_KEY_NEGATIVE_NOT_ALLOWED
          )
      );
    }

    // minimum order status is 1
    if (
      this.defaultOrderStatus &&
      (isNaN(this.defaultOrderStatus) || this.defaultOrderStatus < 1)
    ) {
      issues.push(
          this.translation.translate("order_status_id") +
          ": " +
          this.translation.translate(
            SettingsComponent.TRANSLATION_KEY_MUST_BE_POSITIVE
          )
      );
    }

    if (
      this.defaultShippingProvider &&
      (isNaN(this.defaultShippingProvider) || this.defaultShippingProvider < 0)
    ) {
      issues.push(
        this.translation.translate("shipping_provider_id") +
        ": " +
        this.translation.translate(
          SettingsComponent.TRANSLATION_KEY_NEGATIVE_NOT_ALLOWED
        )
      );
    }

    if (issues.length < 1) {
      return null;
    }

    if (issues.length == 1)
    {
      return issues.pop();
    }

    return issues.toString();
  }

  /**
   * Show a message
   * @param type the style of the message
   * @param message the value of the message
   */
  private showMessageVerbose(
    type,
    message,
    timestamp = new Date().toLocaleString()
  ): void {
    this.status.type = type;
    this.status.value = message;
    this.status.timestamp = timestamp;
  }

  /**
   * Clear any message data on the page
   */
  public clearMessage(): void {
    this.showMessageVerbose(null, null, null);
  }

  /**
   * Show an error after translating it
   * @param messageKey a translator key
   */
  private showTranslatedError(messageKey): void {
    this.showErrorVerbose(this.translation.translate(messageKey));
  }

  /**
   * Show a non-error message after translating it
   * @param messageKey a translator key
   */
  private showTranslatedInfo(messageKey): void {
    this.showInfoVerbose(this.translation.translate(messageKey));
  }

  /**
   * Show an error message
   * @param messageValue a message
   */
  private showErrorVerbose(messageValue): void {
    this.showMessageVerbose("text-danger", messageValue);
  }

  /**
   * Show a non-error message
   * @param messageValue a message
   */
  private showInfoVerbose(messageValue): void {
    this.showMessageVerbose("text-info", messageValue);
  }
}
