<?php /* #?ini charset="utf-8"?

[LoginSettings]
# Cookie name and value to be used as 'logedin' identifier (same as 4.1 support nativly)
CookieName=is_logged_in
CookieValue=true


# Cached User Group mapping
# Only to be used for low priviliges user groups
# As in no more read access then non logged in users
# Note: Cookie will only be set if user doesn't have
# any other goups then those defined here
# Note2: Even root user group 'User' needs to be
# defined, this usually has object id: 4
# Use:
#CachedUserGroupCookieName=member_is_logged_in
#CachedUserGroups[]
#CachedUserGroups[]=<user_group_object_id>


# (optional) Cookie Name to use for user name
#UserNameCookieName=


[SSOSettings]
# List of other doamins you want to redirect to (Signel sign on)
# You must use the full url to the root of the eZ Publish site, like:
# RedirectList[]=http://example.com/ezp/index.php/eng
# or in virtual host mode:
# RedirectList[]=http://example.com
# Note: all sites need to have this extension and same RedirectList setting
# Note2: Uses GET parameters, so will not work on (fast)CGI setup unless in vhost setup
#RedirectList[]
#RedirectList[]=http://trunk:81
#RedirectList[]=http://nor.trunk:81


*/ ?>