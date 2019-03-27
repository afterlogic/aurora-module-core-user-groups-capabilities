'use strict';

module.exports = function (oAppData) {
	var
		App = require('%PathToCoreWebclientModule%/js/App.js'),
				
		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
		
		Settings = require('modules/%ModuleName%/js/Settings.js')
	;
	
	Settings.init(oAppData);
	
	if (App.getUserRole() === Enums.UserRole.SuperAdmin)
	{
		return {
			/**
			 * Registers admin settings tabs before application start.
			 * 
			 * @param {Object} ModulesManager
			 */
			start: function (ModulesManager)
			{
				ModulesManager.run('AdminPanelWebclient', 'registerAdminPanelTab', [
					function(resolve) {
						require.ensure(
							['modules/%ModuleName%/js/views/GroupCapaAdminSettingsView.js'],
							function() {
								resolve(require('modules/%ModuleName%/js/views/GroupCapaAdminSettingsView.js'));
							},
							'admin-bundle'
						);
					},
					Settings.HashModuleName + '-user',
					TextUtils.i18n('%MODULENAME%/LABEL_SETTINGS_TAB_CAPABILITIES')
				]);
				ModulesManager.run('AdminPanelWebclient', 'changeAdminPanelEntityData', [{
					Type: 'Group',
					AdditionalButtons: [{'ButtonView': require('modules/%ModuleName%/js/views/InitCapaButtonView.js')}]
				}]);
			}
		};
	}
	
	return null;
};
