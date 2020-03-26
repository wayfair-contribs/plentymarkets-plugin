import {Injectable} from '@angular/core';
import {HttpEvent, HttpInterceptor, HttpHandler, HttpRequest} from '@angular/common/http';
import {Observable} from 'rxjs/Observable';

@Injectable()
export class LocalHTTPInterceptor implements HttpInterceptor {
  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    const apiReq = req.clone({
      url: `https://8ef7758e958dbfa7deed341935d523f9cb8b7db2.plentymarkets-cloud-ie.com/${req.url}` 
    })
    return next.handle(apiReq)
  }
}