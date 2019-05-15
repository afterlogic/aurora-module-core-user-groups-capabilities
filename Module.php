<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroupsCapabilities;

/**
 * Provides capabilities for user groups.
 * 
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public function init()
	{
		$this->subscribeEvent('Core::Login::after', array($this, 'onAfterLogin'));
		$this->subscribeEvent('CoreUserGroups::DeleteGroups::after', array($this, 'onAfterRemoveDeleteGroups'));
		$this->subscribeEvent('CoreUserGroups::RemoveUsersFromGroup::after', array($this, 'onAfterRemoveUsersFromGroup'));
		$this->subscribeEvent('CoreUserGroups::AddToGroup::after', array($this, 'onAfterAddToGroup'));
		$this->subscribeEvent('CoreUserGroups::SaveGroupsOfUser::after', array($this, 'onAfterSaveGroupsOfUser'));
		
		\Aurora\Modules\CoreUserGroups\Classes\Group::extend(
			self::GetName(),
			[
				'Capabilities' => array('text', '')
			]
		);
	}
	
	protected function getUserGroupsManager()
	{
		$oUserGroupsModule = \Aurora\System\Api::GetModule('CoreUserGroups');
		return $oUserGroupsModule->getGroupsManager();
	}
	
	protected function getCapabilities()
	{
		$aCapabilities = [];
		foreach ($this->getConfig('Capabilities', []) as $oCapa)
		{
			$aCapabilities[$oCapa['Name']] = [
				'Name' => $this->i18N($oCapa['DisplayNameLangConst']),
				'Description' => $this->i18N($oCapa['DescriptionLangConst']),
				'Modules' => $oCapa['Modules']
			];
		}
		return $aCapabilities;
	}
	
	/**
	 * Obtains module settings.
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		return [
			'Capabilities' => $this->getCapabilities()
		];
	}
	
	public function InitCapas()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);
		
		$oEavManager = \Aurora\System\Managers\Eav::getInstance();
		$aUsers =  $oEavManager->getEntities(\Aurora\Modules\Core\Classes\User::class, [], 0, 0, [], 'PublicId', \Aurora\System\Enums\SortOrder::ASC);
		$aUsersIds = [];
		foreach ($aUsers as $oUser)
		{
			$aUsersIds[] = $oUser->EntityId;
		}
		foreach ($aUsers as $oUser)
		{
			$this->setUserCapabilities($oUser, $aUsersIds);
		}
		return true;
	}
	
	/**
	 * Obtains capabilities of the group.
	 * @param int $GroupId Group identifier.
	 * @return array
	 */
	public function GetCapabilitiesOfGroup($GroupId)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		$oGroup = $this->getUserGroupsManager()->getGroup($GroupId);
		$aCapabilities = $oGroup->{self::GetName() . '::Capabilities'};
		
		return json_decode($aCapabilities);
	}
	
	/**
	 * Saves capability list of the group.
	 * @param int $GroupId Group identifier.
	 * @param array $CapaNames List of capability names.
	 * @return boolean
	 */
	public function SaveCapabilitiesOfGroup($GroupId, $CapaNames)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		
		$oGroup = $this->getUserGroupsManager()->getGroup($GroupId);
		$oGroup->{self::GetName() . '::Capabilities'} = json_encode($CapaNames);
		$oEavManager = \Aurora\System\Managers\Eav::getInstance();
		$bResult = $oEavManager->saveEntity($oGroup);
		
		$aGroupUserObjects = $this->getUserGroupsManager()->getGroupUserObjects($GroupId);
		$aUsersIds = [];
		foreach ($aGroupUserObjects as $oGroupUser)
		{
			$aUsersIds[] = $oGroupUser->UserId;
		}
		$aFilters = ['EntityId' => [$aUsersIds, 'IN']];
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$aUsers = [];
		if ($oCoreDecorator && !empty($aUsersIds))
		{
			$aUsers =  $oEavManager->getEntities(\Aurora\Modules\Core\Classes\User::class, [], 0, 0, $aFilters, 'PublicId', \Aurora\System\Enums\SortOrder::ASC);
		}
		
		foreach ($aUsers as $oUser)
		{
			$this->setUserCapabilities($oUser, $aUsersIds);
		}
		
		return $bResult;
	}
	
	/**
	 * Obtains capabilities for specified group.
	 * @staticvar array $aGroupsCapas
	 * @param object $oUserGroupsManager
	 * @param int $iGroupId
	 * @return array
	 */
	static protected function getGroupCapabilities($oUserGroupsManager, $iGroupId)
	{
		static $aGroupsCapas = [];
		
		if (!isset($aGroupsCapas[$iGroupId]))
		{
			$oGroup = $oUserGroupsManager->getGroup($iGroupId);
			$sCapabilities = $oGroup->{self::GetName() . '::Capabilities'};
			$aGroupsCapas[$iGroupId] = empty($sCapabilities) ? [] : json_decode($sCapabilities);
		}
		
		return $aGroupsCapas[$iGroupId];
	}
	
	/**
	 * Obtains capabilities for specified user.
	 * @staticvar array $aAllUserGroup
	 * @param object $oUserGroupsManager
	 * @param int $iUserId
	 * @param array $aUsersIds
	 * @return array
	 */
	static protected function getUserCapas($oUserGroupsManager, $iUserId, $aUsersIds)
	{
		static $aAllUserGroup = null;
		if ($aAllUserGroup === null && !empty($aUsersIds))
		{
			$aFilters = [
				'$AND' => [
					'UserId' => [$aUsersIds, 'IN']
				]
			];
			$oEavManager = \Aurora\System\Managers\Eav::getInstance();
			$aAllUserGroup = $oEavManager->getEntities(\Aurora\Modules\CoreUserGroups\Classes\GroupUser::class, array(), 0, 0, $aFilters);
		}
		
		$aGroupsOfUser = [];
		if ($aAllUserGroup === null)
		{
			$aGroupsOfUser = $oUserGroupsManager->getGroupsOfUser($iUserId);
		}
		else
		{
			foreach ($aAllUserGroup as $oUserGroup)
			{
				if ($oUserGroup->UserId == $iUserId)
				{
					$aGroupsOfUser[] = $oUserGroup;
				}
			}
		}
		
		$aUserCapas = [];
		foreach ($aGroupsOfUser as $oGroupUser)
		{
			$aGroupCapas = self::getGroupCapabilities($oUserGroupsManager, $oGroupUser->GroupId);
			$aUserCapas = array_unique(array_merge($aUserCapas, $aGroupCapas));
		}
		
		return $aUserCapas;
	}
	
	/**
	 * Applies capabilities for just authenticated user.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterLogin(&$aArgs, &$mResult)
	{
		if ($mResult !== false)
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			if ($oUser instanceof \Aurora\Modules\Core\Classes\User && $oUser->isNormalOrTenant())
			{
				// DB operations are not allowed for super admin here (DB might not be configured yet)
				$this->setUserCapabilities($oUser);
			}
		}
	}
	
	/**
	 * Applies capabilities for users from deleted groups.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterRemoveDeleteGroups(&$aArgs, &$mResult)
	{
		if (is_array($mResult) && !empty($mResult))
		{
			$this->setUserListCapabilities($mResult);
		}
	}
	
	/**
	 * Applies capabilities for users removed from groups.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterRemoveUsersFromGroup(&$aArgs, &$mResult)
	{
		if ($mResult)
		{
			$this->setUserListCapabilities($aArgs['UsersIds']);
		}
	}
	
	/**
	 * Applies capabilities for users removed from groups.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterAddToGroup(&$aArgs, &$mResult)
	{
		if ($mResult)
		{
			$this->setUserListCapabilities($aArgs['UsersIds']);
		}
	}
	
	/**
	 * Applies capabilities for user.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterSaveGroupsOfUser(&$aArgs, &$mResult)
	{
		if ($mResult)
		{
			$this->setUserListCapabilities([$aArgs['UserId']]);
		}
	}
	
	/**
	 * Applies capabilities for users.
	 * @param array $aUsersIds
	 */
	protected function setUserListCapabilities($aUsersIds)
	{
		$oEavManager = \Aurora\System\Managers\Eav::getInstance();
		$aFilters = ['EntityId' => [$aUsersIds, 'IN']];
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$aUsers = [];
		if ($oCoreDecorator && !empty($aUsersIds))
		{
			$aUsers = $oEavManager->getEntities(\Aurora\Modules\Core\Classes\User::class, [], 0, 0, $aFilters, 'PublicId', \Aurora\System\Enums\SortOrder::ASC);
		}
		
		foreach ($aUsers as $oUser)
		{
			$this->setUserCapabilities($oUser, $aUsersIds);
		}
	}
	
	/**
	 * Applies capabilities for user.
	 * @param \Aurora\Modules\Core\Classes\User $oUser
	 * @param array $aUsersIds
	 */
	protected function setUserCapabilities($oUser, $aUsersIds = [])
	{
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$aUserCapas = self::getUserCapas($this->getUserGroupsManager(), $oUser->EntityId, $aUsersIds);

			$aAllCapaModules = [];
			$aAllowedCapaModules = [];
			foreach ($this->getCapabilities() as $sCapaName => $oCapa)
			{
				$aAllCapaModules = array_unique(array_merge($aAllCapaModules, $oCapa['Modules']));
				if (in_array($sCapaName, $aUserCapas))
				{
					$aAllowedCapaModules = array_unique(array_merge($aAllowedCapaModules, $oCapa['Modules']));
				}
			}

			foreach ($aAllCapaModules as $sModuleName)
			{
				if (in_array($sModuleName, $aAllowedCapaModules))
				{
					$oUser->enableModule($sModuleName);
				}
				else
				{
					$oUser->disableModule($sModuleName);
				}
			}
			
			$oCoreModuleDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
			$oCoreModuleDecorator->UpdateUserObject($oUser);
		}
	}
}
