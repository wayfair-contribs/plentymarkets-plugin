import { Icon } from './icon.struct';

export class DisplayedState {
  public static readonly TEXT_CLASS_WARNING = "text-warning";
  public static readonly TEXT_CLASS_DANGER = "text-danger";
  public static readonly TEXT_CLASS_INFO = "text-info";
  public static readonly TEXT_CLASS_SUCCESS = "text-success";
  public static readonly TEXT_CLASS_BODY = "text-body";

  public message = "";
  public style = "";
  public icon: Icon;
}
