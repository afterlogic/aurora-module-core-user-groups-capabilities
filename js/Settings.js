'use strict';

var Types = require('%PathToCoreWebclientModule%/js/utils/Types.js');

module.exports = {
	ServerModuleName: 'CoreUserGroupsCapabilities',
	HashModuleName: 'groups-apabilities',
	
	Capabilities: [],

	/**
	 * Initializes settings from AppData object sections.
	 * 
	 * @param {Object} oAppData Object contained modules settings.
	 */
	init: function (oAppData)
	{
		var oAppDataSection = oAppData[this.ServerModuleName];
		if (oAppDataSection)
		{
			this.Capabilities = Types.pObject(oAppDataSection.Capabilities, this.Capabilities);
		}
	}
};
