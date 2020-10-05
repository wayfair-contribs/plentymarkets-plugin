import { Icon } from './icon.struct';

export class DisplayedState {
  public static readonly TEXT_CLASS_WARNING: string = 'text-warning';
  public static readonly TEXT_CLASS_DANGER: string = 'text-danger';
  public static readonly TEXT_CLASS_INFO: string = 'text-info';
  public static readonly TEXT_CLASS_SUCCESS: string = 'text-success';
  public static readonly TEXT_CLASS_BODY: string = 'text-body';

  public message: string = '';
  public style: string = '';
  public icon: Icon;
}
