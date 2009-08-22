<?php
//
// Created on: <22-Aug-2009 13:58:09 ar>
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


$Module = $Params['Module'];
$currentUser = eZUser::currentUser();

if ( isset( $Params['UserID'] ) && is_numeric( $Params['UserID'] ) )
{
    $UserID = $Params['UserID'];
    $publicProfile = true;
}
else
{
    if ( !$currentUser->isAnonymous() )
    {
        $UserID = $currentUser->attribute( 'contentobject_id' );
        $publicProfile = false;
    }
    else
    {
        return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
    }
}

if ( isset( $Params['UserParameters'] ) )
{
    $UserParameters = $Params['UserParameters'];
}
else
{
    $UserParameters = array();
}

$user = $currentUser->id() != $UserID ? eZUser::fetch( $UserID ) : $currentUser;
if ( !$user instanceof eZUser )
{
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
}

$userObject = $user->attribute( 'contentobject' );
if ( !$userObject instanceof eZContentObject )
{
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
}

if ( !$userObject->canRead( ) )
{
    return $Module->handleError( eZError::KERNEL_ACCESS_DENIED, 'kernel' );
}

list( $handler, $cacheFileContent ) = eZTemplateCacheBlock::retrieve( array(
    'user_profile',
    $UserID,
    $publicProfile,
    $UserParameters,
    $currentUser->roleIDList(),
    $currentUser->limitValueList(),
    $GLOBALS['eZCurrentAccess'] ), $userObject->attribute('main_node_id'), -1 );
if ( !$cacheFileContent instanceof eZClusterFileFailure )
{
    $Result = unserialize( $cacheFileContent );
    $contentInfoArray = $Result['content_info'];

    $res = eZTemplateDesignResource::instance();
    $res->setKeys( array( array( 'object', $contentInfoArray['object_id'] ),
                          array( 'node', $contentInfoArray['node_id'] ),
                          array( 'parent_node', $contentInfoArray['parent_node_id'] ),
                          array( 'class', $contentInfoArray['class_id'] ),
                          array( 'class_identifier', $contentInfoArray['class_identifier'] ),
                          array( 'remote_id', $contentInfoArray['remote_id'] ),
                          array( 'class_group', $contentInfoArray['class_group'] ),
                          array( 'state', $contentInfoArray['state'] ),
                          array( 'state_identifier', $contentInfoArray['state_identifier'] ) ) );
    return;
}

require_once( 'kernel/common/template.php' );
$tpl = templateInit();
$tpl->setVariable( 'module', $Module );
$tpl->setVariable( 'user_id', $UserID );
$tpl->setVariable( 'user', $user );
$tpl->setVariable( 'user_object', $userObject );
$tpl->setVariable( 'public_profile', $publicProfile );
$tpl->setVariable( 'view_parameters', $UserParameters );
$tpl->setVariable( 'site_access', $GLOBALS['eZCurrentAccess'] );
$tpl->setVariable( 'persistent_variable', false );

$contentInfoArray = array();
$contentInfoArray['object_id'] = $userObject->attribute( 'id' );
$contentInfoArray['node_id'] = $userObject->attribute( 'main_node_id' );
$contentInfoArray['parent_node_id'] =  $userObject->attribute( 'main_parent_node_id' );
$contentInfoArray['class_id'] = $userObject->attribute( 'contentclass_id' );
$contentInfoArray['class_identifier'] = $userObject->attribute( 'class_identifier' );
$contentInfoArray['remote_id'] = $userObject->attribute( 'remote_id' );
$contentInfoArray['class_group'] = $userObject->attribute( 'match_ingroup_id_list' );
$contentInfoArray['state'] = $userObject->attribute( 'state_id_array' );
$contentInfoArray['state_identifier'] = $userObject->attribute( 'state_identifier_array' );

$res = eZTemplateDesignResource::instance();
$res->setKeys( array( array( 'object', $contentInfoArray['object_id'] ),
                      array( 'node', $contentInfoArray['node_id'] ),
                      array( 'parent_node', $contentInfoArray['parent_node_id'] ),
                      array( 'class', $contentInfoArray['class_id'] ),
                      array( 'class_identifier', $contentInfoArray['class_identifier'] ),
                      array( 'remote_id', $contentInfoArray['remote_id'] ),
                      array( 'class_group', $contentInfoArray['class_group'] ),
                      array( 'state', $contentInfoArray['state'] ),
                      array( 'state_identifier', $contentInfoArray['state_identifier'] ) ) );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:user/profile.tpl' );
$Result['view_parameters'] = $UserParameters;
$Result['section_id'] = $userObject->attribute( 'section_id' );
$Result['node_id'] = $userObject->attribute( 'main_node_id' );
$Result['path'] = array( array( 'text' =>  ezi18n( 'kernel/user', 'User profile' ),
                                'url' => false ),
                         array( 'text' =>  $userObject->attribute('name'),
                                'url' => false )  );

$contentInfoArray['persistent_variable'] = false;
if ( $tpl->variable( 'persistent_variable' ) !== false )
{
    $contentInfoArray['persistent_variable'] = $tpl->variable( 'persistent_variable' );
    $keyArray[] = array( 'persistent_variable', $contentInfoArray['persistent_variable'] );
    $res->setKeys( $keyArray );
}

$Result['content_info'] = $contentInfoArray;

$handler->storeCache( array( 'scope'      => 'template-block',
                             'binarydata' => serialize( $Result ) ) );


?>
