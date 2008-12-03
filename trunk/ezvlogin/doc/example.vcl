backend default {
    set backend.host = "127.0.0.1";
    set backend.port = "80";
}

sub vcl_recv {

     /* binary files should always be returned by Varnish */
     if (req.url ~ "\.(swf|css|js|flv|mp3|mp4|pdf|ico)$") {
         lookup;
     }

     /* do not use Varnish when the user is authenticated */
     if (req.http.Cookie == "is_logged_in" ) {
         pass;
     }

     if (req.request != "GET" && req.request != "HEAD") {
         pipe;
     }
 
     if (req.http.Expect) {
         pipe;
     }
 
     if (req.http.Authenticate) {
         pass;
     }
 
     if (req.http.Cache-Control ~ "no-cache") {
         pass;
     }
 
     lookup;
}
 
sub vcl_hash {
     set req.hash += req.url;
     set req.hash += req.http.host;
     hash;
}
 
sub vcl_hit {
 
     if (!obj.cacheable) {
         pass;
     }
 
    deliver;
}
 
sub vcl_fetch {
 
     if (!obj.valid) {
         error;
     }
 
     if (!obj.cacheable) {
        pass;
     }
 
     if (obj.http.Set-Cookie) {
        pass;
     }
     
     if (  obj.http.Pragma        ~ "no-cache"
        || obj.http.Cache-Control ~ "no-cache" 
        || obj.http.Cache-Control ~ "private") {
             pass;
     }

     if (obj.ttl < 3600s){
             set obj.ttl = 3600s;
     }

     insert;
}
