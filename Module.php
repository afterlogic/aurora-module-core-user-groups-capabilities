<?php
/**
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\CoreUserGroupsCapabilities;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public $aCapabilities = [];
			
	public function init()
	{
		$this->aCapabilities = [
			'Mail' => [
				'Name' => $this->i18N('LABEL_MAIL_CAPABILITY_NAME'),
				'Description' => $this->i18N('LABEL_MAIL_CAPABILITY_DESC'),
				'Modules' => ['Mail', 'MailDomains', 'MailNotesPlugin']
			],
			'Files' => [
				'Name' => $this->i18N('LABEL_FILES_CAPABILITY_NAME'),
				'Description' => $this->i18N('LABEL_FILES_CAPABILITY_DESC'),
				'Modules' => ['Files', 'FilesZipFolder']
			],
			'Contacts' => [
				'Name' => $this->i18N('LABEL_CONTACTS_CAPABILITY_NAME'),
				'Description' => $this->i18N('LABEL_CONTACTS_CAPABILITY_DESC'),
				'Modules' => ['Contacts']
			]
		];
		
		$this->subscribeEvent('Core::Login::after', array($this, 'onAfterLogin'));
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
	
	/**
	 * Obtains module settings.
	 * @return array
	 */
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		return ['Capabilities' => $this->aCapabilities];
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
			$this->setUserCapabilities($oUser);
		}
		
		return $bResult;
	}
	
	static protected function getGroupCapabilities($oUserGroupsManager, $iGroupId)
	{
		static $aGroupsCapas = [];
		
		if (!isset($aGroupsCapas[$iGroupId]))
		{
			$oGroup = $oUserGroupsManager->getGroup($iGroupId);
			$aGroupsCapas[$iGroupId] = json_decode($oGroup->{self::GetName() . '::Capabilities'});
		}
		
		return $aGroupsCapas[$iGroupId];
	}
	
	/**
	 * Applies capabilities for user.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterLogin(&$aArgs, &$mResult)
	{
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		$this->setUserCapabilities($oUser);
	}
	
	public function onAfterRemoveUsersFromGroup(&$aArgs, &$mResult)
	{
		$this->setUserListCapabilities($aArgs['UsersIds']);
	}
	
	public function onAfterAddToGroup(&$aArgs, &$mResult)
	{
		$this->setUserListCapabilities($aArgs['UsersIds']);
	}
	
	public function onAfterSaveGroupsOfUser(&$aArgs, &$mResult)
	{
		$this->setUserListCapabilities([$aArgs['UserId']]);
	}
	
	protected function setUserListCapabilities($aUsersIds)
	{
		$oEavManager = \Aurora\System\Managers\Eav::getInstance();
		$aFilters = ['EntityId' => [$aUsersIds, 'IN']];
		$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
		$aUsers = [];
		if ($oCoreDecorator && !empty($aUsersIds))
		{
			$aUsers =  $oEavManager->getEntities(\Aurora\Modules\Core\Classes\User::class, [], 0, 0, $aFilters, 'PublicId', \Aurora\System\Enums\SortOrder::ASC);
		}
		
		foreach ($aUsers as $oUser)
		{
			$this->setUserCapabilities($oUser);
		}
	}
	
	protected function setUserCapabilities($oUser)
	{
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$aGroupsOfUser = $this->getUserGroupsManager()->getGroupsOfUser($oUser->EntityId);
			$aUserCapas = [];
			foreach ($aGroupsOfUser as $oGroupUser)
			{
				$aGroupCapas = $this->getGroupCapabilities($this->getUserGroupsManager(), $oGroupUser->GroupId);
				$aUserCapas = array_unique(array_merge($aUserCapas, $aGroupCapas));
			}

			$aAllCapaModules = [];
			$aAllowedCapaModules = [];
			foreach ($this->aCapabilities as $sCapaName => $oCapa)
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
