'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	CAbstractSettingsFormView = ModulesManager.run('AdminPanelWebclient', 'getAbstractSettingsFormViewClass'),
	
	Cache = require('modules/%ModuleName%/js/Cache.js'),
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
* @constructor of object which is used to manage user groups at the user level.
*/
function CGroupCapaAdminSettingsView()
{
	CAbstractSettingsFormView.call(this, Settings.ServerModuleName);
	
	this.iGroupId = 0;
	
	this.aCapabilities = [];
	_.each(Settings.Capabilities, function (oCapa, sName) {
		this.aCapabilities.push({
			Name: sName,
			DisplayName: oCapa.Name,
			Description: oCapa.Description,
			enabled: ko.observable(false)
		});
	}.bind(this));
}

_.extendOwn(CGroupCapaAdminSettingsView.prototype, CAbstractSettingsFormView.prototype);

CGroupCapaAdminSettingsView.prototype.ViewTemplate = '%ModuleName%_GroupCapaAdminSettingsView';

/**
 * Clears enabled value of every capability.
 */
CGroupCapaAdminSettingsView.prototype.clear = function ()
{
	_.each(this.aCapabilities, function (oCapa) {
		oCapa.enabled(false);
	});
};

/**
 * Runs after routing to this view.
 */
CGroupCapaAdminSettingsView.prototype.onRoute = function ()
{
	this.clear();
	this.getCapabilitiesOfGroup();
};

/**
 * Requests list of capabilities of the current user group.
 */
CGroupCapaAdminSettingsView.prototype.getCapabilitiesOfGroup = function ()
{
	if (Types.isPositiveNumber(this.iGroupId))
	{
		Ajax.send(Settings.ServerModuleName, 'GetCapabilitiesOfGroup', {'GroupId': this.iGroupId}, function (oResponse) {
			if (oResponse.Result)
			{
				_.each(this.aCapabilities, function (oCapa) {
					oCapa.enabled(_.indexOf(oResponse.Result, oCapa.Name) !== -1);
				});
			}
		}, this);
	}
};

/**
 * Saves capability list to the current user group.
 */
CGroupCapaAdminSettingsView.prototype.saveCapabilitiesOfGroup = function()
{
	this.isSaving(true);
	
	var
		aGroupCapas = _.filter(this.aCapabilities, function (oCapa) {
			return oCapa.enabled();
		}),
		oParameters = {
			'GroupId': this.iGroupId,
			'CapaNames': _.map(aGroupCapas, function (oCapa) {
				return oCapa.Name;
			})
		}
	;
	
	Ajax.send(
		Settings.ServerModuleName,
		'SaveCapabilitiesOfGroup',
		oParameters,
		function (oResponse, oRequest) {
			this.isSaving(false);
			if (!oResponse.Result)
			{
				Api.showErrorByCode(oResponse, TextUtils.i18n('COREWEBCLIENT/ERROR_SAVING_SETTINGS_FAILED'));
			}
			else
			{
				Screens.showReport(TextUtils.i18n('COREWEBCLIENT/REPORT_SETTINGS_UPDATE_SUCCESS'));
			}
		},
		this
	);
};

/**
 * Sets access level for the view via entity type and entity identifier.
 * This view is visible only for Group entity type.
 * 
 * @param {string} sEntityType Current entity type.
 * @param {number} iEntityId Indentificator of current intity.
 */
CGroupCapaAdminSettingsView.prototype.setAccessLevel = function (sEntityType, iEntityId)
{
	
	this.visible(sEntityType === 'Group');
	if (this.iGroupId !== iEntityId)
	{
		this.iGroupId = iEntityId;
	}
};

module.exports = new CGroupCapaAdminSettingsView();
