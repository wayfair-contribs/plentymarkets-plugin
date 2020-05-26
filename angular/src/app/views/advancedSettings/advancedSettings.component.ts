import { Component, OnInit } from "@angular/core";
import { Language, TranslationService } from "angular-l10n";
import { ResetAuthenticationService } from "../../core/services/resetAuthentication/resetAuthentication.service";

@Component({
  selector: "advanced-settings",
  template: require("./advancedSettings.component.html"),
})
export class AdvancedSettingsComponent implements OnInit {
  private static readonly MESSAGE_TYPE_BAD = "text-danger";
  private static readonly MESSAGE_TYPE_GOOD = "text-info";
  private static readonly MESSAGE_KEY_OPERATION_FAILED = "operation_failed";
  private static readonly MESSAGE_KEY_SUCCESS = "success";

  @Language()
  public lang: string;
  public authResetStatus = { type: null, value: null };

  constructor(
    private resetAuthService: ResetAuthenticationService,
    private translationService: TranslationService,
  ) {}

  ngOnInit(): void {}

  /**
   * Reset the back-ends tokens for calling Wayfair APIs
   */
  public resetAuthentication(): void {
    let status = false;
    this.resetAuthService.resetAuthentication().subscribe(
      (data) => {
        if (data.status)
        {
          this.showResetAuthSuccess();
        }
        else
        {
          this.showResetAuthError();
        }
      },
      (err) => {
        this.showResetAuthError();
      }
    );
  }

  /**
   * Show a message for the reset auth button.
   * Does NOT translate for locale!
   * 
   * @param type the display type
   * @param value the value to display to the client
   */
  private showResetAuthMessageVerbose(type, value){
    this.authResetStatus.type = type;
    this.authResetStatus.value = value;
  }

  /**
   * Show a message for the reset auth button, after translating
   * 
   * @param type the display type
   * @param messageKey the key for use by the translation service
   */
  private translateAndShowResetAuthMessage(type, messageKey) {
    this.showResetAuthMessageVerbose(type, this.translationService.translate(messageKey));
  }

  /**
   * Show a failure message for the reset auth button
   */
  private showResetAuthError() {
    this.translateAndShowResetAuthMessage(AdvancedSettingsComponent.MESSAGE_TYPE_BAD, AdvancedSettingsComponent.MESSAGE_KEY_OPERATION_FAILED);
  }

  /**
   * Show a timestamped success message for the reset auth button
   */
  private showResetAuthSuccess() {
    let message = this.translationService.translate(AdvancedSettingsComponent.MESSAGE_KEY_SUCCESS);
    message += " - " + Date().toLocaleString();
    this.showResetAuthMessageVerbose(AdvancedSettingsComponent.MESSAGE_TYPE_GOOD, message);
  }

  /**
   * Clear out the message for the reset auth button
   */
  private clearResetAuthMessage() {
    this.showResetAuthMessageVerbose(null, null);
  }
}
