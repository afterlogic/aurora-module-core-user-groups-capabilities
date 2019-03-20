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
		
		return $oEavManager->saveEntity($oGroup);
	}
	
	/**
	 * Applies capabilities for user.
	 * @param array $aArgs
	 * @param mixed $mResult
	 */
	public function onAfterLogin(&$aArgs, &$mResult)
	{
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		
		$aGroupsOfUser = $this->getUserGroupsManager()->getGroupsOfUser($oUser->EntityId);
		$aUserCapas = [];
		foreach ($aGroupsOfUser as $oGroupUser)
		{
			$oGroup = $this->getUserGroupsManager()->getGroup($oGroupUser->GroupId);
			$aGroupCapas = json_decode($oGroup->{self::GetName() . '::Capabilities'});
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
