'use strict';

var
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),
	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),
	
	Settings = require('modules/%ModuleName%/js/Settings.js')
;

/**
 * @constructor
 */
function CInitCapaButtonView()
{
}

CInitCapaButtonView.prototype.ViewTemplate = '%ModuleName%_InitCapaButtonView';


CInitCapaButtonView.prototype.initCapas = function ()
{
	Screens.showLoading(TextUtils.i18n('COREWEBCLIENT/INFO_LOADING'));
	Ajax.send(Settings.ServerModuleName, 'InitCapas', {}, function (oResponse) {
		Screens.hideLoading();
		if (oResponse.Result)
		{
			Screens.showReport(TextUtils.i18n('COREWEBCLIENT/REPORT_SETTINGS_UPDATE_SUCCESS'));
		}
		else
		{
			Api.showErrorByCode(oResponse, TextUtils.i18n('COREWEBCLIENT/ERROR_SAVING_SETTINGS_FAILED'));
		}
	}, this);
};

module.exports = new CInitCapaButtonView();
