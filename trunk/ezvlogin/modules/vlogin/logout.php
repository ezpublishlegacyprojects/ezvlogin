<?php
//
// Created on: <30-Apr-2002 12:41:17 bf>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ Publish
// SOFTWARE RELEASE: 4.1.x
// COPYRIGHT NOTICE: Copyright (C) 1999-2009 eZ Systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

include_once( 'extension/ezvlogin/classes/ezvloginhelper.php' );

$Module = $Params['Module'];

// check if we're back from a SSO redirection loop
if ( ($redirectionURI = eZVLoginHelper::isSSOStart( $Module )) !== false )
{
	// Makes sure this is not cached by proxy
    header( 'Cache-Control: no-cache, must-revalidate' );
    return $Module->redirectTo( $redirectionURI );
}

$http = eZHTTPTool::instance();

$user = eZUser::instance();

// Remove all temporary drafts
eZContentObject::cleanupAllInternalDrafts( $user->attribute( 'contentobject_id' ) );

// Remove eZVLogin cookies
eZVLoginHelper::setUserCookie( $user );

$user->logoutCurrent();

$http->setSessionVariable( 'force_logout', 1 );

$ini = eZINI::instance();
if ( $ini->variable( 'UserSettings', 'RedirectOnLogoutWithLastAccessURI' ) == 'enabled' && $http->hasSessionVariable( 'LastAccessesURI'))
{
    $redirectURL = $http->sessionVariable( "LastAccessesURI" );
}
else
{
    $redirectURL = $ini->variable( 'UserSettings', 'LogoutRedirect' );
}

// check if we're should do SSO or just do a normal redirect
return eZVLoginHelper::doSSORedirect( $Module, $redirectURL );

?>
