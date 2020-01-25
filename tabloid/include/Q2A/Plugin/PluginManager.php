<?php

/*
	Keeps track of the installed plugins
*/

class Q2A_Plugin_PluginManager
{
	const PLUGIN_DELIMITER = ';';
	const OPT_ENABLED_PLUGINS = 'enabled_plugins';

	private $loadBeforeDbInit = array();
	private $loadAfterDbInit = array();

	public function readAllPluginMetadatas()
	{
		$pluginDirectories = $this->getFilesystemPlugins(true);

		foreach ($pluginDirectories as $pluginDirectory) {
			$pluginFile = $pluginDirectory . 'plugin.php';

			if (!file_exists($pluginFile)) {
				continue;
			}

			$metadataUtil = new Q2A_Util_Metadata();
			$metadata = $metadataUtil->fetchFromAddonPath($pluginDirectory);
			if (empty($metadata)) {
				// limit plugin parsing to first 8kB
				$contents = file_get_contents($pluginFile, false, null, 0, 8192);
				$metadata = qa_addon_metadata($contents, 'Plugin', true);
			}

			// skip plugin which requires a later version of Q2A
			if (isset($metadata['min_q2a']) && qa_qa_version_below($metadata['min_q2a'])) {
				continue;
			}
			// skip plugin which requires a later version of PHP
			if (isset($metadata['min_php']) && qa_php_version_below($metadata['min_php'])) {
				continue;
			}

			$pluginInfoKey = basename($pluginDirectory);
			$pluginInfo = array(
				'pluginfile' => $pluginFile,
				'directory' => $pluginDirectory,
				'urltoroot' => substr($pluginDirectory, strlen(BASE_DIR)),
			);

			if (isset($metadata['load_order'])) {
				switch ($metadata['load_order']) {
					case 'after_db_init':
						$this->loadAfterDbInit[$pluginInfoKey] = $pluginInfo;
						break;
					case 'before_db_init':
						$this->loadBeforeDbInit[$pluginInfoKey] = $pluginInfo;
						break;
					default:
				}
			} else {
				$this->loadBeforeDbInit[$pluginInfoKey] = $pluginInfo;
			}
		}
	}

	private function loadPlugins($pluginInfos)
	{
		global $qa_plugin_directory, $qa_plugin_urltoroot;

		foreach ($pluginInfos as $pluginInfo) {
			$qa_plugin_directory = $pluginInfo['directory'];
			$qa_plugin_urltoroot = $pluginInfo['urltoroot'];

			require_once $pluginInfo['pluginfile'];
		}

		$qa_plugin_directory = null;
		$qa_plugin_urltoroot = null;
	}

	public function loadPluginsBeforeDbInit()
	{
		$this->loadPlugins($this->loadBeforeDbInit);
	}

	public function loadPluginsAfterDbInit()
	{
		$enabledPlugins = $this->getEnabledPlugins(false);
		$enabledForAfterDbInit = array();

		foreach ($enabledPlugins as $enabledPluginDirectory) {
			if (isset($this->loadAfterDbInit[$enabledPluginDirectory])) {
				$enabledForAfterDbInit[$enabledPluginDirectory] = $this->loadAfterDbInit[$enabledPluginDirectory];
			}
		}

		$this->loadPlugins($enabledForAfterDbInit);
	}

	public function getEnabledPlugins($fullPath = false)
	{
		$pluginDirectories = $this->getEnabledPluginsOption();

		if ($fullPath) {
			foreach ($pluginDirectories as $key => &$pluginDirectory) {
				$pluginDirectory = PLUGINS_DIR . $pluginDirectory . '/';
			}
		}

		return $pluginDirectories;
	}

	public function setEnabledPlugins($array)
	{
		$this->setEnabledPluginsOption($array);
	}

	public function getFilesystemPlugins($fullPath = false)
	{
		$result = array();

		$fileSystemPluginFiles = glob(PLUGINS_DIR . '*/plugin.php');

		foreach ($fileSystemPluginFiles as $pluginFile) {
			$directory = dirname($pluginFile) . '/';

			if (!$fullPath) {
				$directory = basename($directory);
			}
			$result[] = $directory;
		}

		return $result;
	}

	public function getHashesForPlugins($pluginDirectories)
	{
		$result = array();

		foreach ($pluginDirectories as $pluginDirectory) {
			$result[$pluginDirectory] = md5($pluginDirectory);
		}

		return $result;
	}

	private function getEnabledPluginsOption()
	{
		return explode(self::PLUGIN_DELIMITER, qa_opt(self::OPT_ENABLED_PLUGINS));
	}

	private function setEnabledPluginsOption($array)
	{
		qa_opt(self::OPT_ENABLED_PLUGINS, implode(self::PLUGIN_DELIMITER, $array));
	}

	public function cleanRemovedPlugins()
	{
		$finalEnabledPlugins = array_intersect(
			$this->getFilesystemPlugins(),
			$this->getEnabledPlugins()
		);

		$this->setEnabledPluginsOption($finalEnabledPlugins);
	}
}