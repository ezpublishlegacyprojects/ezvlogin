<?php
//
// Created on: <10-Oct-2008 11:03:17 ar>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ Varnish Login
// SOFTWARE RELEASE: 1.0.x
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


class eZVLoginHelper
{
    private function eZVLoginHelper()
    {
        
    }

    /*
     Set cookies for use by reverse proxy setup
     Assumes logout if $userName is false ( deletes cookie )
     Cookie follow user session if $time is set to 0
    */
    public static function setUserCookie( eZUser $user, $userName = false, $time = 0 )
    {
        $wwwDir = eZSys::wwwDir();
        $vIni   = eZINI::instance( 'vlogin.ini' );
        // On host based site accesses this can be empty, causing the cookie to be set for the current dir,
        // but we want it to be set for the whole eZ publish site
        $cookiePath = $wwwDir != '' ? $wwwDir : '/';
        setcookie( $vIni->variable( 'LoginSettings', 'CookieName' ),
                   ($userName !== false ? $vIni->variable( 'LoginSettings', 'CookieValue' ) : false),
                   $time,
                   $cookiePath );

        // should we set cookie with user name for use in ajax / reverse proxy setup ?
        if ( $vIni->hasVariable( 'LoginSettings', 'UserNameCookieName' ) &&
             $vIni->variable( 'LoginSettings', 'UserNameCookieName' ) != '' )
        {
            setcookie( $vIni->variable( 'LoginSettings', 'UserNameCookieName' ),
                   $userName,
                   $time,
                   $cookiePath );
        }

        // Is user, even thoug logged in, supposed to be cached by reverse proxy?
        if ( $vIni->hasVariable( 'LoginSettings', 'CachedUserGroups' ) &&
             $vIni->variable( 'LoginSettings', 'CachedUserGroups' ))
        {
            $cachedGroups = $vIni->variable( 'LoginSettings', 'CachedUserGroups' );
            $cachedValue  = $userName !== false && count( array_diff( $user->groups(), $cachedGroups) ) === 0 ? 'true' : false;
            setcookie( $vIni->variable( 'LoginSettings', 'CachedUserGroupCookieName' ),
                   $cachedValue,
                   $time,
                   $cookiePath );
        }
    }

    /*
     Checks if current url is the starting point of the SSO redirect loop
     If so it returns the url (string) where we shoudl redirect else false
    */
    public static function isSSOStart( eZModule $Module )
    {
        $currentURI = eZSys::serverURL() . eZSys::indexDir();
        $http       = eZHTTPTool::instance();
        if ( $http->hasGetVariable( 'ezvloginSSOStart' ) )
        {
            if ( $http->getVariable( 'ezvloginSSOStart' ) == $currentURI )
                return $http->getVariable( 'ezvloginRedirectURI' );
            else
                eZDebug::writeNotice( 'Not current url: ' . $http->getVariable( 'ezvloginSSOStart' ), __METHOD__ );
        }
        return false;
    }

    /*
     Checks if we should do a SSO redirect to another server or if we should redirect
     like we normally do.
    */
    public static function doSSORedirect( eZModule $Module, $redirectionURI )
    {
        $http       = eZHTTPTool::instance();
        
        // first do internal sso call, as it will exit request if called
        // from another server to save full page generation
        self::doSSORemoteCall( $Module, $http );

        $currentURI = eZSys::serverURL() . eZSys::indexDir();
        $siteIndex  = self::IndexOfSite( $currentURI, $redirectList );

        // return SSO redirect url if we found currentURI in redirect list
        if ( $siteIndex !== false && $siteIndex !== -1 && is_numeric( $siteIndex ) )
        {
            if ( count( $redirectList ) <= ( $siteIndex + 1 ) )
                $uri = $redirectList[0];
            else
                $uri = $redirectList[( $siteIndex + 1 )];

            // Attach GET parameters to not lose the login parameters
            if ( $Module->isCurrentAction( 'Login' ) )
            {
                $uri .= '/vlogin/login/?UserLogin=' . rawurlencode( $Module->actionParameter( 'UserLogin' ) );
                $uri .= '&UserPassword=' . rawurlencode( $Module->actionParameter( 'UserPassword' ) );
                $uri .= '&UserRedirectURI=' . $Module->actionParameter( 'UserRedirectURI' );
            }
            else
            {
                $uri .= '/vlogin/logout/?';
            }

            // Attach SSO start point so we know when to exit the SSO redirect loop
            if ( $http->hasGetVariable( 'ezvloginSSOStart' ) )
            {
                $uri .= '&ezvloginSSOStart=' . $http->getVariable( 'ezvloginSSOStart' );
                $uri .= '&ezvloginRedirectURI=' . $http->getVariable( 'ezvloginRedirectURI' );
            }
            else
            {
                $uri .= '&ezvloginSSOStart=' . $currentURI . '&ezvloginRedirectURI=' . $redirectionURI;
            }

            $redirectionURI = $uri;
        }
        elseif ( $siteIndex === -1 )
        {
            eZDebug::writeNotice( 'Unvalid site index: ' . $siteIndex, __METHOD__ );
        }

        // return redirect uri
        return $Module->redirectTo( $redirectionURI );
    }

