RedyCASRestLogin - CAS login as REST request
================
This small lib is intended to be used with the Nginx module auth_request.
Nginx pass a subrequest to auth.php. Which proceeds with authentication and if required authorization of the user.
If passing Nginx will pass the original request through to the source. Otherwise a 401 HTTP response will be returned.

Authentication is done passing the query parameters username and password.

Authorization is done verifying the users roles against the passed roles to the login method.