    /*
     Privare function to get index of current site url ammong the
     [SSOSettings]RedirectList urls
    */
    private static function IndexOfSite( $uri, &$redirectList )
    {
        $vIni = eZINI::instance( 'vlogin.ini' );

        if ( $vIni->hasVariable( 'SSOSettings', 'RedirectList' ) &&
             count( $vIni->variable( 'SSOSettings', 'RedirectList' ) > 1 ) )
        {
            $redirectList = $vIni->variable( 'SSOSettings', 'RedirectList' );
            foreach ( $redirectList as $i => $site )
            {
                if ( $uri == $site )
                {
                    return $i;
                }
            }
            return -1;
        }
        return false;
    }

    public static function doSSORemoteCall( eZModule $Module, eZHTTPTool $http )
    {
        if ( $http->hasGetVariable( 'RemoteSSO' ) )
        {
            eZDB::checkTransactionCounter();
            eZExecution::cleanExit();
        }

        $vIni = eZINI::instance( 'vlogin.ini' );
        if ( $vIni->hasVariable( 'SSOSettings', 'InternalList' ) )
        {
            if ( $Module->isCurrentAction( 'Login' ) )
            {
                $uri = '/vlogin/login/?UserLogin=' . rawurlencode( $Module->actionParameter( 'UserLogin' ) );
                $uri .= '&UserPassword=' . rawurlencode( $Module->actionParameter( 'UserPassword' ) );
            }
            else
            {
                $uri = '/vlogin/logout/?';
            }

            $uri .= '&RemoteSSO=true';

            $ipList = $vIni->variable( 'SSOSettings', 'InternalList' );
            $currentIp = eZSys::serverVariable( 'SERVER_ADDR' );

            // only do internal sso if current server is part of sso setup
            if ( isset( $ipList[ $currentIp ] ) )
            {
                foreach( $ipList as $remoteIp => $remoteUri  )
                {
                    if ( $currentIp != $remoteIp )
                    {
                        self::callRemoteServerAndSetCookies( $remoteUri . $uri );
                    }
                }
            }
        }
    }

    public static function callRemoteServerAndSetCookies( $uri, $port = 80, $timeOut = 20 )
    {
        preg_match( "/^((http[s]?:\/\/)([a-zA-Z0-9_\-.:]+))?([\/]?[~]?(\.?[^.]+[~]?)*)/i", $uri, $matches );
        
        $protocol = $matches[2];
        $host = $matches[3];
        $path = $matches[4];
        $errorNumber = '';
        $errorString = '';

        if ( strpos( $host, ':') )
        {
            $host = explode( ':', $host );
            $port = $host[1];
            $host = $host[0];
        }

        if ( !$protocol || $protocol === 'https://' )
        {
            $filename = 'ssl://' . $host;
        }
        else
        {
            $filename = 'tcp://' . $host;
        }

        // Try to connect to server 
        $fp = fsockopen( $filename,
                         $port,
                         $errorNumber,
                         $errorString,
                         $timeOut );
        if ( !$fp )
        {
            eZDebug::writeError( 'Error connecting to: ' . $host .':'. $port . " error string: $errorString ($errorNumber)" , __METHOD__ );
            return '';
        }

        $request = 'GET ' . $path . ' ' . $_SERVER['SERVER_PROTOCOL'] . "\r\n" .
             "Host: $host\r\n" .
             "Accept: */*\r\n" .
             "Content-type: application/x-www-form-urlencoded\r\n" .
             "Content-length: 0\r\n" .
             "User-Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\r\n" .
             "Pragma: no-cache\r\n" .
             "Connection: close\r\n";

        // Add cookies to request header
        $cookieStr = '';
        foreach( $_COOKIE as $cookie => $value )
        {
            $cookieStr .= " $cookie=$value;";
        }
        if ( $cookieStr !== '' )
        {
            $request .= 'Cookie:' . $cookieStr . "\r\n";
        }
        // End request header with an empty line
        $request .= "\r\n";

        eZDebug::writeDebug( $request, __METHOD__ );

        fputs( $fp, $request );


        stream_set_blocking( $fp, true );
        stream_set_timeout( $fp, $timeOut );
        $info = stream_get_meta_data( $fp );
        $buf = '';

        while ( !feof($fp) && !$info['timed_out'] )
        {
                $buf .= fgets($fp, 4096);
                $info = stream_get_meta_data($fp);
        }
        fclose( $fp );

        if ( $info['timed_out'] )
        {
            eZDebug::writeWarning('Connection timeout: ' . $uri, __METHOD__ );
            return '';
        }
        else if ( !$buf )
        {
            eZDebug::writeWarning('Uri returned empty: ' . $uri, __METHOD__);
            return '';
        }

        $wwwDir = eZSys::wwwDir();
        // On host based site accesses this can be empty, causing the cookie to be set for the current dir,
        // but we want it to be set for the whole eZ publish site
        $cookiePath = $wwwDir !== '' ? $wwwDir : '/';

        //Set-Cookie: eZSESSID57c7d11cd49333e3f722204c63016da9=18fuiffemiuru1d81h9qmfhcs3; path=/
        if ( preg_match_all( "/\nSet-Cookie: ([a-zA-Z0-9_\-.:]+)=([a-zA-Z0-9_\-.:]+);/i", $buf, $matches ) )
        {
            foreach( $matches[1] as $i => $cookieName )
            {
                if ( !isset( $_COOKIE[$cookieName] ) && isset( $matches[2][$i] ) )
                {
                    setcookie( $cookieName, $matches[2][$i], 0, $cookiePath );
                }
            }
        }
    }
}

?>
